# 04 — Options-Referenz

Alle Felder werden in der Option `wp_sdtrk_options` (Redux) gespeichert und über `WP_SDTRK_Helper_Options` gelesen. Cookie-Service-Felder akzeptieren `none` oder `borlabs`.

> Wiederkehrendes Muster pro Plattform: `{p}_pixelid`/`_measurement_id`/`_tracking_id`, `{p}_trk_browser*`, `{p}_trk_server*`, `{p}_trk_debug*`, jeweils mit `_cookie_service`/`_cookie_id`.

## General

| Feld | Typ | Bedeutung |
|------|-----|-----------|
| `brandname` | text | Standard-Markenname |
| `trk_fp` | switch | Fingerprinting aktivieren |
| `trk_time` | switch | Zeit-Events aktivieren |
| `trk_time_group_seconds[]` | repeater | Sekundenschwellen (Default 10) |
| `trk_scroll` | switch | Scroll-Events aktivieren |
| `trk_scroll_group_percent[]` | repeater | Scroll-Prozent (Default 33) |
| `trk_buttons` | switch | Button-Click-Events |
| `trk_visibility` | switch | Element-Sichtbarkeits-Events |

## Meta {#meta}

| Feld | Bedeutung |
|------|-----------|
| `meta_pixelid` | Pixel-ID |
| `meta_trk_debug` | Debug aktivieren |
| `meta_trk_server_debug_code` | Test-Event-Code |
| `meta_trk_browser` | Browser-Pixel aktiv |
| `meta_trk_browser_cookie_service` / `_id` | Consent-Service / Borlabs-Cookie-ID |
| `meta_trk_server` | Server-CAPI aktiv |
| `meta_trk_server_token` | CAPI Access-Token |
| `meta_trk_server_cookie_service` / `_id` | Consent (Server) |

## Google

| Feld | Bedeutung |
|------|-----------|
| `ga_measurement_id` | Measurement-ID `G-…` |
| `ga_trk_debug` | Debug (→ `/debug/`-Endpoint) |
| `ga_trk_debug_live` | Debug gegen Live-Endpoint |
| `ga_trk_browser` | Browser-Tag aktiv |
| `ga_trk_browser_cookie_service` / `_id` | Consent (Browser) |
| `ga_trk_server` | Server-MP aktiv |
| `ga_trk_server_token` | API Secret |
| `ga_trk_server_cookie_service` / `_id` | Consent (Server) |

## TikTok

| Feld | Bedeutung |
|------|-----------|
| `tt_pixelid` | Pixel-Code |
| `tt_trk_debug` | Debug |
| `tt_trk_server_debug_code` | Test-Event-Code |
| `tt_trk_browser` | Browser-Pixel aktiv |
| `tt_trk_browser_cookie_service` / `_id` | Consent (Browser) |
| `tt_trk_server` | Server-API aktiv |
| `tt_trk_server_token` | Access-Token |
| `tt_trk_server_cookie_service` / `_id` | Consent (Server) |

## LinkedIn

| Feld | Bedeutung |
|------|-----------|
| `lin_pixelid` | Partner-ID |
| `lin_trk_debug` | Debug |
| `lin_trk_browser` | Browser-Tag aktiv |
| `lin_trk_browser_cookie_service` / `_id` | Consent (Browser) |
| `lin_trk_manage_mappings` | RAW-Button → Mapping-Seite |

> LinkedIn-Conversion-Zuordnungen liegen **nicht** in `wp_sdtrk_options`, sondern in der Tabelle `sdtrk_linkedin` ([05](../05-data-model/linkedin-mapping.md)).

## Funnelytics

| Feld | Bedeutung |
|------|-----------|
| `fl_tracking_id` | Pixel-ID |
| `fl_trk_debug` | Debug |
| `fl_trk_browser` | Browser-Tracking aktiv |
| `fl_trk_browser_cookie_service` / `_id` | Consent (Browser) |

## Mautic

| Feld | Bedeutung |
|------|-----------|
| `mtc_tracking_id` | Mautic Base-URL |
| `mtc_trk_debug` | Debug |
| `mtc_trk_browser` | Browser-Tracking aktiv |
| `mtc_trk_browser_cookie_service` / `_id` | Consent (Browser) |

## Matomo

| Feld | Bedeutung |
|------|-----------|
| `mtm_tracking_id` | Matomo Base-URL |
| `mtm_site_id` | Site-ID |
| `mtm_api_key` | API-Key |
| `mtm_trk_debug` | Debug |
| `mtm_trk_browser` | Browser-Tracking aktiv |
| `mtm_trk_browser_cookie_service` / `_id` | Consent (Browser) |

## Data Sources — Digistore24

| Feld | Bedeutung |
|------|-----------|
| `ds24_encrypt_data` | DS24-Entschlüsselung aktivieren |
| `ds24_encrypt_data_key` | Thank-You-Key (Secret) |

> Generisches Decrypter-Muster: `validateTracker`-fremder Pfad nutzt `{service}_encrypt_data_key`; der Klassenname wird als `Wp_Sdtrk_Decrypter_{service}` gebildet ([06](../06-integrations.md)).

## Page-Level (Metabox, `wp_postmeta`)

| Feld | Bedeutung |
|------|-----------|
| `wp_sdtrk_product_id` | Produkt-ID für ViewContent o. ä. |
| `wp_sdtrk_bypass_consent` | Consent umgehen (→ `trkow`) |
