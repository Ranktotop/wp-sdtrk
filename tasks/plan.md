# Umsetzungsplan — WooCommerce-Tracking neu aufbauen

> Ist-Stand-Grundlage: Spec v1.7.6. Quelle der Wahrheit ist [`spec/`](../spec/README.md).
> **Pflicht je Aufgabe:** Code-Änderung ist erst fertig, wenn die betroffene Spec den neuen Ist-Zustand widerspiegelt (siehe [CLAUDE.md](../CLAUDE.md)). Die Spec ist **kein** Changelog — veraltete Beschreibungen ersetzen, nicht ergänzen.

## Ziel & Leitentscheidung

Die bestehende WC-Integration trackt Käufe über einen **eigenen Sonderweg**: Browser-Purchase per dediziertem Skript ([wp-sdtrk-wc.js](../public/js/wp-sdtrk-wc.js)), Server-Purchase über einen **Order-Status-Hook** (`woocommerce_order_status_processing/_completed`) mit Consent-Snapshot-AJAX und Idempotenz-Metas ([class-wp-sdtrk-wc-integration.php](../public/class-wp-sdtrk-wc-integration.php)).

Dieser Sonderweg hat zwei reale Bugs:
1. **Server feuert bei Sofort-Zahlung nie.** Der Status-Hook läuft beim Checkout, *bevor* die Danke-Seite den Consent-Snapshot per AJAX schreibt → fail-closed → kein Server-Call. Der Hook feuert nicht erneut.
2. **Keine Käuferdaten im Browser-Purchase.** Meta Advanced Matching wird nur beim `fbq('init', …)` aus dem (leeren) Page-Event gesetzt; das Purchase-Event reicht `em/fn/ln` nicht durch.

**Leitentscheidung:** Den Sonderweg ersatzlos zurückbauen. Stattdessen auf der Order-Received-Seite die Order-Daten als **zusätzliche Datenquelle** in die bestehende Engine-Mechanik ([collect_eventData](../public/js/wp-sdtrk-engine.js#L161)) einspeisen. Die Engine feuert dann Purchase **Browser und Server in einem Durchlauf** — exakt wie bei jeder Lead-/Value-Seite. Dedup läuft automatisch über die Order-ID (PHP `getEventId` → `getTransactionId`, JS `grabOrderId`).

**Akzeptierte Trade-offs** (mit dem Auftraggeber abgestimmt):
- Server-Feuerung hängt am Browser-Lauf (Tab zu / JS aus / AdBlocker → kein Server-Event). Gleiche Abhängigkeit wie auf jeder anderen Trackingseite.
- Bei Vorkasse/Rechnung wird der „Kauf" beim Erreichen der Danke-Seite gemeldet, auch wenn die Zahlung erst später eingeht. Gewünscht: „Kunde hat bestellt → feuern."

## Anforderungen

1. **Alle Produkte** des Warenkorbs tracken (Mehr-Produkt-`contents[]`/`items[]`), nicht nur die erste Position. Erfordert Erweiterung des heute single-product Event-Modells (JS + PHP) und aller kaufrelevanten Catcher (Meta/GA/TikTok, je Browser + Server).
2. **Währung** aus dem Shop liefern; `EUR` nur noch als Fallback (heute hartkodiert in [meta.js:262](../public/js/wp-sdtrk-meta.js#L262), [ga.js:326](../public/js/wp-sdtrk-ga.js#L326), [tracker-meta.php:149](../public/class-wp-sdtrk-tracker-meta.php#L149), [tracker-ga.php:153](../public/class-wp-sdtrk-tracker-ga.php#L153), [tracker-tt.php:148](../public/class-wp-sdtrk-tracker-tt.php#L148) und [tt.js]).
3. Bereitstellung der Daten ausschließlich über **ein** `wp_enqueue_scripts`/`wp_localize_script` auf der Order-Received-Seite. **Kein** Order-Status-Hook, kein Snapshot, keine Idempotenz-Metas.

---

## Architektur-Fixpunkte (verifiziert)

- **Engine-Pfad feuert beides:** [engine.js:236](../public/js/wp-sdtrk-engine.js#L236) `catchPageHit(2)` → pro Catcher `fireData` (Browser) **und** `sendData` (Server via `send_ajax` → `func=validateTracker`).
- **Event-Auto-Erkennung:** [event.js:397](../public/js/wp-sdtrk-event.js#L397) `parseEventName` liefert automatisch `purchase`, sobald `orderId` gesetzt ist.
- **Dedup über Order-ID:** JS `grabOrderId()` ([event.js:132](../public/js/wp-sdtrk-event.js#L132)) und PHP `getEventId()` ([class-wp-sdtrk-tracker-event.php:169](../public/class-wp-sdtrk-tracker-event.php#L169)) bevorzugen beide die Order-ID. Browser- und Server-Event teilen damit dieselbe `event_id`.
- **Datenquelle der Engine:** scalar-Felder aus GET-Params (`helper.get_Params(get_paramNames(...))`, gespeist aus `this.data`), einige Felder aus `wp_sdtrk_engine` (prodId/Quelle/IP/Agent). Injection-Punkt = [collect_eventData](../public/js/wp-sdtrk-engine.js#L161).
- **Server-Event-Transport:** `send_ajax` schickt das komplette JS-Event-Objekt; PHP baut daraus `new Wp_Sdtrk_Tracker_Event($data['event'])` ([class-wp-sdtrk-public-ajax.php:55](../public/class-wp-sdtrk-public-ajax.php#L55)). Ein neues `items`-Feld am JS-Event erreicht damit ohne weiteren Kanal die Server-Tracker.
- **Catcher-Konsum heute (single-product):** Meta `content_ids`/`contents` aus `grabProdId` ([meta.js:266](../public/js/wp-sdtrk-meta.js#L266)); GA `items:[{…}]` ([ga.js:329](../public/js/wp-sdtrk-ga.js#L329)); TT `contents` ([tt.js], PHP [tracker-tt.php:295](../public/class-wp-sdtrk-tracker-tt.php#L295)); GA-Server `getData_products` ([tracker-ga.php:342](../public/class-wp-sdtrk-tracker-ga.php#L342)); Meta-Server `contents`/`content_ids` ([tracker-meta.php:259](../public/class-wp-sdtrk-tracker-meta.php#L259)).
- **Loader:** Klassen via `require_once` in [includes/class-wp-sdtrk.php](../includes/class-wp-sdtrk.php). WC-Hooks aktuell [class-wp-sdtrk.php:269-273](../includes/class-wp-sdtrk.php#L269-L273).
- **Mapper:** `Wp_Sdtrk_WC_Order_Mapper::lineItems()` liefert bereits `[{id,name,qty,price}]` — Basis für die Mehr-Produkt-Payload.

---

## Abhängigkeitsgraph

```
P0  Rückbau Sonderweg ───────────────┐  (sauberer Ausgangszustand, Site stabil)
        ▼
P1  Shared Foundation ───────────────┤
   ├─ Event-Modell: items[] + currency (JS + PHP), abwärtskompatibel
   └─ Danke-Seiten-Datenquelle + Engine-Ingestion (collect_eventData)
        ▼
P2  Meta end-to-end ─────────────────┐  (Kritischer Pfad / Proof)
   (Browser Advanced Matching + alle Produkte + Shop-Währung; Server CAPI dito; event_id = Order-ID)
        ▼
P3  GA4 end-to-end ──────────────────┤
        ▼
P4  TikTok end-to-end ───────────────┤
        ▼
P5  Regression + Spec/Tests
```

**Reihenfolge-Begründung:** P1 ist die gemeinsame Basis (Event-Modell + Datenfluss), die alle Plattformen brauchen. P2 (Meta) ist der vom Auftraggeber genutzte kritische Pfad → zuerst end-to-end beweisen. GA/TT bauen identisch auf der Basis auf. Währung wird **je Plattform-Slice** miterledigt (sie ist quer, aber pro Catcher zu ändern); P5 sichert die Nicht-WC-Regression (EUR-Fallback unverändert).

---

## Phasen & Checkpoints

### Phase 0 — Rückbau des Sonderwegs
Ziel: Der alte WC-Purchase-Sonderweg ist vollständig entfernt; die Site läuft stabil ohne ihn (Käufe werden vorübergehend nicht getrackt — bewusster Zwischenzustand).
→ Tasks **T0.1–T0.2**.
**Checkpoint C0:** Order-Received-Seite lädt fehlerfrei, kein `wp-sdtrk-wc.js`, keine Order-Status-/Persist-Hooks mehr registriert; übrige Seiten unverändert. Feed/Cron unberührt.

### Phase 1 — Gemeinsame Basis
Ziel: Event-Modell trägt `items[]` + `currency` (mit sicheren Fallbacks, kein Verhaltenswechsel für bestehende Flows); die Danke-Seite stellt die Order-Daten bereit und die Engine übernimmt sie.
→ Tasks **T1.1–T1.2**.
**Checkpoint C1:** Auf der Order-Received-Seite trägt das Engine-Event (Console/Debug) `eventName=purchase`, `orderId`, Gesamtwert, `email/firstName/lastName`, `currency` und eine `items[]`-Liste aller Positionen. Bestehende Nicht-WC-Seiten feuern unverändert (kein `items`, EUR-Fallback).

### Phase 2 — Meta end-to-end (kritischer Pfad)
→ Tasks **T2.1–T2.2**.
**Checkpoint C2:** Testbestellung (Meta): Browser-Purchase mit Advanced Matching (em/fn/ln) **und** allen Produkten in `contents[]` + Shop-Währung; CAPI-Server-Purchase mit gehashten Userdaten, allen `contents[]`, Shop-Währung, `event_id` = Order-ID; Meta dedupliziert Browser/Server. Verifiziert auf dem HTTPS-Dev-Shop.

### Phase 3 — GA4 end-to-end
→ Task **T3.1**.
**Checkpoint C3:** `items[]` aller Produkte, Shop-Währung, `transaction_id` = Order-ID, Browser-gtag **und** Measurement Protocol.

### Phase 4 — TikTok end-to-end
→ Task **T4.1**.
**Checkpoint C4:** `contents[]` aller Produkte, Shop-Währung, `PlaceAnOrder`, Browser-Pixel **und** Events-API 2.0, `event_id` = `<Order-ID>_<hash>`.

### Phase 5 — Regression & Spec/Tests
→ Tasks **T5.1–T5.2**.
**Checkpoint C5:** Nicht-WC-Seiten (Lead/Value, View-Item) verhalten sich exakt wie vorher (EUR-Fallback, single-product). Spec-Sektion 07 + betroffene Datenmodell-/Browser-Tracking-Seiten + Befunde sind auf den neuen Ist-Zustand gebracht; Tests grün/angepasst.

---

## Übergreifende Verifikationsmittel

- **Browser-Debug-Log** je Catcher (`dbg`-Flag) → Console-Ausgaben „Fired in Browser (…)" / „Sent Data to Server (…)".
- **Test-Event-Codes:** Meta `meta_trk_server_debug_code`, TikTok `tt_trk_server_debug_code`; GA4 Debug via `ga_trk_debug`.
- **WP_DEBUG_LOG** → `sdtrk_log()` (Request/Response der Server-Calls).
- **DevTools Network:** `admin-ajax.php?func=validateTracker` Antwort `state`; Pixel-/CAPI-Requests an die Endpunkte.
- **Live-Shop:** `dev.eia-akademie.de` (HTTPS) für echte Testbestellungen.

---

## Risiken & offene Punkte

- **Reihenfolge des Localize:** Das WC-`wp_localize_script` muss **nach** der Engine-Registrierung an den Engine-Handle gehängt werden (sonst fehlt das Objekt zur Engine-Laufzeit). WC-Hook mit späterer Priorität als `enqueue_scripts`.
- **IP/User-Agent auf der Danke-Seite:** Es zählen die Live-Werte der Seite (konsistent mit dem „fire on thankyou"-Modell), nicht die auf der Order gespeicherten.
- **Backload:** Wird Consent erst nach Seitenaufbau erteilt, wird Purchase nicht nachgefeuert (gleiche Einschränkung wie für alle Events; keine Sonderlogik).
- **Mehr-Produkt-Wert:** Gesamtwert = `order->get_total()` (Scalar); `items[]` tragen Stückpreis × Menge. Konsistenz zwischen Summe und Positionssummen beachten (Versand/Steuer).
- Jede Phase schließt mit Spec-Nachführung; ohne sie gilt der Task als **nicht** abgeschlossen.
</content>
</invoke>
