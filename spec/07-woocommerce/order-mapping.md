# 07 — Order → Datenquelle für die Engine

Die WooCommerce-Order wird nicht in das Server-Event-Array übersetzt, sondern als **Datenquelle** auf der Order-Received-Seite bereitgestellt und von der Engine übernommen (siehe [purchase-tracking.md](purchase-tracking.md)).

## Produkt-ID (`id`)

Alle Positions-Mapper (`lineItems`, `cartLines`, `productLine`) setzen die Feld-`id` auf die **numerische WooCommerce-ID** — die Variations-ID, wenn die Position eine Variation ist, sonst die Eltern-Produkt-ID. Das ist der kanonische, immer vorhandene und eindeutige Produktschlüssel.

> **Warum numerisch (und nicht die SKU):** Die Pixel-/CAPI-`content_ids` **müssen** mit der `<g:id>` des Katalog-Feeds übereinstimmen, sonst meldet Meta eine Katalog-Übereinstimmungsrate unter 90 %. Der Feed veröffentlicht dieselbe numerische ID als `<g:id>` ([product-feed.md](product-feed.md), `Wp_Sdtrk_WC_Feed::feed_items()`). SKUs werden bewusst **nicht** verwendet: Sie sind in WooCommerce optional (können leer, nicht eindeutig oder nachträglich geändert sein) und würden das Matching brüchig machen.

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

`id` ist die numerische Produkt-ID (`get_variation_id() ?: get_product_id()`, s. o.). `price` ist der Stückpreis (`get_total()` der Position / Menge). Diese Liste wird von der Engine als `items[]` ins Event übernommen und von jedem Kauf-Catcher in seine plattformspezifische Mehr-Produkt-Payload (`contents[]`/`items[]`) umgesetzt.

## `Wp_Sdtrk_WC_Order_Mapper::productLine($product, int $qty = 1): array`

Eine einzelne Position für die **ViewItem**- und **AddToCart**-Payloads (statt einer Order ein `WC_Product`), in derselben Form wie `lineItems`:

```php
[ 'id' => string, 'name' => string, 'qty' => int, 'price' => float ]
```

`id` ist die numerische Produkt-ID (`$product->get_id()`, s. o.). `price` ist der Einzel-Anzeigepreis (`wc_get_price_to_display($product)`; außerhalb WooCommerce Fallback `$product->get_price()`). Verwendung: [view-item-and-add-to-cart.md](view-item-and-add-to-cart.md).

## `Wp_Sdtrk_WC_Order_Mapper::cartLines($cart): array`

Strukturierte Positionsliste über **alle** Warenkorb-Positionen (`$cart->get_cart()`) für die **InitiateCheckout**-Payload, in derselben Form wie `lineItems`:

```php
[ ['id' => string, 'name' => string, 'qty' => int, 'price' => float], … ]
```

`id` ist die numerische Produkt-ID (`variation_id ?: product_id`, s. o.). `price` ist der Stückpreis aus dem Warenkorb-Positionswert (`line_total` / Menge; nach Rabatt, vor Versand). Verwendung: [initiate-checkout.md](initiate-checkout.md).

**Dedup:** Der Gesamtwert (`value`) und die Order-ID (`orderId`) führen im Event dazu, dass JS `grabOrderId()` und PHP `getEventId()` die Order-ID als gemeinsame `event_id` liefern.
</content>
