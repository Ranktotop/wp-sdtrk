# 07 — ViewItem & AddToCart

Neben dem Purchase trackt die Integration Funnel-Events über **dasselbe** Engine-Seed-Modell wie der Kauf ([purchase-tracking.md](purchase-tracking.md)): Die passenden Produkt-/Warenkorb-Daten werden ans Engine-Skript lokalisiert, die Engine baut daraus **ein** Event und feuert es browser- **und** serverseitig in einem Durchlauf über alle aktiven Catcher. Es ist **keine** Catcher- oder Server-Tracker-Änderung nötig: `view_item`/`add_to_cart` sind in jeder `convert_eventname()`-Abbildung (Browser **und** Server) bereits enthalten.

Diese Events laufen unter dem bestehenden Gate `Wp_Sdtrk_WC_Integration::is_active()` — **kein** eigener Redux-Schalter (gebündelt mit der Integration, wie Purchase).

## Eine Quelle pro Seitenaufbau (Präzedenz)

`Wp_Sdtrk_WC_Integration::localize_commerce_data()` (Hook `wp_enqueue_scripts`, Priorität 20) lokalisiert **genau eine** Datenquelle als `wp_sdtrk_wc.*`. Die Auswahl trifft der reine Resolver `resolve_commerce_source($order_received, $has_pending_atc, $is_product)` in fester Reihenfolge:

| Reihenfolge | Quelle | Bedingung | Lokalisiert |
|:-:|--------|-----------|-------------|
| 1 | `order` | Order-Received-Seite + auflösbare Order (gültiger Key) | `wp_sdtrk_wc.order` |
| 2 | `viewItem` | `is_product()` | `wp_sdtrk_wc.viewItem` |
| — | `none` | sonst | nichts |

So wird pro Seitenaufbau höchstens **ein** Commerce-Event geseedet. Die Engine wertet die `wp_sdtrk_wc`-Unterschlüssel in derselben Reihenfolge als `else if`-Kette aus.

## ViewItem (Produkt-Detailseite)

### Bereitstellung der Daten

Auf `is_product()` baut `build_view_item_payload($product)` das Objekt `wp_sdtrk_wc.viewItem`:

| Feld | Quelle |
|------|--------|
| `prodId` | `$product->get_id()` |
| `name` | `$product->get_name()` |
| `value` | Einzel-Anzeigepreis (`wc_get_price_to_display($product)`; Steuerbehandlung nach Shop-Einstellung) |
| `currency` | `get_woocommerce_currency()` (Shop-Währung) |
| `items` | **eine** Position via `Wp_Sdtrk_WC_Order_Mapper::productLine($product, 1)` — `[{id,name,qty:1,price}]` |

### Ingestion in der Engine

`collect_eventData()` übernimmt — falls `wp_sdtrk_wc.viewItem` vorhanden und keine `.order`-Quelle gesetzt — diese Werte:

- `setEventName({wc:'view_item'})`, `setValue({wc})`, `setCurrency()`, `setItems()`
- `setProdId/Name({wc})` aus der (einzigen) Position

`catchPageHit(2) → catchEventHit(2)` feuert daraufhin pro Catcher den Browser-Hit **und** den Server-Call. Plattform-Abbildung des Event-Namens: Meta `ViewContent`, GA4 `view_item`, TikTok `ViewContent` (jeweils über die bestehende `convert_eventname()`-Logik). Wert, Währung und Position reisen über die regulären `getValue()/getCurrency()/getItems()`-Pfade mit — identisch zum Purchase.

### Kein Once-Guard

ViewItem ist ein Seiten-Event: Es feuert bei **jedem** Produktseiten-Aufruf erneut. Der `localStorage`-Reload-Guard des Purchase (Order darf je Browser nur einmal feuern) gilt hier **nicht**. Browser- und Server-Event teilen — mangels Order-ID — die gemeinsame Engine-`eventId` (`grabOrderId()` fällt auf `getEventId()` zurück), wie bei jedem Nicht-Kauf-Event.
