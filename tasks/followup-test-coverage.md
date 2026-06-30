# Follow-up — Test-Coverage-Lücken (ViewItem/AddToCart)

> Quelle: `/ship`-Fan-out (test-engineer-Persona) nach Merge der WC-ViewItem/AddToCart-Feature auf `main`. **Kein** Blocker für das Release — bewusst auf **nach den Live-Test + Tag** verschoben. Reihenfolge nach Risiko.
> DoD je Task: Test ergänzt, Suite grün (`php tests/*.php`, `node tests/*.mjs`), bei Code-Änderung Spec nachziehen ([CLAUDE.md](../CLAUDE.md)).

Legende: ☐ offen · ☑ erledigt

---

## ☑ F1 (HIGH) — Echte Engine-Branch-Coverage für die Seed-Zweige

> **Erledigt:** Seed-Zweige in `Wp_Sdtrk_Engine.seedWcCommerce()` + `seedCommerceEvent()` extrahiert (entfernt zugleich die Dreifach-Duplizierung). Neuer Test `test-wc-engine-seeding.mjs` lädt die **echte** Engine-Klasse (Stub-Scope für `window.localStorage` + `Wp_Sdtrk_Decrypter`) und ruft `seedWcCommerce()` real — deckt Präzedenz `order > addToCart > viewItem`, alle drei Seed-Zweige und den Order-`localStorage`-Once-Guard ab. Die zwei Mirror-Tests (`test-wc-view-item-seeding.mjs`, `test-wc-add-to-cart-seeding.mjs`) ersetzt/gelöscht.

**Problem:** `test-wc-view-item-seeding.mjs` und `test-wc-add-to-cart-seeding.mjs` **spiegeln** den Seed-Block (bauen ein `Wp_Sdtrk_Event` von Hand nach), statt `collect_eventData()` real auszuführen. Die echte `else if`-Kette (`order > addToCart > viewItem`), die per-Zweig-`set*`-Aufrufe und der `localStorage`-Once-Guard des Order-Zweigs sind nur durch Substring-Asserts (`engineSrc.includes(...)`, `indexOf(...) < indexOf(...)`) abgesichert → Drift möglich (gelöschter `setCurrency`, falscher `String(...)`-Cast, fehlender `Array.isArray`-Guard fällt nicht auf).

**Tun:**
- Neuer Test (z. B. `tests/test-wc-engine-collect.mjs`), der den **echten** Engine-Code lädt und `collect_eventData()` ausführt.
- Minimal-Shims im Test (analog zu vorhandenen mjs-Tests): `global.window` (mit `localStorage`-Stub: get/set/remove), `global.document` (location/title, `history.replaceState`), `global.jQuery` (nur falls von `collect_eventData` berührt — sonst weglassen), `global.wp_sdtrk_engine` (localizedData-Minimalobjekt), `global.wp_sdtrk_wc`.
- **Achtung Abhängigkeiten:** `collect_eventData()` instanziiert `Wp_Sdtrk_Helper`/`Wp_Sdtrk_Fp` und ruft viele Getter. Zwei Wege:
  1. Die Seed-Logik der drei `wp_sdtrk_wc`-Zweige in eine eigene Methode `seedCommerceEvent()` / `collectWcCommerce()` extrahieren (reiner Event-in/out, **ohne** Helper/Fp/DOM) und **diese** real testen. Saubere Variante, deckt zugleich F-Nit (DRY) ab. → Engine-Refactor + Spec-Notiz.
  2. Oder den ganzen Engine + Catcher-Globals in jsdom booten (schwerer, mehr Shims).
- **Empfehlung:** Weg (1) — extrahiert die Commerce-Seed-Zweige in eine testbare reine Methode, ersetzt die 3-fach-Duplizierung **und** macht die Präzedenz/`set*`-Verdrahtung echt testbar.

**Akzeptanz:**
- [ ] Realer Code (nicht gespiegelt) seedet bei `wp_sdtrk_wc.viewItem` → `view_item`, bei `.addToCart` → `add_to_cart`, bei `.order` → `purchase`.
- [ ] Präzedenz echt geprüft: gleichzeitig gesetzte Quellen → nur die höherwertige feuert (PHP lokalisiert zwar nur eine, der JS-`else if` muss sie trotzdem korrekt priorisieren).
- [ ] Order-`localStorage`-Once-Guard real abgedeckt (zweiter Aufruf seedet kein zweites Purchase).

---

## ☑ F2 (MEDIUM) — `capture_add_to_cart`-Kurzschlüsse

**Lücke:** Nur der `!$product`-Pfad ist getestet. `!is_active()` (Schalter aus) und `!WC()->session` (keine Session) sind ungeprüft — eine Regression, die das Gate hinter den Buffer-Write verschiebt, fiele nicht auf.

**Tun:** In `tests/test-wc-add-to-cart-capture.php` ergänzen:
- [ ] Schalter aus (`get_bool_option('wc_integration')` → false) → `capture_add_to_cart()` schreibt **nichts** in die Session.
- [ ] `WC()->session = null` → kein Fatal, kein Write.

---

## ☑ F3 (MEDIUM) — Nicht-Array-`wp_sdtrk_atc` auf der Consume-Seite

**Lücke:** `pending_add_to_cart()` hat einen `is_array(...) ? … : []`-Guard (Schutz vor korruptem Session-Wert eines Fremd-Plugins), aber kein Test deckt ihn ab.

**Tun:** In `tests/test-wc-commerce-precedence.php` (oder consume-Test): `wp_sdtrk_atc` mit einem Skalar-String seeden, `localize_commerce_data()` auf Nicht-Produkt-/Nicht-Order-Seite → Source `none`, kein Localize, keine PHP-Warning.

---

## ☑ F4 (MEDIUM) — Currency-Fallback `''` für view_item/add_to_cart

**Lücke:** Alle PHP-Tests stubben `get_woocommerce_currency()` → `'USD'`. Der Produktions-Fallback (`function_exists(...) ? … : ''`) der **neuen** Builder ist nie ausgeführt.

**Tun:** In einem Payload-Test `get_woocommerce_currency` **nicht** definieren → `build_view_item_payload`/`build_add_to_cart_payload` liefern `currency === ''` (catcher-seitiger EUR-Fallback greift dann). Achtung: eigener PHP-Prozess/Testdatei nötig, damit die Funktion wirklich undefiniert ist.

---

## ☑ F5 (LOW) — String-typisierte qty/price in der Buffer-Summe

**Lücke:** `build_add_to_cart_payload` castet `(float)` auf `qty`/`price`, Test füttert aber bereits Floats.

**Tun:** In `tests/test-wc-add-to-cart-payload.php` eine Position mit `['qty' => '2', 'price' => '10.5']` → `value` 21.0 (nach Rounding). Deckt die `(float)`-Koerzierung direkt ab.

---

> F1 ist der eigentliche Wert (echte Engine-Coverage + DRY). F2–F5 sind günstige PHP-Ergänzungen ohne Code-Änderung. Reihenfolge: F1 zuerst (ggf. mit Engine-Extraktion), dann F2–F5 gebündelt.
