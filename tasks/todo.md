# Task-Liste — WooCommerce-Tracking neu aufbauen

> Reihenfolge nach [plan.md](plan.md). Jeder Task = ein vollständiger vertikaler Pfad. **Definition of Done je Task:** Code geändert **und** betroffene Spec auf Ist-Zustand gebracht (siehe [CLAUDE.md](../CLAUDE.md)).

Legende: ☐ offen · ☑ erledigt

---

## Phase 0 — Rückbau des Sonderwegs

### ☐ T0.1 — Browser-Sonderweg entfernen
- **Tun:** [public/js/wp-sdtrk-wc.js](../public/js/wp-sdtrk-wc.js) löschen. In [class-wp-sdtrk-wc-integration.php](../public/class-wp-sdtrk-wc-integration.php) den Browser-Purchase-Pfad entfernen: `enqueue_purchase_assets` (alte Form), `build_browser_payload`. Loader-Registrierung [class-wp-sdtrk.php:269](../includes/class-wp-sdtrk.php#L269) für das WC-Skript bereinigen.
- **Abhängig von:** —
- **Akzeptanz:** Order-Received-Seite lädt ohne `wp-sdtrk-wc.js`; keine JS-Fehler; kein Purchase mehr über den alten Pfad.
- **Verifikation:** DevTools Network/Console auf der Order-Received-Seite.

### ☐ T0.2 — Server-Sonderweg + Order-Metas entfernen
- **Tun:** In [class-wp-sdtrk-wc-integration.php](../public/class-wp-sdtrk-wc-integration.php) entfernen: `on_order_paid`, `handle_persist_ajax`, `should_fire_server`, `server_platforms`. Loader-Hooks `woocommerce_order_status_processing/_completed` und `wp_ajax(_nopriv)_wp_sdtrk_wc_persist` ([class-wp-sdtrk.php:270-273](../includes/class-wp-sdtrk.php#L270-L273)) abmelden. Order-Meta-Nutzung (`_wp_sdtrk_consent`, `_wp_sdtrk_ids`, `_wp_sdtrk_server_sent_*`, `_wp_sdtrk_bypass`) entfernen. `Wp_Sdtrk_WC_Order_Mapper` bleibt (für die Datenquelle).
- **Abhängig von:** —
- **Akzeptanz:** Keine WC-Server-/Persist-Hooks mehr registriert; kein PHP-Fehler bei Statuswechsel; Feed/Cron unberührt.
- **Verifikation:** Testbestellung durchführen → keine `_wp_sdtrk_*`-Metas geschrieben; `debug.log` ohne WC-Server-Calls.

**⇒ Checkpoint C0** (Site stabil ohne Sonderweg; Käufe bewusst vorübergehend ungetrackt).

---

## Phase 1 — Gemeinsame Basis

### ☐ T1.1 — Event-Modell: `items[]` + `currency` (JS + PHP)
- **Tun:**
  - JS [event.js](../public/js/wp-sdtrk-event.js): `setItems/getItems` (Default `[]`), `setCurrency/getCurrency` (Default `""`).
  - PHP [class-wp-sdtrk-tracker-event.php](../public/class-wp-sdtrk-tracker-event.php): `getItems()` (aus `eventData['items']`, sonst `[]`), `getCurrency()` (aus `eventData['currency']`, **Fallback `'EUR'`**).
- **Abhängig von:** —
- **Akzeptanz:** Beide Getter existieren mit sicheren Defaults; **kein** Verhaltenswechsel für bestehende Flows (leere `items` ⇒ Catcher nutzen weiter den single-product-Pfad; fehlende currency ⇒ `EUR`).
- **Verifikation:** Bestehende Lead-/Value-Seite feuert byte-gleich wie zuvor (Console-Log-Vergleich).

### ☐ T1.2 — Danke-Seiten-Datenquelle + Engine-Ingestion
- **Tun:**
  - WC-Integration: auf `wp_enqueue_scripts` (Priorität **nach** Engine-Registrierung) auf der Order-Received-Seite ein `wp_localize_script` an den Engine-Handle hängen: Objekt `wp_sdtrk_wc.order` mit `orderId`, `value` (= `get_total()`), `currency` (= `get_currency()`), `email/firstName/lastName` (Billing), `items` (= `lineItems()`), optional `source/ip/agent`.
  - [engine.js `collect_eventData`](../public/js/wp-sdtrk-engine.js#L161): nach der Param-Sammlung, falls `wp_sdtrk_wc.order` vorhanden, Event-Felder seeden — `setOrderId({wc:…})`, `setValue({wc:…})`, `setEventName({wc:'purchase'})`, `setUserEmail/FirstName/LastName({wc:…})`, `setCurrency(…)`, `setItems(…)`, sowie `setProdId/setProdName` aus der **ersten** Position (Abwärtskompat-Fallback der single-product-Getter).
- **Abhängig von:** T1.1
- **Akzeptanz:** Auf der Order-Received-Seite trägt das Engine-Event `eventName=purchase`, korrekte `orderId/value/currency`, Käuferdaten und `items[]` aller Positionen. Außerhalb der Danke-Seite kein Effekt.
- **Verifikation:** `window.wp_sdtrk_engine_class.get_event()` in der Console inspizieren.

**⇒ Checkpoint C1.**

---

## Phase 2 — Meta end-to-end (kritischer Pfad)

### ☐ T2.1 — Meta Browser: Advanced Matching + alle Produkte + Währung
- **Tun:** [meta.js](../public/js/wp-sdtrk-meta.js): `get_data_custom` baut `content_ids`/`contents` aus **allen** `getItems()` (Fallback: single-product aus `grabProdId`, wenn `items` leer); `currency` aus `event.getCurrency()` statt hartkodiert `"EUR"` ([meta.js:262](../public/js/wp-sdtrk-meta.js#L262)). Advanced Matching für den Purchase sicherstellen: Userdaten (em/fn/ln) müssen für das Purchase-Event an Meta gehen (Pixel-Init bzw. Re-Init mit `get_data_user()` aus dem WC-getragenen Event).
- **Abhängig von:** T1.2
- **Akzeptanz:** Browser-Purchase enthält alle gekauften Produkte und die Shop-Währung; Advanced Matching (em/fn/ln) wird gesendet.
- **Verifikation:** Meta Pixel Helper / Events Manager (Test-Event); Console-Log.

### ☐ T2.2 — Meta Server (CAPI): alle Produkte + Währung + Userdaten
- **Tun:** [tracker-meta.php](../public/class-wp-sdtrk-tracker-meta.php): `getData_base`/`getData_custom` bauen `contents[]`/`content_ids` aus `getItems()` (Fallback single-product); `currency` aus `getCurrency()` statt `"EUR"` ([tracker-meta.php:149](../public/class-wp-sdtrk-tracker-meta.php#L149)). `getData_user` (em/fn/ln-Hashes) ist bereits vorhanden — Fluss über die neue Datenquelle bestätigen.
- **Abhängig von:** T1.2
- **Akzeptanz:** CAPI-Purchase mit allen `contents[]`, Shop-Währung, gehashten Userdaten, `event_id` = Order-ID.
- **Verifikation:** Meta Events Manager (Test-Event-Code); `debug.log` Payload.

**⇒ Checkpoint C2** (live auf HTTPS-Dev-Shop).

---

## Phase 3 — GA4 end-to-end

### ☐ T3.1 — GA4 Browser + Measurement Protocol: alle Produkte + Währung
- **Tun:** [ga.js](../public/js/wp-sdtrk-ga.js) `get_data_custom`: `items[]` aus **allen** `getItems()` (Fallback single-product, [ga.js:329](../public/js/wp-sdtrk-ga.js#L329)); `currency` aus `getCurrency()` ([ga.js:326](../public/js/wp-sdtrk-ga.js#L326)). [tracker-ga.php](../public/class-wp-sdtrk-tracker-ga.php) `getData_products`: Array aus allen Items; `currency` aus `getCurrency()` ([tracker-ga.php:153](../public/class-wp-sdtrk-tracker-ga.php#L153)).
- **Abhängig von:** T1.2
- **Akzeptanz:** GA4-Purchase mit `items[]` aller Produkte, Shop-Währung, `transaction_id` = Order-ID — Browser **und** MP.
- **Verifikation:** GA4 DebugView; `debug.log`.

**⇒ Checkpoint C3.**

---

## Phase 4 — TikTok end-to-end

### ☐ T4.1 — TikTok Browser + Events-API 2.0: alle Produkte + Währung
- **Tun:** [tt.js](../public/js/wp-sdtrk-tt.js) und [tracker-tt.php](../public/class-wp-sdtrk-tracker-tt.php) `getData_contents`: `contents[]` aus **allen** `getItems()` (Fallback single-product, [tracker-tt.php:295](../public/class-wp-sdtrk-tracker-tt.php#L295)); `currency` aus `getCurrency()` statt `"EUR"` ([tracker-tt.php:148](../public/class-wp-sdtrk-tracker-tt.php#L148)).
- **Abhängig von:** T1.2
- **Akzeptanz:** `PlaceAnOrder` mit `contents[]` aller Produkte, Shop-Währung, `event_id` = `<Order-ID>_<hash>` — Browser **und** Server.
- **Verifikation:** TikTok Events Manager (Test-Event-Code); `debug.log`.

**⇒ Checkpoint C4.**

---

## Phase 5 — Regression & Spec/Tests

### ☐ T5.1 — Nicht-WC-Regression
- **Tun:** Bestehende Flows gegenprüfen: Lead-/Value-Seite, View-Item (single product), Scroll/Time/Click/Visibility. Sicherstellen: leere `items` ⇒ single-product-Pfad unverändert; fehlende currency ⇒ `EUR`.
- **Abhängig von:** T2.x, T3.1, T4.1
- **Akzeptanz:** Kein Verhaltenswechsel gegenüber vor dem Umbau.
- **Verifikation:** Console-Log-Vergleich je Catcher; Test-Events.

### ☐ T5.2 — Spec + Tests nachführen
- **Tun:** Spec-Sektion [07-woocommerce](../spec/07-woocommerce/README.md) neu fassen: Order-Status-Server-Pfad, Consent-Snapshot, Idempotenz, `wp-sdtrk-wc.js` entfernen; das Danke-Seiten-Injection-Modell + Mehr-Produkt + Shop-Währung dokumentieren. [order-mapping.md](../spec/07-woocommerce/order-mapping.md) auf die neue Datenquellen-Rolle bringen; `server-purchase.md`/`browser-purchase.md` zusammenführen/ersetzen. Datenmodell ([05](../spec/05-data-model/README.md)) und Browser-Tracking ([03](../spec/03-browser-tracking/README.md), `event-collection`/`engine-and-lifecycle`) um `items[]`/`currency` ergänzen. [99-findings.md](../spec/99-findings.md): Währungs-Hardcode-Befund + „kein Thankyou-Besuch"-Einschränkung entfernen. Tests: `test-wc-server-decision.php` entfernen, `test-wc-order-mapper.php` anpassen, `test-wc-integration-gate.php`/`test-wc-feed.php` prüfen.
- **Abhängig von:** alle vorherigen
- **Akzeptanz:** Spec spiegelt den Ist-Zustand exakt; keine Verweise mehr auf den Sonderweg; Tests grün/aktuell.
- **Verifikation:** Spec-Quervergleich gegen Code; Testlauf.

**⇒ Checkpoint C5.**
</content>
