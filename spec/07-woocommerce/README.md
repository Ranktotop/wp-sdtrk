# 07 — WooCommerce-Integration

Optionale Integration, die **nur** greift, wenn WooCommerce installiert/aktiv ist **und** der Redux-Schalter `wc_integration` eingeschaltet ist. Sie trackt Käufe auf der WooCommerce-Order-Received-Seite — browser-seitig (Pixel/Tags aller aktiven Plattformen) und serverseitig (Conversion-APIs auf Order-Status-Übergang), consent-gated und über eine gemeinsame `event_id` (= Order-ID) dedupliziert.

| Datei | Inhalt |
|-------|--------|
| [activation.md](activation.md) | Aktivierungs-Gate (`Wp_Sdtrk_WC_Integration`), Redux-Schalter, Hook-Registrierung |
| [order-mapping.md](order-mapping.md) | `Wp_Sdtrk_WC_Order_Mapper`: Order → kanonisches Event-Array + Positionsliste |
| [browser-purchase.md](browser-purchase.md) | Browser-Purchase auf der Order-Received-Seite (`wp-sdtrk-wc.js`) |
| [server-purchase.md](server-purchase.md) | Server-Conversion-APIs auf Order-Status, Consent-Snapshot, Dedup, Idempotenz |

## Klassen / Dateien

| Artefakt | Pfad |
|----------|------|
| Integration (Gate + Hooks + AJAX + Server-Firing) | [public/class-wp-sdtrk-wc-integration.php](../../public/class-wp-sdtrk-wc-integration.php) |
| Order-Mapper | [public/class-wp-sdtrk-wc-order-mapper.php](../../public/class-wp-sdtrk-wc-order-mapper.php) |
| Browser-Purchase-Skript | [public/js/wp-sdtrk-wc.js](../../public/js/wp-sdtrk-wc.js) |
| Redux-Sektion `WooCommerce` / Schalter `wc_integration` | [admin/class-wp-sdtrk-admin.php](../../admin/class-wp-sdtrk-admin.php) |
| Loader-Registrierung | [includes/class-wp-sdtrk.php](../../includes/class-wp-sdtrk.php) |

## Feuer-Modell (Überblick)

| Seite/Ereignis | Was feuert | Ziel |
|----------------|------------|------|
| `woocommerce_thankyou` / Order-Received-Seite | Browser-Purchase aller aktiven Catcher + Persistenz des Consent-Snapshots/Identifier auf der Order | Browser |
| `woocommerce_order_status_processing` / `…_completed` | Server-Conversion-APIs (Meta/GA4/TikTok), consent-gated, idempotent | Server (S2S) |

Begründung der Trennung: Sofort-Zahlungen springen direkt auf `processing`/`completed`; asynchrone/Offline-Zahlungen wechseln den Status erst später. Browser-Pixel benötigt die Order-Received-Seite, der Server-Pfad läuft unabhängig (und ggf. später) über den Status-Hook.
