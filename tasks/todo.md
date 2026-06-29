# Task-Liste — WooCommerce: ViewItem + AddToCart

> Reihenfolge nach [plan.md](plan.md). Jeder Task = ein vollständiger vertikaler Pfad. **Definition of Done je Task:** Code geändert **und** betroffene Spec auf Ist-Zustand gebracht (siehe [CLAUDE.md](../CLAUDE.md)).

Legende: ☐ offen · ☑ erledigt

---

## Phase 1 — ViewItem (Produktseiten)

### ☐ T1 — Single-Product-Mapper + ViewItem-Payload + Localize-Verallgemeinerung (PHP)
- **Tun:**
  - `Wp_Sdtrk_WC_Order_Mapper::productLine($product, int $qty = 1): array` ergänzen → ein `{id,name,qty,price}` (Einzelpreis = Anzeigepreis `wc_get_price_to_display`). Wird von ViewItem **und** AddToCart-Capture genutzt.
  - `Wp_Sdtrk_WC_Integration::build_view_item_payload($product): array` → `['viewItem' => ['prodId','name','value','currency','items'=>[productLine]]]`.
  - `localize_order_data()` → **`localize_commerce_data()`** verallgemeinern. Reihenfolge **order > addToCart > viewItem**; in dieser Phase nur `order` (bestehend) + `viewItem` (neu auf `is_product()`). Genau **eine** Quelle lokalisieren.
  - Hook-Registrierung in [includes/class-wp-sdtrk.php:266](../includes/class-wp-sdtrk.php#L266) auf neuen Methodennamen anpassen (Hook/Priorität unverändert: `wp_enqueue_scripts`, 20).
- **Abhängig von:** —
- **Dateien:** [public/class-wp-sdtrk-wc-order-mapper.php](../public/class-wp-sdtrk-wc-order-mapper.php), [public/class-wp-sdtrk-wc-integration.php](../public/class-wp-sdtrk-wc-integration.php), [includes/class-wp-sdtrk.php](../includes/class-wp-sdtrk.php) · Scope: S–M
- **Akzeptanz:**
  - [ ] Auf einer Produktseite wird `wp_sdtrk_wc.viewItem` mit `prodId/name/value/currency/items[1]` lokalisiert.
  - [ ] Order-Received-Pfad (`wp_sdtrk_wc.order`) bleibt unverändert.
  - [ ] Auf Nicht-Produkt-/Nicht-Order-Seiten wird `wp_sdtrk_wc` gar nicht lokalisiert.
- **Verifikation:**
  - [ ] DevTools/View-Source: Produktseite zeigt `var wp_sdtrk_wc = {"viewItem":…}`; Order-Received zeigt weiterhin `{"order":…}`.
  - [ ] Keine PHP-Notices (Produkt ohne Preis → `value` leer/`0`, kein Fatal).

### ☐ T2 — Engine seedet `view_item` + Spec
- **Tun:**
  - [public/js/wp-sdtrk-engine.js](../public/js/wp-sdtrk-engine.js) `collect_eventData()`: nach dem `.order`-Block einen `.viewItem`-Zweig (else-if, Präzedenz beachten) ergänzen → `setEventName({wc:'view_item'})`, `setValue`, `setCurrency`, `setItems`, `setProdId/Name` aus `items[0]`. **Kein** localStorage-Once-Guard.
  - Spec: [spec/07-woocommerce/README.md](../spec/07-woocommerce/README.md) (Feuermodell-Tabelle), neue Datei `spec/07-woocommerce/view-item-and-add-to-cart.md` (ViewItem-Teil) + Index-Verlinkung, [spec/03-browser-tracking/event-collection.md](../spec/03-browser-tracking/event-collection.md) bzw. engine-Beschreibung um den Seed-Zweig.
- **Abhängig von:** T1
- **Dateien:** `public/js/wp-sdtrk-engine.js`, 2–3 Spec-Dateien · Scope: S–M
- **Akzeptanz:**
  - [ ] Produktseite feuert **Meta ViewContent** + **GA4 view_item** im Browser, jeweils mit `value`+`currency`+`content_ids`/`items`.
  - [ ] Identisches Event geht serverseitig per `admin-ajax validateTracker` raus (S2S), gleiche `event_id`.
  - [ ] Spec beschreibt ViewItem exakt nach Ist-Zustand (kein Changelog-Stil).
- **Verifikation:**
  - [ ] DevTools Network: `fbevents` `ViewContent`, GA `view_item`; parallel `admin-ajax.php?action=…validateTracker` (meta/ga).
  - [ ] Browser-only-Catcher (Mautic/Funnelytics) tragen Shop-Währung + Position.

### ☑/☐ Checkpoint A — ViewItem end-to-end
- [ ] ViewItem feuert Browser **und** Server auf Produktseiten.
- [ ] Purchase + Nicht-WC-Seiten unverändert (Regression sichtprüfen).
- [ ] Spec der Sektion 07 konsistent. → **Review mit Mensch vor Phase 2.**

---

## Phase 2 — AddToCart (server-deferred)

### ☐ T3 — Capture `woocommerce_add_to_cart` → WC-Session (PHP)
- **Tun:**
  - `Wp_Sdtrk_WC_Integration::capture_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data): void` — `is_active()`-Gate; Produkt via `wc_get_product($variation_id ?: $product_id)`; `productLine($product, (int)$quantity)` an Session-Liste `wp_sdtrk_atc` anhängen (`WC()->session`-Existenz prüfen).
  - Hook-Registrierung in [includes/class-wp-sdtrk.php](../includes/class-wp-sdtrk.php): `woocommerce_add_to_cart` → `capture_add_to_cart`, accepted args **6**.
- **Abhängig von:** T1 (nutzt `productLine`)
- **Dateien:** `public/class-wp-sdtrk-wc-integration.php`, `includes/class-wp-sdtrk.php` · Scope: S
- **Akzeptanz:**
  - [ ] Add-to-Cart (AJAX-Archiv **und** Single-Product-Formular) schreibt einen Eintrag in `WC()->session['wp_sdtrk_atc']`.
  - [ ] Inaktiv, wenn `is_active()` false oder keine Session.
- **Verifikation:**
  - [ ] Debug-Ausgabe/Session-Inspektion nach Add zeigt korrekte `{id,name,qty,price}`.

### ☐ T4 — Consume + Engine seedet `add_to_cart` + Präzedenz + Spec
- **Tun:**
  - `localize_commerce_data()`: `addToCart`-Zweig zwischen `order` und `viewItem` einsortieren (Präzedenz **order > addToCart > viewItem**). Pending-Session → `build_add_to_cart_payload()` (`value`=Σ`price*qty`, `currency`, `items[]`) lokalisieren **und Session leeren** (Once-Guard).
  - [public/js/wp-sdtrk-engine.js](../public/js/wp-sdtrk-engine.js): `.addToCart`-Zweig (else-if vor `.viewItem`) → `setEventName({wc:'add_to_cart'})` + `value/currency/items` + `prodId/Name` aus `items[0]`. Kein localStorage-Guard nötig.
  - Spec: `view-item-and-add-to-cart.md` (AddToCart-Teil: server-deferred, Session, Präzedenz, Once-Guard, Multi-Item-Merge-Trade-off), README-Feuermodell + Trade-off-Liste, [activation.md](../spec/07-woocommerce/activation.md) (Hook-Tabelle: `woocommerce_add_to_cart` + Umbenennung `localize_commerce_data`), [order-mapping.md](../spec/07-woocommerce/order-mapping.md) (`productLine`).
- **Abhängig von:** T2, T3
- **Dateien:** `public/class-wp-sdtrk-wc-integration.php`, `public/js/wp-sdtrk-engine.js`, 3–4 Spec-Dateien · Scope: M
- **Akzeptanz:**
  - [ ] Nach Add feuert beim **nächsten** Seitenaufbau **Meta AddToCart** + **GA4 add_to_cart** (Browser + Server), `value`+`currency`+`items`.
  - [ ] Session wird beim Lokalisieren geleert → **Reload feuert AddToCart nicht erneut**.
  - [ ] Präzedenz: Seite mit pending Add **und** `is_product()` feuert **AddToCart**, nicht ViewContent.
  - [ ] Mehrere Adds vor einem Load → ein AddToCart mit allen Positionen in `items[]`.
- **Verifikation:**
  - [ ] DevTools: Add → Navigation → AddToCart Browser+Server; danach Reload → kein zweites AddToCart.
  - [ ] Single-Product-Formular-Add (Redirect zur Cart-Seite) feuert AddToCart auf der Cart-Seite.

### ☐ Checkpoint B — AddToCart end-to-end
- [ ] AddToCart feuert Browser **und** Server, deckt AJAX- **und** Formular-Adds ab.
- [ ] Reload feuert nicht erneut; Präzedenz korrekt.
- [ ] ViewItem/Purchase unverändert. → **Review mit Mensch vor Phase 3.**

---

## Phase 3 — Tests & Spec-Konsolidierung

### ☐ T5 — Automatisierte Tests
- **Tun:** (bestehende Harness-Muster spiegeln — [tests/](../tests/))
  - JS (`.mjs`): Engine-Seed-Fixtures — `wp_sdtrk_wc.viewItem` → Event `view_item` mit value/currency/items; `wp_sdtrk_wc.addToCart` → `add_to_cart`; Präzedenz `order > addToCart > viewItem`.
  - PHP: `productLine()`, `build_view_item_payload()`, `build_add_to_cart_payload()` (Σ-value), Session-Consume-leert-Flag.
  - Regression: Nicht-WC-Seite + Purchase unverändert ([tests/test-nowc-regression.mjs](../tests/test-nowc-regression.mjs), [tests/test-nowc-server-regression.php](../tests/test-nowc-server-regression.php) als Vorlage).
- **Abhängig von:** T2, T4
- **Dateien:** `tests/*` · Scope: M
- **Akzeptanz:** [ ] neue Tests grün · [ ] bestehende Tests grün.
- **Verifikation:** [ ] Test-Runner lokal ausführen.

### ☐ T6 — Spec-Konsolidierung & Findings
- **Tun:** [spec/07-woocommerce/README.md](../spec/07-woocommerce/README.md) Datei-/Klassentabellen + Feuermodell vollständig; [spec/00-overview.md](../spec/00-overview.md) Feature-Matrix; Querverweise/Indizes prüfen; in [spec/99-findings.md](../spec/99-findings.md) prüfen, ob „fehlende WC-Events" gelistet ist → falls ja, entfernen.
- **Abhängig von:** T2, T4
- **Dateien:** Spec · Scope: S
- **Akzeptanz:** [ ] alle relativen Links auflösbar · [ ] Spec spiegelt Code 1:1 · [ ] kein Changelog-Stil.

### ☐ Checkpoint C — Komplett
- [ ] Alle Akzeptanzkriterien erfüllt.
- [ ] Spec = Ist-Zustand, Tests grün.
- [ ] Bereit für Commit/Review (Live-Smoke-Test der Browser-Pfade wie bei den übrigen offenen WC-Punkten).
