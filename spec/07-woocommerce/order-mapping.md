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

`id` ist die **Variations-ID**, falls vorhanden (`get_variation_id() ?: get_product_id()`) — so stimmen Order-Positionen mit dem Produkt-Feed und den AddToCart-/ViewItem-IDs überein (Katalog-Konsistenz); für einfache Produkte greift die Parent-Produkt-ID. `price` ist der Stückpreis (`get_total()` der Position / Menge). Diese Liste wird von der Engine als `items[]` ins Event übernommen und von jedem Kauf-Catcher in seine plattformspezifische Mehr-Produkt-Payload (`contents[]`/`items[]`) umgesetzt.

## `Wp_Sdtrk_WC_Order_Mapper::productLine($product, int $qty = 1): array`

Eine einzelne Position für die **ViewItem**- und **AddToCart**-Payloads (statt einer Order ein `WC_Product`), in derselben Form wie `lineItems`:

```php
[ 'id' => string, 'name' => string, 'qty' => int, 'price' => float ]
```

`price` ist der Einzel-Anzeigepreis (`wc_get_price_to_display($product)`; außerhalb WooCommerce Fallback `$product->get_price()`). Verwendung: [view-item-and-add-to-cart.md](view-item-and-add-to-cart.md).

**Dedup:** Der Gesamtwert (`value`) und die Order-ID (`orderId`) führen im Event dazu, dass JS `grabOrderId()` und PHP `getEventId()` die Order-ID als gemeinsame `event_id` liefern.
</content>
