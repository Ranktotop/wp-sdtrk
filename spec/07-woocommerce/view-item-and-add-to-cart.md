# 07 — ViewItem & AddToCart

Neben dem Purchase trackt die Integration Funnel-Events über **dasselbe** Engine-Seed-Modell wie der Kauf ([purchase-tracking.md](purchase-tracking.md)): Die passenden Produkt-/Warenkorb-Daten werden ans Engine-Skript lokalisiert, die Engine baut daraus **ein** Event und feuert es browser- **und** serverseitig in einem Durchlauf über alle aktiven Catcher. Es ist **keine** Catcher- oder Server-Tracker-Änderung nötig: `view_item`/`add_to_cart` sind in jeder `convert_eventname()`-Abbildung (Browser **und** Server) bereits enthalten.

Diese Events laufen unter dem bestehenden Gate `Wp_Sdtrk_WC_Integration::is_active()` — **kein** eigener Redux-Schalter (gebündelt mit der Integration, wie Purchase).

## Eine Quelle pro Seitenaufbau (Präzedenz)

`Wp_Sdtrk_WC_Integration::localize_commerce_data()` (Hook `wp_enqueue_scripts`, Priorität 20) lokalisiert **genau eine** Datenquelle als `wp_sdtrk_wc.*`. Die Auswahl trifft der reine Resolver `resolve_commerce_source($order_received, $has_pending_atc, $is_product)` in fester Reihenfolge **order > addToCart > viewItem**:

| Reihenfolge | Quelle | Bedingung | Lokalisiert |
|:-:|--------|-----------|-------------|
| 1 | `order` | Order-Received-Seite + auflösbare Order (gültiger Key) | `wp_sdtrk_wc.order` |
| 2 | `addToCart` | Pending-Position(en) in der WC-Session | `wp_sdtrk_wc.addToCart` |
| 3 | `viewItem` | `is_product()` | `wp_sdtrk_wc.viewItem` |
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

## AddToCart (server-deferred)

Single-Product-Seiten fügen per Default über einen **Formular-Submit** in den Warenkorb (kein AJAX, die Seite navigiert weg), Archiv-/Shop-Listen über WooCommerces AJAX-`added_to_cart`. Statt zwei Client-Pfade zu pflegen, wird der Add **serverseitig gepuffert** und beim **nächsten Seitenaufbau** geseedet — das deckt **alle** Add-Flows ab und passt 1:1 zum Seed-Modell.

### 1. Puffern beim Hinzufügen

`capture_add_to_cart()` ist an den Hook `woocommerce_add_to_cart` gehängt (feuert bei AJAX- **und** Formular-Adds). Inaktiv, außer `is_active()` und `WC()->session` ist verfügbar. Das hinzugefügte Produkt (`wc_get_product($variation_id ?: $product_id)`) wird via `Wp_Sdtrk_WC_Order_Mapper::productLine($product, $quantity)` an die Session-Liste **`wp_sdtrk_atc`** angehängt.

### 2. Verbrauchen & Seeden

`localize_commerce_data()` liest die Pending-Liste (`pending_add_to_cart()`). Hat `addToCart` Vorrang (siehe Präzedenz oben), baut `build_add_to_cart_payload($pending)` das Objekt `wp_sdtrk_wc.addToCart`:

| Feld | Quelle |
|------|--------|
| `value` | Σ `price * qty` über **alle** gepufferten Positionen |
| `currency` | `get_woocommerce_currency()` (Shop-Währung) |
| `items` | die gesamte Pufferliste `[{id,name,qty,price}, …]` |

Die Engine seedet daraus — `else if`-Zweig **vor** `viewItem` — ein `add_to_cart`-Event (`setEventName({wc:'add_to_cart'})` + `value`/`currency`/`items` + `prodId/Name` aus der ersten Position) und feuert es browser- **und** serverseitig: Meta `AddToCart`, GA4 `add_to_cart`, TikTok `AddToCart`.

### 3. Once-Guard (Session-Verbrauch)

Beim Lokalisieren wird die Session-Liste **vor** dem Localize geleert (`WC()->session->set('wp_sdtrk_atc', [])`). Ein Reload der Seite seedet daher **kein** zweites AddToCart. Das ersetzt den `localStorage`-Reload-Guard des Purchase serverseitig.

### Bewusste Trade-offs

- **Nicht echtzeit bei AJAX-Adds.** Ein reiner AJAX-Add (Archiv) löst keinen Seitenaufbau aus; AddToCart feuert erst beim **nächsten** Navigieren. Formular-Adds (Single-Product) feuern auf der Folgeseite (Cart/Redirect).
- **Mehrere Adds → ein Event.** Werden vor einem Seitenaufbau mehrere Produkte hinzugefügt, entsteht **ein** AddToCart-Event mit allen Positionen in `items[]` (Folge des Ein-Event-pro-Load-Modells). Der Warenkorb-Zuwachs reist verlustfrei mit; es gibt **kein** Ein-Event-pro-Position.
- **Clear-on-Localize.** Läuft das Engine-JS nach dem Localize nicht (Bot, JS deaktiviert, AdBlocker), geht dieser eine AddToCart-Hit verloren — dieselbe Klasse wie „Server hängt am Browser" ([README › Trade-offs](README.md#bewusste-trade-offs)).
- **Dedup ohne Order-ID.** Browser- und Server-AddToCart teilen die gemeinsame Engine-`eventId` (`grabOrderId()` → `getEventId()`), wie jedes Nicht-Kauf-Event.

> **Preisbasis:** `value`/`price` von ViewItem und AddToCart sind der **Anzeigepreis** (`wc_get_price_to_display()`, Steuer nach Shop-Einstellung). Der Purchase-`value` ist dagegen der tatsächlich berechnete Order-Gesamtwert (`get_total()`); je nach Steuerkonfiguration kann sich die Basis zwischen Funnel-Schritt und Kauf leicht unterscheiden (akzeptiert).
