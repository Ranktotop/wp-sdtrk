# 07 — WooCommerce-Integration

Optionale Integration, die **nur** greift, wenn WooCommerce installiert/aktiv ist **und** der Redux-Schalter `wc_integration` eingeschaltet ist. Sie trackt Käufe auf der WooCommerce-Order-Received-Seite, indem sie die Order-Daten in die **bestehende Engine-Mechanik** einspeist: Die Engine feuert das Purchase-Event darüber wie jedes andere Event browser- **und** serverseitig in einem Durchlauf — über alle aktiven Plattformen, consent-gated und über eine gemeinsame `event_id` (= Order-ID) dedupliziert. Es gibt **keinen** Order-Status-Hook, **keinen** Consent-Snapshot und **kein** dediziertes Purchase-Skript.

| Datei | Inhalt |
|-------|--------|
| [activation.md](activation.md) | Aktivierungs-Gate (`Wp_Sdtrk_WC_Integration`), Redux-Schalter, Hook-Registrierung |
| [order-mapping.md](order-mapping.md) | Order → Datenquelle für die Engine (`build_order_payload` + `lineItems`) |
| [purchase-tracking.md](purchase-tracking.md) | Danke-Seiten-Injection: Engine-Ingestion, Browser + Server, Mehr-Produkt, Währung, Dedup, Consent |
| [view-item-and-add-to-cart.md](view-item-and-add-to-cart.md) | ViewItem (Produktseite) & AddToCart über dasselbe Seed-Modell; Quellen-Präzedenz |
| [product-feed.md](product-feed.md) | RSS-2.0/`g:`-Produkt-Feed, Token-Endpoint, täglicher Cron |

## Klassen / Dateien

| Artefakt | Pfad |
|----------|------|
| Integration (Gate + Commerce-Daten-Localize) | [public/class-wp-sdtrk-wc-integration.php](../../public/class-wp-sdtrk-wc-integration.php) |
| Order-/Produkt-Mapper (`lineItems` / `productLine`) | [public/class-wp-sdtrk-wc-order-mapper.php](../../public/class-wp-sdtrk-wc-order-mapper.php) |
| Engine (Ingestion in `collect_eventData`) | [public/js/wp-sdtrk-engine.js](../../public/js/wp-sdtrk-engine.js) |
| Produkt-Feed | [public/class-wp-sdtrk-wc-feed.php](../../public/class-wp-sdtrk-wc-feed.php) |
| Cron (täglich) | [includes/class-wp-sdtrk-cron.php](../../includes/class-wp-sdtrk-cron.php) |
| Redux-Sektion `WooCommerce` / Schalter `wc_integration` | [admin/class-wp-sdtrk-admin.php](../../admin/class-wp-sdtrk-admin.php) |
| Loader-Registrierung | [includes/class-wp-sdtrk.php](../../includes/class-wp-sdtrk.php) |

## Feuer-Modell (Überblick)

| Seite/Ereignis | Was passiert | Ziel |
|----------------|--------------|------|
| Order-Received-Seite (`wp_enqueue_scripts`) | `Wp_Sdtrk_WC_Integration::localize_commerce_data()` legt die Order-Daten (`wp_sdtrk_wc.order`) auf das Engine-Skript. Die Engine seedet daraus ein Purchase-Event und feuert es über alle aktiven Catcher | Browser **und** Server (S2S) in einem Durchlauf |
| Produkt-Detailseite (`wp_enqueue_scripts`) | `localize_commerce_data()` legt bei `is_product()` die Produktdaten (`wp_sdtrk_wc.viewItem`) auf das Engine-Skript. Die Engine seedet ein `view_item`-Event ([view-item-and-add-to-cart.md](view-item-and-add-to-cart.md)) | Browser **und** Server (S2S) in einem Durchlauf |

Die Engine sendet pro aktivem Catcher den Browser-Hit (`fireData`) **und** den Server-Call (`sendData` → AJAX `validateTracker`). Browser- und Server-Event teilen dieselbe `event_id` (= Order-ID), weil JS `grabOrderId()` und PHP `getEventId()` beide die Order-ID bevorzugen.

## Bewusste Trade-offs

- **Server hängt am Browser:** Läuft das Engine-JS nicht (Tab vor dem Laden geschlossen, JS deaktiviert, AdBlocker blockt `admin-ajax`), wird auch der Server-Call nicht gesendet — dieselbe Abhängigkeit wie auf jeder anderen Trackingseite.
- **Zahlungszeitpunkt:** Gefeuert wird beim Erreichen der Danke-Seite (Bestellung abgeschlossen), unabhängig davon, ob die Zahlung bei asynchronen Methoden (Vorkasse/Rechnung) bereits eingegangen ist.
- **Nicht DB-autoritativ:** Der Server-Purchase wird über den Browser-AJAX (`validateTracker`) gefeuert; `value`/`items`/`currency`/`orderId` kommen also client-geliefert und sind — wie alle anderen Events des Plugins — manipulierbar. Der frühere Order-Status-Pfad las diese Werte serverseitig aus der `WC_Order` (DB-autoritativ). Für Conversion-Tracking akzeptiert (betrifft nur die eigenen Kampagnendaten); eine serverseitige Re-Validierung gegen die Order ist bewusst nicht implementiert.
</content>
