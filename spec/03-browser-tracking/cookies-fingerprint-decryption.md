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
| `gclid` | Google Ads | URL-Parameter | 90 Tage |
| `_ttc` | TikTok | aus `ttclid` | 7 Tage |
| `_ttp` | TikTok | Cookie | 90 Tage |
| `wpsdtrk_utm_*` | alle | URL-UTMs (persistiert) | 14 Tage |

> Click-IDs werden persistiert, damit sie auch bei späterer Conversion (anderer Pageview) noch verfügbar sind und an die Server-API gehängt werden können.

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
