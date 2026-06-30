# Task-Liste — WooCommerce: InitiateCheckout (`begin_checkout`)

> Reihenfolge nach [plan.md](plan.md). Jeder Task = ein vollständiger vertikaler Pfad. **Definition of Done je Task:** Code geändert **und** betroffene Spec auf Ist-Zustand gebracht (siehe [CLAUDE.md](../CLAUDE.md)).

Bestätigte Entscheidungen: Präzedenz **order > beginCheckout > addToCart > viewItem** · Checkout gewinnt gegen ATC-Puffer (Puffer wird verworfen) · **kein Guard** (feuert bei jedem Checkout-Load, analog `view_item`) · nur bei nicht-leerem Warenkorb · `value` = Σ `line_total`.

Legende: ☑ erledigt · `[ ]`-Boxen = offene manuelle Live-Verifikation

> **Status:** T1–T5 implementiert, alle Unit-Tests grün (26 PHP + 8 mjs). Offen: Live-Smoke-Test der Browser-/Server-Pfade auf der Checkout-Seite.

---

## Phase 1 — Server-Datenpfad

### T1 — Mapper `cartLines()` + `build_begin_checkout_payload()` (PHP)
- **Tun:**
  - `Wp_Sdtrk_WC_Order_Mapper::cartLines($cart): array` — iteriert `$cart->get_cart()`, liefert je Position `{id,name,qty,price}` (id = `variation_id ?: product_id`, price = `line_total/qty`), Shape wie `lineItems()`.
  - `Wp_Sdtrk_WC_Integration::build_begin_checkout_payload($cart): array` → `['beginCheckout' => ['value','currency','items']]`; `value` = Σ `line_total`, gerundet via `wc_get_price_decimals()` (analog `build_add_to_cart_payload()`).
- **Abhängig von:** —
- **Dateien:** [public/class-wp-sdtrk-wc-order-mapper.php](../public/class-wp-sdtrk-wc-order-mapper.php), [public/class-wp-sdtrk-wc-integration.php](../public/class-wp-sdtrk-wc-integration.php), `tests/test-wc-begin-checkout-payload.php` · Scope: S
- **Akzeptanz:**
  - [ ] `cartLines()` liefert `{id,name,qty,price}`; id bevorzugt Variation; price = Stückpreis aus `line_total/qty`.
  - [ ] `build_begin_checkout_payload()` liefert `beginCheckout.value` (String, gerundet), `.currency`, `.items` (volle Liste).
- **Verifikation:**
  - [ ] `php tests/test-wc-begin-checkout-payload.php` grün (Fake-Cart, mehrere Positionen, value = Σ line_total, String-Coercion).
  - [ ] Bestehende `php tests/test-wc-*.php` grün.

### T2 — Präzedenz `resolve_commerce_source()` + Localize-Zweig (PHP)
- **Tun:**
  - `resolve_commerce_source(bool $order_received, bool $is_checkout, bool $has_pending_atc, bool $is_product)` — Reihenfolge **order > beginCheckout > addToCart > viewItem**.
  - `localize_commerce_data()`: `$is_checkout = is_checkout() && WC()->cart && !WC()->cart->is_empty()`; neuer `case 'beginCheckout'`: pending ATC-Puffer leeren (wie `order`-Zweig), dann `build_begin_checkout_payload(WC()->cart)` lokalisieren.
- **Abhängig von:** T1
- **Dateien:** `public/class-wp-sdtrk-wc-integration.php`, `tests/test-wc-commerce-precedence.php` · Scope: S
- **Akzeptanz:**
  - [ ] Resolver: `is_checkout` true & kein `order` → `beginCheckout`, auch wenn `has_pending_atc` true.
  - [ ] Localize-Zweig leert den ATC-Puffer beim Checkout-Gewinn.
  - [ ] Leerer Warenkorb → kein Event.
- **Verifikation:**
  - [ ] `php tests/test-wc-commerce-precedence.php` erweitert & grün (neue Signatur, beginCheckout schlägt addToCart, Puffer geleert).
  - [ ] Alle `php tests/test-wc-*.php` grün.

### Checkpoint A — Server-Pfad
- [ ] Alle `php tests/test-wc-*.php` grün.
- [ ] Resolver-Reihenfolge & Puffer-Verwerfen verifiziert.

---

## Phase 2 — Browser-Seed

### T3 — Engine seedet `begin_checkout` (JS)
- **Tun:**
  - [public/js/wp-sdtrk-engine.js](../public/js/wp-sdtrk-engine.js) `seedWcCommerce()`: `else if (wc.beginCheckout)`-Zweig **vor** `addToCart` → `seedCommerceEvent('begin_checkout', wc.beginCheckout)`. **Kein** Once-Guard.
- **Abhängig von:** T2
- **Dateien:** `public/js/wp-sdtrk-engine.js`, `tests/test-wc-engine-seeding.mjs` · Scope: S
- **Akzeptanz:**
  - [ ] `wc.beginCheckout` seedet `begin_checkout` mit value/currency/items.
  - [ ] Branch-Reihenfolge: order → beginCheckout → addToCart → viewItem.
- **Verifikation:**
  - [ ] `node tests/test-wc-engine-seeding.mjs` erweitert & grün (beginCheckout-Branch + Präzedenz vor addToCart).

---

## Phase 3 — Tracker-Verifikation

### T4 — `begin_checkout`-Mapping in allen Plattformen bestätigen
- **Tun:** Grep-Verifikation, dass `begin_checkout` in Meta/GA/TikTok **Tracker** + **Catcher** gemappt ist (Meta/TikTok → `InitiateCheckout`, GA4 → nativ). Nur bei Lücke `case 'begin_checkout'` ergänzen.
- **Abhängig von:** — (parallel möglich)
- **Dateien:** ggf. `public/class-wp-sdtrk-tracker-{ga,tt}.php`, `public/js/wp-sdtrk-{ga,tt}.js` · Scope: XS
- **Akzeptanz:** [ ] `convert_eventname()` behandelt `begin_checkout` überall (kein `default → false`).
- **Verifikation:** [ ] Grep `begin_checkout` über Tracker + Catcher zeigt je Treffer.

---

## Phase 4 — Spec nachführen (Pflicht laut CLAUDE.md)

### T5 — Spec auf Ist-Zustand bringen
- **Tun:**
  - [spec/00-overview.md](../spec/00-overview.md) — Feature-Bullet: InitiateCheckout (Checkout-Seite).
  - [spec/07-woocommerce/README.md](../spec/07-woocommerce/README.md) — Intro + Feuer-Modell-Tabelle (Zeile „Checkout-Seite `is_checkout()`"), Dateien-Tabelle (neuer Verweis).
  - [spec/07-woocommerce/view-item-and-add-to-cart.md](../spec/07-woocommerce/view-item-and-add-to-cart.md) — Präzedenz überall → `order > beginCheckout > addToCart > viewItem`.
  - [spec/07-woocommerce/order-mapping.md](../spec/07-woocommerce/order-mapping.md) — `cartLines()` dokumentieren.
  - **Neu:** `spec/07-woocommerce/initiate-checkout.md` — Format gespiegelt von purchase-tracking.md/view-item-and-add-to-cart.md.
- **Abhängig von:** T1–T3
- **Dateien:** Spec · Scope: M
- **Akzeptanz:** [ ] Feuer-Modell + Feature-Matrix nennen InitiateCheckout · [ ] Präzedenz in Spec überall korrekt · [ ] neue Datei verlinkt · [ ] kein Changelog-Stil.

### Checkpoint B — Komplett
- [ ] Alle `php tests/test-wc-*.php` + `node tests/test-wc-engine-seeding.mjs` grün.
- [ ] Spec = Ist-Zustand (Präzedenz, Feuer-Modell, neue Datei).
- [ ] Bereit für Live-Smoke-Test (Checkout-Seite: Browser + Server `begin_checkout`; Kollision; Reload).
