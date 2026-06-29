# 07 — Purchase-Tracking (Danke-Seiten-Injection)

Käufe werden nicht über einen Sonderweg, sondern über die reguläre Engine getrackt. Auf der Order-Received-Seite werden die Order-Daten bereitgestellt; die Engine baut daraus ein Purchase-Event und feuert es browser- **und** serverseitig wie jedes andere Event.

## 1. Bereitstellung der Order-Daten

`Wp_Sdtrk_WC_Integration::localize_order_data()` ist an `wp_enqueue_scripts` (Priorität **20**, also nach dem Enqueue des Engine-Skripts) gehängt. Inaktiv, außer `is_active()` und es lässt sich über `is_order_received_page()` + `get_query_var('order-received')` eine Order auflösen.

Per `wp_localize_script('wp_sdtrk-engine', 'wp_sdtrk_wc', …)` wird das Objekt `wp_sdtrk_wc.order` auf das Engine-Skript gelegt (Aufbau in `build_order_payload()`):

| Feld | Quelle |
|------|--------|
| `orderId` | `$order->get_id()` |
| `value` | `$order->get_total()` (Order-Gesamtwert) |
| `currency` | `$order->get_currency()` |
| `email` / `firstName` / `lastName` | Billing-Daten der Order |
| `items` | `Wp_Sdtrk_WC_Order_Mapper::lineItems()` — alle Positionen `[{id,name,qty,price}]` |

## 2. Ingestion in der Engine

`collect_eventData()` ([wp-sdtrk-engine.js](../../public/js/wp-sdtrk-engine.js)) übernimmt — falls `wp_sdtrk_wc.order` vorhanden — diese Werte als **autoritative** Purchase-Felder ins Event:

- `setOrderId({wc})`, `setEventName({wc:'purchase'})`, `setValue({wc})`, `setCurrency()`
- `setUserEmail/FirstName/LastName({wc})`
- `setItems()` (ganzer Warenkorb)
- `setProdId/Name({wc})` aus der **ersten** Position (Fallback für die single-product-Getter)

Das geschieht **vor** der Catcher-Konstruktion. Dadurch trägt das Engine-Event die Käuferdaten bereits beim Pixel-Init — Meta Advanced Matching (`fbq('init', pid, get_data_user())`) sendet em/fn/ln also automatisch, ohne Sonderbehandlung.

UTM-Daten werden nicht über `wp_sdtrk_wc` übergeben; sie kommen wie sonst aus den persistierten Erstpartei-Cookies.

## 3. Browser **und** Server in einem Durchlauf

Die Engine ruft pro Catcher `catchPageHit(2)` → `catchEventHit(2)`: Browser-Hit (`fireData`) **und** Server-Call (`sendData` → AJAX `validateTracker`, [02 › AJAX-Pipeline](../02-server-tracking/ajax-pipeline.md)). Der Server rekonstruiert das Event aus dem gesendeten Objekt; `items` und `currency` reisen als Event-Felder mit.

## 4. Mehr-Produkt-Payloads

Jeder Kauf-Catcher baut seine plattformspezifische Mehr-Produkt-Payload aus `getItems()` (Fallback: single-product aus `prodId`, wenn die Liste leer ist):

| Plattform | Browser | Server |
|-----------|---------|--------|
| Meta | `content_ids` (alle IDs) + `contents` `[{id,quantity}]` | `custom_data.content_ids` + `custom_data.contents` |
| GA4 | `items[]` `{id,name,quantity,price,brand}` | MP `items[]` |
| TikTok | `properties.contents[]` `{content_id,content_name,content_type,quantity,price}` | Events-API `properties.contents[]` |

## 5. Währung

Die Währung kommt aus dem Event (`getCurrency()`); `EUR` ist nur noch der Fallback, wenn keine gesetzt ist. Für WooCommerce ist das die Shop-Währung (`get_currency()`), für Nicht-WC-Events greift der `EUR`-Fallback (unverändertes Verhalten).

## 6. Deduplizierung

Gemeinsame `event_id` = Order-ID:

| Plattform | Browser | Server | Mechanik |
|-----------|---------|--------|----------|
| Meta | `eventID = grabOrderId()` = Order-ID | `event_id = getEventId()` = Order-ID | identische `event_id` |
| GA4 | `transaction_id` = Order-ID | `transaction_id` = Order-ID | GA4 dedupliziert per `transaction_id` |
| TikTok | `event_id = "<Order-ID>_<hash>"` | `event_id = "<Order-ID>_<hash>"` | identischer `hash` |

Mehrfaches Laden der Danke-Seite ist unkritisch: gleiche `event_id` → Plattform-Dedup.

## 7. Consent

Browser- und Server-Consent werden wie sonst pro Catcher beim Engine-Aufbau ausgewertet (`helper.has_consent`, Borlabs v2/v3). Nur Catcher mit erteiltem Consent feuern den jeweiligen Pfad. Wird Consent erst nach dem Seitenaufbau erteilt, greift der bestehende Backload-Mechanismus der Catcher — eine Purchase-spezifische Sonderlogik gibt es nicht.
</content>
