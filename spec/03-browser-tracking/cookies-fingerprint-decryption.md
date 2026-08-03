# 03 — Cookies, Fingerprinting & Client-Decryption

## 1. Cookies & Click-IDs

`Wp_Sdtrk_Helper` (`wp-sdtrk-helper.js`) verwaltet Cookies. Erstpartei-Cookies erhalten das Präfix `wpsdtrk_`:

```js
save_cookie(name, value, days, firstparty = true) {
  var key = firstparty ? 'wpsdtrk_' + name : name;
  document.cookie = key + "=" + value + ";expires=…; path=/; domain=." + rootDomain + ";";
}
```

Verwaltete IDs (Auswahl):

| Cookie / ID | Plattform | Herkunft | typ. Laufzeit |
|-------------|-----------|----------|---------------|
| `_fbp` | Meta | generiert `fb.1.{ts}.{rnd}` | 90 Tage |
| `_fbc` | Meta | aus `fbclid` | 90 Tage |
| `_ga`/`cid` | GA4 | GA-Client-ID (ggf. aus FP) | 90 Tage |
| `_ttc` | TikTok | aus `ttclid` | 28 Tage |
| `_ttp` | TikTok | Cookie | 90 Tage |
| `wpsdtrk_utm_*` | alle | URL-UTMs (persistiert) | 14 Tage |

> Click-IDs werden persistiert, damit sie auch bei späterer Conversion (anderer Pageview) noch verfügbar sind und an die Server-API gehängt werden können.

### Meta-Click-Window (`_fbc`)

`get_fbc()` (`wp-sdtrk-meta.js`) hält den Wert im Format `fb.{subdomainIndex}.{creationTime}.{fbclid}`. Der Zeitstempel im dritten Segment ist Teil des Werts und markiert den Zeitpunkt des Klicks — Meta verwirft Klick-IDs, die älter als 90 Tage sind.

Priorisierung:

| Zustand | Verhalten |
|---------|-----------|
| `fbclid` im URL-Parameter | Wert wird neu gebaut (neue Klick-ID + aktueller Zeitstempel) und überschreibt den gespeicherten — auch einen noch gültigen. Cookie: 90 Tage. |
| Kein `fbclid`, Cookie < 90 Tage | Gespeicherter Wert wird zurückgegeben, **ohne** die Cookie-Laufzeit zu verlängern. |
| Kein `fbclid`, Cookie ≥ 90 Tage oder Format ungültig | `""` — es wird kein `fbc` gesendet. |

> Die Laufzeit wird im Cookie-Zweig bewusst **nicht** erneuert: Ein Rolling Refresh bei jedem Pageview würde eine längst abgelaufene Klick-ID bei wiederkehrenden Besuchern unbegrenzt am Leben halten, während der Zeitstempel im Wert eingefroren bleibt. `is_fbc_valid()` prüft Segmentanzahl und Zeitstempel; serverseitig prüft `Wp_Sdtrk_Tracker_Meta::isFbcValid()` dasselbe noch einmal — siehe [02 Meta CAPI](../02-server-tracking/platform-meta-capi.md#identitäts-matching-daten).

### Google: keine Klick-ID-Speicherung

Der GA-Catcher persistiert **keine** `gclid`. Grund: Die GA4 Measurement Protocol kennt keinen Klick-ID-Parameter — ihre Feldliste (`client_id`, `user_id`, `timestamp_micros`, `user_properties`, `user_data`, `consent`, `user_location`, `ip_override`, `device`, `user_agent`, `validation_behavior`, `events[]`) enthält nichts dergleichen. Werbe-Attribution stitcht GA4 über `client_id`/Session aus dem Browser-Tag. Browserseitig verwaltet das Google-Tag seine `_gcl_*`-Cookies ohnehin selbst, sobald die `gclid` in der URL steht.

> Eine `gclid` wäre nur für den Offline-Conversion-Import über `ConversionUploadService.UploadClickConversions` der **Google Ads API** verwertbar — eine eigene Schnittstelle mit Developer-Token, OAuth und einer Conversion-Action vom Typ `UPLOAD_CLICKS`. Das Plugin bindet sie nicht an.

`set_storedCampaign()` prüft den URL-Parameter `gclid` weiterhin — allerdings nur, um bei bezahltem Traffic die Landing-URL im Cookie `_cd` (14 Tage) abzulegen. Das ist Kampagnen-Kontext für `gtag()`, keine Klick-ID-Persistenz.

### TikTok (`_ttc`)

`get_Ttc()` (`wp-sdtrk-tt.js`) speichert die nackte `ttclid` für 28 Tage und **erneuert die Laufzeit bei jedem Pageview**. Der Wert geht serverseitig als `user_data.ttclid` an die Events API.

> Abweichend von Meta/Google ist der Rolling Refresh hier kein Defekt: TikTok bindet die Gültigkeit einer Klick-ID an das im **Attribution Manager** konfigurierte CTA-Fenster und prüft sie gegen die eigene Klick-Datenbank — eine Klick-ID außerhalb des Fensters wird schlicht nicht attribuiert, nicht beanstandet. TikToks eigenes `ttclid`-Cookie läuft „13 Monate ab der letzten Verwendung", ist also ebenfalls rollierend.
>
> Die 28 Tage entsprechen dem **Maximum** der pro Ad Group wählbaren CTA-Fenster (1/7/14/28 Tage; Default 7). Damit ist jede mögliche Kontoeinstellung abgedeckt — eine zu lange Laufzeit kostet nichts, eine zu kurze verlöre späte Conversions.

## 2. Fingerprinting

Datei: `public/js/wp-sdtrk-fp.js`, Klasse `Wp_Sdtrk_Fp`.

Aktivierbar über Option `trk_fp`. Bildet einen cookielosen Identifier aus Browser-Signalen (u. a. Canvas, User-Agent, Bildschirmauflösung). Verwendung: Fallback-Identität (`userFP` im Event) bzw. als Basis für stabile Pseudo-IDs, wenn keine Cookies/Consent vorliegen.

> Lokalisierung: `wp_sdtrk_fp = { enabled: true|false }`.

## 3. Client-Decryption-Steuerung

Datei: `public/js/wp-sdtrk-decrypter.js`, Klasse `Wp_Sdtrk_Decrypter`.

Steuert, ob GET-Parameter vor Engine-Start serverseitig entschlüsselt werden müssen:

```
decrypt():
  if has_Services():                         // z. B. 'ds24' aktiv
      decryptOnServer(params) ──AJAX──▶ PHP-Decrypter ──▶ wp_sdtrk_startEngine(decrypted)
  else:
      setDecryptedData(rawParams) ──▶ wp_sdtrk_startEngine(this)
```

Aktive Services kommen aus `wp_sdtrk_decrypter = { services: ["ds24"] }`. Die eigentliche Entschlüsselung passiert **serverseitig** (`Wp_Sdtrk_Decrypter_ds24`) — siehe [06 Integrationen › Digistore24](../06-integrations.md#digistore24-decryption). So bleibt der geheime Schlüssel im Backend.
