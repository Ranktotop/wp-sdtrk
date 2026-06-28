# 07 — Order → Event-Mapping

`Wp_Sdtrk_WC_Order_Mapper` ([public/class-wp-sdtrk-wc-order-mapper.php](../../public/class-wp-sdtrk-wc-order-mapper.php)) übersetzt eine `WC_Order` in das Array-Schema, das [`Wp_Sdtrk_Tracker_Event`](../../public/class-wp-sdtrk-tracker-event.php) erwartet.

## `toEventArray($order): array`

| Schlüssel | Form | Quelle | Hinweis |
|-----------|------|--------|---------|
| `eventName` | Liste | `['purchase']` | → `getEventName()` = `purchase` |
| `value` | Liste | `[ (string) $order->get_total() ]` | → `getEventValue()` (float) |
| `orderId` | Liste | `[ (string) $order->get_id() ]` | → `getTransactionId()` **und** `getEventId()` (Dedup) |
| `prodId` | Liste | erste Position | → `getProductId()` |
| `prodName` | Liste | erste Position | → `getProductName()` |
| `userEmail` | Liste | `get_billing_email()` | |
| `userFirstName` | Liste | `get_billing_first_name()` | |
| `userLastName` | Liste | `get_billing_last_name()` | |
| `eventSource` | Einzelwert | `get_checkout_order_received_url()` | → `getEventSource()` (`setAndFilled`) |
| `eventSourceAdress` | Einzelwert | `get_customer_ip_address()` | → `getEventIp()` |
| `eventSourceAgent` | Einzelwert | `get_customer_user_agent()` | → `getEventAgent()` |
| `currency` | Einzelwert | `get_currency()` | s. [99 Befunde](../99-findings.md) — Tracker hardcoden derzeit `EUR` |
| `utm` | assoziativ | Order-Meta `_wp_sdtrk_utm` (falls vorhanden) | → `getUtmData()` |

Listenförmige Schlüssel werden vom Event-Modell über `grabFirstValue()` gelesen, Einzelwerte über `setAndFilled()`.

**Dedup:** `getEventId()` liefert die Order-ID (da `orderId` gesetzt). Browser- und Server-Event verwenden damit dieselbe `event_id`.

## `lineItems($order): array`

Strukturierte Positionsliste für Mehr-Produkt-Payloads:

```php
[ ['id' => string, 'name' => string, 'qty' => int, 'price' => float], … ]
```

`price` ist der Stückpreis (`get_total()` der Position / Menge). Das kanonische Event trägt nur die **erste** Position (Event-Modell ist single-product); die vollständige Liste steht für plattformspezifische `contents[]`/`items[]`-Payloads zur Verfügung.
