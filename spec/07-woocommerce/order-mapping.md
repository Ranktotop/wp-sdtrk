# 07 — Order → Datenquelle für die Engine

Die WooCommerce-Order wird nicht in das Server-Event-Array übersetzt, sondern als **Datenquelle** auf der Order-Received-Seite bereitgestellt und von der Engine übernommen (siehe [purchase-tracking.md](purchase-tracking.md)).

## `Wp_Sdtrk_WC_Integration::build_order_payload($order): array`

Baut das an das Engine-Skript lokalisierte Objekt `wp_sdtrk_wc.order`:

| Schlüssel | Quelle |
|-----------|--------|
| `orderId` | `$order->get_id()` |
| `value` | `$order->get_total()` (Order-Gesamtwert) |
| `currency` | `$order->get_currency()` |
| `email` | `$order->get_billing_email()` |
| `firstName` | `$order->get_billing_first_name()` |
| `lastName` | `$order->get_billing_last_name()` |
| `items` | `Wp_Sdtrk_WC_Order_Mapper::lineItems($order)` |

## `Wp_Sdtrk_WC_Order_Mapper::lineItems($order): array`

Strukturierte Positionsliste über **alle** Warenkorb-Positionen:

```php
[ ['id' => string, 'name' => string, 'qty' => int, 'price' => float], … ]
```

`price` ist der Stückpreis (`get_total()` der Position / Menge). Diese Liste wird von der Engine als `items[]` ins Event übernommen und von jedem Kauf-Catcher in seine plattformspezifische Mehr-Produkt-Payload (`contents[]`/`items[]`) umgesetzt.

**Dedup:** Der Gesamtwert (`value`) und die Order-ID (`orderId`) führen im Event dazu, dass JS `grabOrderId()` und PHP `getEventId()` die Order-ID als gemeinsame `event_id` liefern.
</content>
