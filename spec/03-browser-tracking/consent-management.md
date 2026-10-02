# 03 — Consent-Management

Datei: `public/js/wp-sdtrk-helper.js`, Methode `has_consent(id, service, event)`.

## 1. Unterstützte Consent-Lösung: Borlabs Cookie

Das Plugin prüft vor Pixel-Laden, Server-Senden und dem Schreiben eigener Cookies den Consent. **Borlabs Cookie** ist der einzige explizit unterstützte Dienst, in **v2** und **v3**.

```js
has_consent(id, service, event) {
  if (event.getForce()) return -1;        // Bypass pro Seite → Consent übersprungen
  switch (service) {
    case 'borlabs':
      // Borlabs v2
      if (typeof window.BorlabsCookie.checkCookieConsent === "function")
        return window.BorlabsCookie.checkCookieConsent(id) === true;
      // Borlabs v3
      if (typeof window.BorlabsCookie.Consents?.hasConsent === "function")
        return window.BorlabsCookie.Consents.hasConsent(id) === true;
      return false;                        // Borlabs (noch) nicht geladen → kein Consent
    default:
      return -1;                           // Cookie-Service none → kein Block
  }
}
```

Rückgaben:

| Wert | Bedeutung | Folge |
|------|-----------|-------|
| `true` | Borlabs meldet Consent | Pfad wird freigeschaltet |
| `false` | Borlabs meldet keinen Consent, liefert keinen Boolean oder ist nicht geladen | blockiert |
| `-1` | Consent bewusst umgangen: Bypass auf der Seite (§3) oder Cookie-Service `none` in den Settings | Pfad wird freigeschaltet |

Die Catcher prüfen auf `!== false`. Consent wird also nur übersprungen, wenn das explizit konfiguriert ist. Ein Borlabs, das später lädt als die Engine, blockiert zunächst; hat der Besucher bereits zugestimmt, führt Borlabs den Opt-in-Code aus, und der Backload (§5) holt die Events nach.

## 2. Konfiguration je Plattform

Pro Plattform und je getrennt für Browser/Server (siehe [04 › Options-Referenz](../04-admin-and-options/option-reference.md)):

| Localize-Feld | Option | Bedeutung |
|---------------|--------|-----------|
| `b_cs` / `s_cs` | `*_trk_browser_cookie_service` / `*_trk_server_cookie_service` | `none` oder `borlabs` |
| `b_ci` / `s_ci` | `*_trk_browser_cookie_id` / `*_trk_server_cookie_id` | Borlabs-Cookie-ID (z. B. `facebook`) |
| `b_e` / `s_e` | abgeleitet | Browser- bzw. Server-Tracking aktiv |

## 3. Force- / Bypass-Modus

Auf Seitenebene kann Consent **umgangen** werden (z. B. interne Thank-You-Pages):

```js
// engine.js
if (this.localizedData.trkow !== "") this.event.enableForce();
else this.event.disableForce();
```

`trkow` (Tracking-Overwrite) stammt aus der Metabox-Option `wp_sdtrk_bypass_consent` der jeweiligen Seite (siehe [04 › Metabox](../04-admin-and-options/metabox-and-helpers.md)). Bei aktivem Force liefert `has_consent` immer `-1` → es wird unabhängig vom Consent getrackt, einschließlich der Cookies und des Fingerprints aus §4.

## 4. Was der Consent abdeckt

Ohne Consent (bzw. ohne Bypass) speichert das Plugin nichts auf dem Endgerät und liest keine Gerätemerkmale aus:

| Element | Gate |
|---------|------|
| Pixel/Tag (`loadPixel()`) | Browser-Consent der Plattform |
| `_fbc`, `_fbp` (Meta) | Browser- **oder** Server-Consent für Meta (`b_enabled \|\| s_enabled`) |
| `_ga` (GA, Client-ID) | Browser- oder Server-Consent für GA; wird vor `loadPixel()` geschrieben, weil das Google-Tag das Cookie zur Identifikation übernimmt |
| `_ttc`, `_ttp` (TikTok) | Browser- oder Server-Consent für TikTok |
| `_oai_oppref` (ChatGPT Ads) | Browser- oder Server-Consent für ChatGPT Ads |
| Fingerprint | wird erst in `get_Cid()` berechnet, also mit GA-Consent ([Fingerprinting](cookies-fingerprint-decryption.md#2-fingerprinting)) |
| `wpsdtrk_utm_*` | Consent für mindestens eine Plattform; `engine.persist_onConsent()` nach dem Aufbau der Catcher und in jedem Backload |
| `localStorage` `wp_sdtrk_wc_<orderId>` (Purchase-Reload-Guard) | wie `wpsdtrk_utm_*`; ohne Consent wird kein Purchase getrackt, also ist auch kein Guard nötig ([07 › Purchase](../07-woocommerce/purchase-tracking.md#6-deduplizierung)) |

Die Cookie-Ermittlung läuft in `validate()` hinter der Consent-Prüfung. Da `isOngoingBackload()` `validate()` erneut aufruft, entstehen die Cookies bei einem späteren Opt-in auf derselben Seite.

## 5. Backload bei nachträglichem Consent

Events werden in `wp_sdtrk_history` gehalten, sodass bei späterer Zustimmung zuvor blockierte Events nachgespielt werden können (Backload-Mechanik der Engine). Jeder Catcher mit Consent-Gate stellt dafür globale Funktionen `wp_sdtrk_backload_<type>_b()` (Browser) und `_s()` (Server) bereit, z. B. `wp_sdtrk_backload_oai_b()`; die Admin-Oberfläche zeigt den passenden Opt-in-Code am Cookie-ID-Feld. Jede dieser Funktionen ruft beim tatsächlichen Nachholen zusätzlich `engine.persist_onConsent()` auf.

## 6. Consent Mode v2 (Google-Tag)

Nur der GA-Catcher (`wp-sdtrk-ga.js`) sendet zusätzlich zum Blockieren die vier Einwilligungssignale des Google Consent Mode v2 (`analytics_storage`, `ad_storage`, `ad_user_data`, `ad_personalization`). Ohne das Signal `ad_user_data` stellt Google den **Conversion-Export von GA4 nach Google Ads ein** — Analytics erfasst die Käufe weiterhin, in Ads bleibt die Conversion-Spalte auf 0.

### Basic Consent Mode

Das Tag wird **bis zur Einwilligung vollständig blockiert**; vor der Zustimmung geht kein Request an Google. Die Signale werden erst beim Laden des Tags gesetzt. Reihenfolge in `loadPixel()`:

```
dataLayer + gtag-Shim anlegen
  → set_consentMode()      // 'default' (alles denied) + 'update' (alles granted)
  → gtag.js injizieren
  → gtag('js') / gtag('config')
```

Die Consent-Kommandos müssen **vor** dem `config`-Kommando in der `dataLayer` stehen, sonst hat sich das Tag bereits konfiguriert, wenn sie eintreffen.

### Signal-Herleitung

Alle vier Signale sind `granted`. Der Catcher erreicht `loadPixel()` ausschließlich mit erteiltem Consent (bzw. bei Cookie-Service `none`, wo er per Konfiguration bedingungslos feuert) — dieselbe Einwilligung deckt die Werbenutzung mit ab. Eine getrennte Werbe-Einwilligung kennt das Plugin bewusst nicht.

Das vorangestellte `default` mit durchgehend `denied` bleibt trotzdem: Google erwartet vor jedem Mess-Kommando einen expliziten Ausgangszustand.

### Fremde Consent-Mode-Implementierung

`has_externalConsentMode()` durchsucht die `dataLayer` nach einem bereits vorhandenen `consent`-Kommando. Wird eines gefunden (z. B. weil der Consent-Manager den Consent Mode selbst verwaltet), setzt das Plugin **weder** `default` **noch** `update`. So streiten sich nie zwei Quellen um denselben Zustand.

Damit ergeben sich zwei Konstellationen, die sich gegenseitig ausschließen:

| Borlabs-Option „Einwilligungsmodus verwenden" | Wer setzt die Signale | Was der Betreiber hinterlegen muss |
|---|---|---|
| **aus** | das Plugin (`set_consentMode()`) | nur den Opt-in-Code mit `wp_sdtrk_backload_ga_b()` |
| **an** | Borlabs (Fallback-Code schreibt `default`, Opt-in/Opt-out schreiben `update`) | Opt-in-, Opt-out- und Fallback-Code mit allen vier Signalen |

Der zweite Fall ist eine Stolperfalle: Die Borlabs-Vorlage setzt von sich aus **nur `analytics_storage`** — die drei `ad_*`-Signale ergeben sich dort ausschließlich aus den IAB-TCF-Zwecken, die ein Shop üblicherweise nicht einsetzt. Ohne Anpassung bleibt `ad_user_data` dauerhaft `denied` und Google stellt den Conversion-Export ein, ohne dass irgendwo ein Fehler sichtbar wird.

Beide Fälle inklusive der fertigen Code-Blöcke liefert die Admin-UI aus: Steht der Browser-Consent auf `borlabs`, erscheint unter dem Feld *Cookie ID* das aufklappbare Panel `ga_trk_borlabs_snippets` (RAW-Feld, Inhalt aus `Wp_Sdtrk_Admin::get_ga_borlabs_snippets()`) zum Kopieren — siehe [04 › Options-Referenz](../04-admin-and-options/option-reference.md#google).
