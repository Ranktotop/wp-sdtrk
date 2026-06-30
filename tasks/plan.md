# Implementation Plan: WooCommerce InitiateCheckout (`begin_checkout`)

## Context

Die WooCommerce-Integration trackt heute **Purchase**, **ViewItem** und **AddToCart** über ein einheitliches Seed-Modell: `localize_commerce_data()` legt pro Seitenaufbau **genau eine** Commerce-Quelle (`wp_sdtrk_wc.{order|addToCart|viewItem}`) auf das Engine-Skript, die Engine seedet daraus ein Event und feuert es browser- **und** serverseitig in einem Durchlauf über alle aktiven Catcher.

Es fehlt das **InitiateCheckout**-Event, das beim Eintritt in die Kasse (`is_checkout()`) feuern soll — die letzte Lücke der WC-Funnel-Abdeckung (ViewItem → AddToCart → **InitiateCheckout** → Purchase).

**Gute Nachricht:** Der interne Eventname `begin_checkout` ist in allen drei Server-Trackern und Browser-Catchern **bereits gemappt** (Meta/TikTok → `InitiateCheckout`, GA4 → natives `begin_checkout`). Das ist daher **keine** Tracker-Änderung, sondern eine reine Erweiterung von WC-Integration + Engine-Seed + Spec + Tests, die exakt dem ViewItem/AddToCart-Muster folgt.

### Bestätigte Design-Entscheidungen
- **Präzedenz:** `order > beginCheckout > addToCart > viewItem`. Checkout gewinnt gegen einen noch anstehenden AddToCart-Puffer; dieser Puffer wird in der (seltenen) Kollision **verworfen** (wie es der `order`-Zweig heute schon tut).
- **Re-Fire:** Kein Guard — `begin_checkout` feuert bei **jedem** Checkout-Seitenaufbau, exakt analog zu `view_item`.
- **Leerer Warenkorb:** `begin_checkout` feuert nur bei nicht-leerem Warenkorb (sonst kein Event).
- **`value`:** Summe der Warenkorb-Positionswerte (`line_total`, nach Rabatt, ohne Versand), gerundet auf `wc_get_price_decimals()` — analog zu `build_add_to_cart_payload()`.

## Architektur-Entscheidungen
- **Keine neuen Hooks, kein neues Skript.** Erweiterung erfolgt innerhalb von `localize_commerce_data()` (läuft bereits an `wp_enqueue_scripts`, Prio 20) und `seedWcCommerce()`.
- **Reiner Resolver bleibt rein.** `resolve_commerce_source()` bekommt einen zusätzlichen `$is_checkout`-Parameter; die Warenkorb-/Seiten-Erkennung bleibt im aufrufenden `localize_commerce_data()`. Damit bleibt der Resolver ohne WP/WC unit-testbar.
- **Mapper-Wiederverwendung.** Neue Methode `cartLines($cart)` im `Wp_Sdtrk_WC_Order_Mapper` spiegelt die `{id,name,qty,price}`-Shape von `lineItems()`/`productLine()`, sodass Engine + Catcher die Checkout-Items genau wie Order-/ATC-Items behandeln.
- **Eventname intern `begin_checkout`** (nicht „InitiateCheckout") — konsistent mit dem bereits existierenden Mapping in allen Catchern/Trackern.

## Kritische Dateien
| Datei | Änderung |
|-------|----------|
| `public/class-wp-sdtrk-wc-order-mapper.php` | Neue Methode `cartLines($cart)` |
| `public/class-wp-sdtrk-wc-integration.php` | `build_begin_checkout_payload()`, `resolve_commerce_source()` (+ `$is_checkout`), `localize_commerce_data()` (Checkout-Zweig) |
| `public/js/wp-sdtrk-engine.js` | `seedWcCommerce()` — `else if (wc.beginCheckout)`-Zweig **vor** `addToCart` |
| `tests/test-wc-*.php` / `*.mjs` | Payload-, Resolver-/Localize- und Engine-Seed-Tests |
| `spec/00-overview.md`, `spec/07-woocommerce/*` | Spec auf neuen Ist-Zustand bringen |

---

## Task-Liste

### Phase 1 — Server-Datenpfad (Foundation)

#### Task 1: Mapper `cartLines()` + Payload-Builder `build_begin_checkout_payload()`
**Beschreibung:** Warenkorb → Engine-Datenquelle. `cartLines($cart)` iteriert `$cart->get_cart()` und liefert pro Position `{id,name,qty,price}` (id = `variation_id ?: product_id`, price = `line_total/qty`), exakt wie `lineItems()`. `build_begin_checkout_payload($cart)` summiert die Positionswerte zu `value` (gerundet via `wc_get_price_decimals()`) und liefert `['beginCheckout' => ['value','currency','items']]` — Shape identisch zu `build_add_to_cart_payload()`.

**Acceptance criteria:**
- [ ] `cartLines()` liefert `{id,name,qty,price}` je Position; id bevorzugt Variation, price = Stückpreis aus `line_total/qty`.
- [ ] `build_begin_checkout_payload()` liefert `beginCheckout.value` (String, gerundet), `.currency` (Shop-Währung), `.items` (volle Liste).

**Verification:**
- [ ] Neuer Test `tests/test-wc-begin-checkout-payload.php` grün: `php tests/test-wc-begin-checkout-payload.php` (Fake-Cart, mehrere Positionen, Wert = Summe `line_total`, String-Coercion der Felder).
- [ ] Bestehende Tests unverändert grün.

**Dependencies:** None
**Files:** `public/class-wp-sdtrk-wc-order-mapper.php`, `public/class-wp-sdtrk-wc-integration.php`, `tests/test-wc-begin-checkout-payload.php`
**Scope:** S

#### Task 2: Präzedenz + Localize-Zweig
**Beschreibung:** `resolve_commerce_source(bool $order_received, bool $is_checkout, bool $has_pending_atc, bool $is_product)` — neue Reihenfolge `order > beginCheckout > addToCart > viewItem`. In `localize_commerce_data()`: `$is_checkout = is_checkout() && WC()->cart && !WC()->cart->is_empty()` berechnen; neuer `case 'beginCheckout'`: bei vorhandenem ATC-Puffer diesen leeren (wie `order`-Zweig), dann `build_begin_checkout_payload(WC()->cart)` localisieren. (Hinweis: `is_checkout()` ist auch auf der Order-Received-Seite true — der Resolver gibt dort aber zuerst `order` zurück, daher kein Konflikt.)

**Acceptance criteria:**
- [ ] Resolver: bei `is_checkout` true und keinem `order` gewinnt `beginCheckout` — auch wenn `has_pending_atc` true ist.
- [ ] Localize-Zweig leert den ATC-Puffer, wenn er beim Checkout-Gewinn noch gefüllt war.
- [ ] Kein Event bei leerem Warenkorb.

**Verification:**
- [ ] `tests/test-wc-commerce-precedence.php` erweitert & grün: neue Signatur, `beginCheckout` schlägt `addToCart`, Puffer wird beim Checkout-Gewinn geleert.
- [ ] `php tests/test-wc-commerce-precedence.php` und alle übrigen `test-wc-*.php` grün.

**Dependencies:** Task 1
**Files:** `public/class-wp-sdtrk-wc-integration.php`, `tests/test-wc-commerce-precedence.php`
**Scope:** S

### Checkpoint: Server-Pfad
- [ ] Alle `php tests/test-wc-*.php` grün.
- [ ] Resolver-Reihenfolge & Puffer-Verwerfen verifiziert.

### Phase 2 — Browser-Seed

#### Task 3: Engine-Seed `beginCheckout`
**Beschreibung:** In `seedWcCommerce()` einen `else if (wc.beginCheckout)`-Zweig ergänzen, der `seedCommerceEvent('begin_checkout', wc.beginCheckout)` aufruft. Position in der `else-if`-Kette **vor** `addToCart` (Präzedenz `order > beginCheckout > addToCart > viewItem`). Kein Once-Guard (analog `view_item`). `seedCommerceEvent` bleibt unverändert (setzt Eventname/value/currency/items + items[0]-Fallback).

**Acceptance criteria:**
- [ ] `wc.beginCheckout` seedet ein `begin_checkout`-Event mit value/currency/items.
- [ ] Reihenfolge der Branches: order → beginCheckout → addToCart → viewItem.

**Verification:**
- [ ] `tests/test-wc-engine-seeding.mjs` erweitert & grün: `node tests/test-wc-engine-seeding.mjs` — beginCheckout-Branch seedet `begin_checkout`; beginCheckout schlägt einen gleichzeitig gesetzten addToCart-Key.

**Dependencies:** Task 2
**Files:** `public/js/wp-sdtrk-engine.js`, `tests/test-wc-engine-seeding.mjs`
**Scope:** S

### Phase 3 — Tracker-Verifikation

#### Task 4: `begin_checkout`-Mapping in allen Plattformen bestätigen
**Beschreibung:** Verifizieren (nicht annehmen), dass `begin_checkout` in allen drei Server-Trackern **und** drei Browser-Catchern korrekt gemappt ist: Meta/TikTok → `InitiateCheckout`, GA4 → natives `begin_checkout`-Passthrough. Falls eine Plattform den Fall nicht abdeckt, dort den `case 'begin_checkout'` ergänzen.

**Acceptance criteria:**
- [ ] `convert_eventname()` in Meta-, GA-, TikTok-Tracker **und** -Catcher behandelt `begin_checkout` (kein `default → false`).

**Verification:**
- [ ] Grep über `begin_checkout` in `public/class-wp-sdtrk-tracker-*.php` und `public/js/wp-sdtrk-{meta,ga,tt}.js` zeigt je einen Treffer.
- [ ] Manuell: keine Code-Änderung nötig erwartet — falls doch, Mini-Edit + Notiz.

**Dependencies:** None (parallel zu Phase 1–2 möglich)
**Files:** ggf. `public/class-wp-sdtrk-tracker-{ga,tt}.php`, `public/js/wp-sdtrk-{ga,tt}.js` (nur falls Lücke)
**Scope:** XS

### Phase 4 — Spec nachführen (Pflicht laut CLAUDE.md)

#### Task 5: Spec auf neuen Ist-Zustand bringen
**Beschreibung:** Die Spec dokumentiert nur den aktuellen Zustand (kein Changelog). Anzupassen:
- `spec/00-overview.md` — Feature-Bullet (Zeile ~48): InitiateCheckout (Checkout-Seite) ergänzen.
- `spec/07-woocommerce/README.md` — Intro + **Feuer-Modell**-Tabelle: neue Zeile „Checkout-Seite (`is_checkout()`)"; Dateien-Tabelle: Verweis auf neue Datei.
- `spec/07-woocommerce/view-item-and-add-to-cart.md` — alle Präzedenz-Angaben `order > addToCart > viewItem` → `order > beginCheckout > addToCart > viewItem`.
- `spec/07-woocommerce/order-mapping.md` — `cartLines()` neben `lineItems`/`productLine` dokumentieren.
- **Neu:** `spec/07-woocommerce/initiate-checkout.md` — Format gespiegelt von `purchase-tracking.md`/`view-item-and-add-to-cart.md` (Bereitstellung der Daten mit Feldtabelle, Engine-Ingestion, Browser+Server, value-Semantik, Re-Fire ohne Guard, Präzedenz/Puffer-Verwerfen).

**Acceptance criteria:**
- [ ] Feuer-Modell-Tabelle & Feature-Matrix nennen InitiateCheckout.
- [ ] Präzedenz-Reihenfolge in der Spec überall = `order > beginCheckout > addToCart > viewItem`.
- [ ] Neue `initiate-checkout.md` folgt dem bestehenden Schema und ist verlinkt.

**Verification:**
- [ ] Querverweise/Links der Sektion 07 konsistent (Datei existiert, README verlinkt sie).
- [ ] Keine „vorher/nachher"-Historie in der Spec.

**Dependencies:** Task 1–3 (Spec spiegelt finalen Code)
**Files:** `spec/00-overview.md`, `spec/07-woocommerce/README.md`, `spec/07-woocommerce/view-item-and-add-to-cart.md`, `spec/07-woocommerce/order-mapping.md`, `spec/07-woocommerce/initiate-checkout.md`
**Scope:** M

### Checkpoint: Komplett
- [ ] Alle `php tests/test-wc-*.php` + `node tests/test-wc-engine-seeding.mjs` grün.
- [ ] Spec spiegelt den Code (Präzedenz, Feuer-Modell, neue Datei).
- [ ] Bereit für Live-Smoke-Test (siehe Verifikation).

---

## Verifikation (End-to-End)
1. **Unit-Tests:** `php tests/test-wc-begin-checkout-payload.php`, `php tests/test-wc-commerce-precedence.php`, restliche `php tests/test-wc-*.php`, `node tests/test-wc-engine-seeding.mjs` — alle grün.
2. **Live-Smoke (Local-Site, `wc_integration` an):** Produkt in den Warenkorb → Checkout-Seite öffnen → im Browser-Netzwerk-Tab prüfen, dass `begin_checkout` browserseitig (Meta `fbq InitiateCheckout`, GA4 `begin_checkout`, TikTok `InitiateCheckout`) **und** serverseitig (`admin-ajax` `validateTracker`) mit korrektem `value`/`currency`/`items` feuert.
3. **Kollisions-Check:** Produkt hinzufügen und unmittelbar Checkout laden → es feuert `begin_checkout` (nicht `add_to_cart`); ATC-Puffer ist verworfen.
4. **Reload-Check:** Checkout neu laden → `begin_checkout` feuert erneut (kein Guard).

## Risiken & Mitigation
| Risiko | Impact | Mitigation |
|--------|:------:|------------|
| `is_checkout()` auch auf Order-Received true | Mittel | Resolver gibt `order` zuerst zurück; Reihenfolge im Test abgesichert |
| Verworfener ATC-Puffer = verlorenes AddToCart in seltener Kollision | Niedrig | Vom Nutzer bewusst gewählt; in Spec-Trade-offs dokumentiert |
| `value` inkl./exkl. Steuer/Versand missverstanden | Niedrig | Definiert als Summe `line_total` (Produkt, nach Rabatt, ohne Versand); in Spec dokumentiert |
| GA4/TikTok `begin_checkout`-Mapping evtl. doch lückenhaft | Niedrig | Task 4 verifiziert explizit per Grep, ergänzt nur bei Lücke |

## Hinweis zur Persistenz
Plan-Modus erlaubt aktuell nur das Schreiben dieser Plandatei. Nach Freigabe wird der Plan zusätzlich als `tasks/plan.md` und die Task-Liste als `tasks/todo.md` abgelegt (erster Implementierungsschritt).
