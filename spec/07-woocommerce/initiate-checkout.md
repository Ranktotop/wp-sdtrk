# 07 — InitiateCheckout (`begin_checkout`)

Beim Eintritt in die Kasse trackt die Integration ein InitiateCheckout-Event über **dasselbe** Engine-Seed-Modell wie Purchase/ViewItem/AddToCart ([view-item-and-add-to-cart.md](view-item-and-add-to-cart.md)): Die Warenkorb-Daten werden ans Engine-Skript lokalisiert, die Engine baut daraus **ein** Event und feuert es browser- **und** serverseitig in einem Durchlauf über alle aktiven Catcher. Es ist **keine** Catcher- oder Server-Tracker-Änderung nötig: Der interne Eventname `begin_checkout` ist in jeder `convert_eventname()`-Abbildung bereits enthalten (Meta/TikTok → `InitiateCheckout`, GA4 → natives `begin_checkout`).

Das Event läuft unter dem bestehenden Gate `Wp_Sdtrk_WC_Integration::is_active()` — **kein** eigener Redux-Schalter (gebündelt mit der Integration).

## Auslöser & Präzedenz

`Wp_Sdtrk_WC_Integration::localize_commerce_data()` setzt `$is_checkout = is_checkout() && WC()->cart && !WC()->cart->is_empty()`. Im reinen Resolver `resolve_commerce_source()` rangiert `beginCheckout` **direkt hinter `order` und vor `addToCart`** (Gesamtreihenfolge **order > beginCheckout > addToCart > viewItem**, siehe [view-item-and-add-to-cart.md](view-item-and-add-to-cart.md#eine-quelle-pro-seitenaufbau-präzedenz)).

- `is_checkout()` ist auch auf der **Order-Received-Seite** true — dort gewinnt aber `order` (höhere Präzedenz), daher kein Konflikt.
- **Checkout schlägt einen anstehenden AddToCart-Puffer:** Liegt beim Checkout-Gewinn noch eine Pending-Position in der WC-Session (`wp_sdtrk_atc`), wird dieser Puffer **verworfen** (geleert) — analog zum `order`-Zweig —, damit er auf einer Folgeseite kein konkurrierendes `add_to_cart` mehr seedet.
- **Leerer Warenkorb → kein Event:** Bei leerem Warenkorb gilt `$is_checkout = false`; es wird nichts lokalisiert.

## Bereitstellung der Daten

`build_begin_checkout_payload($cart)` baut das Objekt `wp_sdtrk_wc.beginCheckout`:

| Feld | Quelle |
|------|--------|
| `value` | Σ `price * qty` über **alle** Warenkorb-Positionen (= Summe der `line_total`; nach Rabatt, vor Versand), gerundet auf `wc_get_price_decimals()` |
| `currency` | `get_woocommerce_currency()` (Shop-Währung) |
| `items` | die gesamte Warenkorb-Liste via `Wp_Sdtrk_WC_Order_Mapper::cartLines($cart)` — `[{id,name,qty,price}, …]` |

## Ingestion in der Engine

`collect_eventData()` ruft `seedWcCommerce(wp_sdtrk_wc)`; bei der `beginCheckout`-Quelle (`else if`-Zweig **vor** `addToCart`) übernimmt `seedCommerceEvent('begin_checkout', …)` die gemeinsamen Felder:

- `setEventName({wc:'begin_checkout'})`, `setValue({wc})`, `setCurrency()`, `setItems()`
- `setProdId/Name({wc})` aus der ersten Position

`catchPageHit(2) → catchEventHit(2)` feuert daraufhin pro Catcher den Browser-Hit **und** den Server-Call. Plattform-Abbildung des Event-Namens: Meta `InitiateCheckout`, GA4 `begin_checkout`, TikTok `InitiateCheckout`. Wert, Währung und Positionen reisen über die regulären `getValue()/getCurrency()/getItems()`-Pfade mit — identisch zu Purchase/AddToCart.

## Kein Once-Guard

InitiateCheckout ist ein Seiten-Event: Es feuert bei **jedem** Checkout-Seitenaufbau erneut (auch nach Reload, z. B. Gutschein-Eingabe in der klassischen Kasse). Der `localStorage`-Reload-Guard des Purchase gilt hier **nicht** — wie bei ViewItem. Browser- und Server-Event teilen — mangels Order-ID — die gemeinsame Engine-`eventId` (`grabOrderId()` fällt auf `getEventId()` zurück).

## Bewusste Trade-offs

- **Checkout verwirft einen Pending-AddToCart.** In der seltenen Kollision (Add-to-Cart unmittelbar vor dem Checkout-Render, ohne dazwischenliegenden Seitenaufbau, der den Puffer verbraucht) gewinnt `begin_checkout` und der gepufferte AddToCart-Hit geht verloren. Bewusst gewählt: Der Kasseneintritt ist das stärkere Funnel-Signal.
- **Preisbasis.** `value` ist die Summe der Warenkorb-Positionswerte (`line_total`, nach Rabatt, **ohne** Versand). Der Purchase-`value` ist dagegen der Order-Gesamtwert (`get_total()`); je nach Versand-/Steuerkonfiguration kann sich die Basis zwischen Checkout-Schritt und Kauf unterscheiden (akzeptiert).
- **Server hängt am Browser.** Läuft das Engine-JS nach dem Localize nicht (Bot, JS deaktiviert, AdBlocker), geht auch der Server-Call verloren — dieselbe Klasse wie bei jedem anderen Event ([README › Trade-offs](README.md#bewusste-trade-offs)).
