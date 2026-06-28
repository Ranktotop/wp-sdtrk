# 03 — Browser-Tracking (JavaScript)

Die clientseitige Schicht erfasst Events im Browser, lädt die nativen Pixel/Tags und schickt Events parallel an das PHP-Backend (für Server-Tracking). Alle Skripte liegen in `public/js/` und werden von `Wp_Sdtrk_Public` registriert/lokalisiert.

## Inhalt

| Datei | Thema |
|-------|-------|
| [engine-and-lifecycle.md](engine-and-lifecycle.md) | `Wp_Sdtrk_Engine`: Initialisierung, Orchestrierung, Run-Phase |
| [event-collection.md](event-collection.md) | Event-Datenmodell im Browser, Quellen (URL/Cookie/Localize/DOM) |
| [catchers.md](catchers.md) | Die Catcher-Module je Plattform + DOM-Konventionen |
| [consent-management.md](consent-management.md) | Borlabs-Consent (v2/v3), Force-/Bypass-Modus |
| [cookies-fingerprint-decryption.md](cookies-fingerprint-decryption.md) | Cookies/Click-IDs, Fingerprinting, Client-Decrypter |

## Skript-Übersicht (`public/js/`)

| Datei | JS-Klasse | Rolle |
|-------|-----------|-------|
| `wp-sdtrk-engine.js` | `Wp_Sdtrk_Engine` | Zentrale Orchestrierung |
| `wp-sdtrk-event.js` | `Wp_Sdtrk_Event` | Event-Datencontainer |
| `wp-sdtrk-helper.js` | `Wp_Sdtrk_Helper` | AJAX, Cookies, Consent, Params |
| `wp-sdtrk-fp.js` | `Wp_Sdtrk_Fp` | Browser-Fingerprinting |
| `wp-sdtrk-decrypter.js` | `Wp_Sdtrk_Decrypter` | Client-seitige Decryption-Steuerung |
| `wp-sdtrk-meta.js` | `Wp_Sdtrk_Catcher_Meta` | Meta-Pixel (`fbq`) + Server |
| `wp-sdtrk-ga.js` | `Wp_Sdtrk_Catcher_Ga` | GA4 (`gtag`) + Server |
| `wp-sdtrk-tt.js` | `Wp_Sdtrk_Catcher_Tt` | TikTok (`ttq`) + Server |
| `wp-sdtrk-lin.js` | `Wp_Sdtrk_Catcher_Lin` | LinkedIn (`lintrk`), nur Browser |
| `wp-sdtrk-fl.js` | `Wp_Sdtrk_Catcher_Fl` | Funnelytics, nur Browser |
| `wp-sdtrk-mtc.js` | `Wp_Sdtrk_Catcher_Mtc` | Mautic (`mt`), nur Browser |
| `wp-sdtrk-mtm.js` | `Wp_Sdtrk_Catcher_Mtm` | Matomo (`_paq`), nur Browser |

> **Kürzel:** `fp` = Fingerprint, `lin` = LinkedIn, `fl` = Funnelytics, `mtc` = Mautic, `mtm` = Matomo.

## Kommunikation mit PHP

Jeder Catcher hat zwei Ausgabewege:
- **`fireData()`** → ruft das native Pixel auf (`fbq`, `gtag`, `ttq`, `lintrk`, `_paq`, …).
- **`sendData(handler, data)`** → AJAX an `wp_sdtrk_handle_public_ajax_callback` (→ Server-Tracker).

Konfiguration kommt per `wp_localize_script` als JS-Globals (`wp_sdtrk_engine`, `wp_sdtrk_meta`, `wp_sdtrk_ga`, …). Siehe [04 › Settings & Localize](../04-admin-and-options/settings-and-menu.md) und [engine-and-lifecycle.md](engine-and-lifecycle.md).
