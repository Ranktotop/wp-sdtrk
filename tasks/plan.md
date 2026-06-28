# Umsetzungsplan — offene Aufgaben aus TODO.md

> Ist-Stand-Grundlage: Spec v1.7.6. Quelle der Wahrheit ist [`spec/`](../spec/README.md).
> **Pflicht je Aufgabe:** Code-Änderung ist erst fertig, wenn die betroffene Spec den neuen Ist-Zustand widerspiegelt (siehe [CLAUDE.md](../CLAUDE.md)). Die Spec ist **kein** Changelog — veraltete Beschreibungen ersetzen, nicht ergänzen.

Dieser Plan deckt alle vier TODO-Punkte ab, in vier Phasen mit Checkpoints. Arbeitsweise: **vertikale Slices** (ein vollständiger Pfad pro Task), nicht horizontale Schichten.

---

## Scope

| # | Aufgabe | Typ | Phase |
|---|---------|-----|-------|
| 1 | Meta-CAPI Dispatch-Bug (`Fb` vs. `Meta`) | 🔴 Bugfix | P0 |
| 2 | Externe API-Integrationen prüfen & aktualisieren | 🟡 Wartung | P1 |
| 3 | WooCommerce-Integration | 🟢 Feature | P2 |
| 4 | Produkt-Feed (nur mit aktiver WooCommerce-Integration) | 🟢 Feature | P3 |

---

## Abhängigkeitsgraph

```
P0  Meta-CAPI-Bugfix ─────────────┐
        │ (Meta-Server-Pfad muss feuern,                 
        │  damit P1-Meta & P2-Server testbar sind)        
        ▼                                                 
P1  API-Audit & Updates ──────────┤
   (Meta v11→aktuell, TikTok v1.2→Events 2.0, GA4 verify, Browser-Pixel verify)
        │                                                 
        ▼                                                 
P2  WooCommerce-Integration ──────┐ (baut auf funktionierender Browser+Server-Pipeline auf)
   ├─ WC-Erkennung + Settings-Switch                      
   ├─ Order → kanonisches Event-Mapping                   
   ├─ thankyou: Browser-Pixel                             
   └─ thankyou: Server-APIs (consent-gated, dedup)        
        │                                                 
        ▼                                                 
P3  Produkt-Feed (benötigt P2 WooCommerce-Datenschicht + Cron-Reaktivierung)
```

**Kritische Reihenfolge-Begründung:**
- **P0 vor P1-Meta:** Solange die Meta-CAPI serverseitig nicht feuert, lässt sich ein Versions-Update (v11→aktuell) nicht real verifizieren. Bugfix zuerst.
- **P0/P1 vor P2:** WooCommerce renutzt exakt die Tracker/Event-Architektur. Erst wenn alle Server-Pfade nachweislich feuern, lohnt der WC-Aufsatz.
- **P2 vor P3:** Der Feed liest aus der WooCommerce-Produktschicht — ohne aktive WC-Integration kein Feed.

---

## Architektur-Fixpunkte (verifiziert)

- **Dispatch:** [public/class-wp-sdtrk-public-ajax.php:56](../public/class-wp-sdtrk-public-ajax.php#L56) bildet `Wp_Sdtrk_Tracker_` + `ucfirst(type)`. Klasse heißt aber `Wp_Sdtrk_Tracker_Fb` ([public/class-wp-sdtrk-tracker-meta.php:3](../public/class-wp-sdtrk-tracker-meta.php#L3)). `Wp_Sdtrk_Tracker_Fb` wird **nirgends sonst im Code** referenziert (nur Datei selbst + Spec) → Umbenennung ist kollisionsfrei.
- **Loader:** Klassen werden per `require_once` in [includes/class-wp-sdtrk.php](../includes/class-wp-sdtrk.php) (`load_dependencies()`) geladen — kein PSR-4-Autoload aktiv. Neue Klassen dort registrieren.
- **Event-Modell:** `Wp_Sdtrk_Tracker_Event` ([public/class-wp-sdtrk-tracker-event.php](../public/class-wp-sdtrk-tracker-event.php)) wird aus einem assoziativen Array gebaut. Getter: `getProductId/Name`, `getEventValue`, `getEventName`, `getUserEmail/FirstName/LastName`, `getUtmData`, `getEventId/TransactionId`, `getEventIp/Agent/Source`. → WC-Mapping muss genau dieses Array-Schema erzeugen.
- **API-Endpoints (Ist):** Meta `graph.facebook.com/v11.0/{pixel}/events`; TikTok `business-api.tiktok.com/open_api/v1.2/pixel/track/`; GA4 `google-analytics.com/mp/collect`.
- **HTTP-Layer:** `WP_SDTRK_Helper_Event::do_post($url,$payload,$headers,$debug)` ([includes/helpers/class-wp-sdtrk-helper-event.php](../includes/helpers/class-wp-sdtrk-helper-event.php)) — zentral, cURL, JSON-Validierung, `sdtrk_log()`.
- **Cron:** `WP_SDTRK_Cron::HOOKS = []` ([includes/class-wp-sdtrk-cron.php:21](../includes/class-wp-sdtrk-cron.php#L21)) → no-op. Aktivierung/Deaktivierung bereits verdrahtet (Activator/Deactivator). `register_cron_actions()` auskommentiert.
- **Redux-Settings:** zentral in [admin/class-wp-sdtrk-admin.php](../admin/class-wp-sdtrk-admin.php) (`wp_sdtrk_register_redux_options()`). Bedingte Sichtbarkeit via `required`-Regeln. Metabox dort ebenfalls.
- **Consent:** JS `helper.has_consent(id, service, event)` ([public/js/wp-sdtrk-helper.js](../public/js/wp-sdtrk-helper.js)) — Borlabs v2/v3; Bypass via Metabox `wp_sdtrk_bypass_consent`.

---

## Phase 0 — Meta-CAPI Dispatch-Bugfix 🔴

**Ziel:** Die Meta Conversions API feuert serverseitig.
**Entscheidung:** Klasse umbenennen `Wp_Sdtrk_Tracker_Fb` → `Wp_Sdtrk_Tracker_Meta` **plus** `class_alias('Wp_Sdtrk_Tracker_Meta', 'Wp_Sdtrk_Tracker_Fb')` für Abwärtskompatibilität. (Bevorzugt gegenüber Catcher-`type:'fb'`, weil Dateiname `…-meta.php` und Optionspräfix `meta_*` schon „meta" sind — konsistenteste Lösung.)

→ Siehe Tasks **T0.1–T0.2** in [todo.md](todo.md).

**Checkpoint C0:** Meta-Server-Event erscheint im Meta Events Manager (Test-Event-Code); `validateTracker` liefert für `type:'meta'` `state !== false`. GA4/TikTok unverändert lauffähig. Spec-Befund entfernt.

---

## Phase 1 — Externe API-Integrationen prüfen & aktualisieren 🟡

**Ziel:** Jede Integration gegen den aktuellen Anbieter-Vertrag prüfen; nur wo nötig aktualisieren. Quellen-getrieben (offizielle Doku zitieren), nichts aus dem Gedächtnis ändern.

**Erwartete Befunde (zu verifizieren, nicht blind anwenden):**
- **Meta:** Graph-API `v11.0` veraltet → aktuelle Version. Payload weitgehend stabil; `action_source`/`event_source_url`/`user_data`-Pflichtfelder gegen aktuelle Doku prüfen.
- **TikTok:** `open_api/v1.2/pixel/track/` → **Events API 2.0** (`v1.3/event/track/`) mit geänderter Payload-Struktur (`event_source`, `event_source_id`, `data[]`, Unix-`event_time`). Das ist der größte Eingriff.
- **GA4:** Measurement Protocol stabil → primär Verifikation.
- **Browser-Pixel (LinkedIn, Funnelytics, Mautic, Matomo):** Snippet-/Script-URLs & globale APIs gegen aktuelle Anbieter-Snippets prüfen.

→ Tasks **T1.0–T1.5**. T1.0 ist ein reiner Audit-Task (read-only, erzeugt eine Befundliste), die folgenden Tasks setzen nur die tatsächlich nötigen Updates um.

**Checkpoint C1:** Audit-Dokument liegt vor; jede umgesetzte Änderung per Test-Event/Debug-Endpoint verifiziert; Spec-Plattformseiten + Befund „Veraltete API-Versionen" nachgeführt.

---

## Phase 2 — WooCommerce-Integration 🟢

**Vorab-Designklärung (Task T2.0, blockierend):** Hooks, Daten-Mapping, Consent-Zusammenspiel, asynchrone Zahlungen. Ergebnis = kurzes Design-Memo, das die folgenden Tasks fixiert.

**Vertikale Slices (jeder Task = ein vollständiger Pfad):**
- **T2.1** WC-Erkennung + Redux-Switch (nur sichtbar/aktiv wenn WC aktiv).
- **T2.2** WC-Order → kanonisches Event-Array (eine `Wp_Sdtrk_WC_Order_Mapper`-Klasse, die exakt das von `Wp_Sdtrk_Tracker_Event` erwartete Schema liefert; gemeinsame `event_id` aus Order).
- **T2.3** `woocommerce_thankyou`: **Browser-Pixel** (Purchase) end-to-end für **eine** Plattform (Meta) — Proof of path.
- **T2.4** Browser-Pixel auf alle aktiven Plattformen ausweiten.
- **T2.5** `woocommerce_thankyou`: **Server-APIs** (CAPI/MP/Events) — consent-gated, Dedup über gemeinsame `event_id`.

**Checkpoint C2:** Testbestellung auf Order-Received-Seite löst Browser-Pixel **und** Server-APIs aus (bei akzeptiertem Consent), mit Produkten/Wert/Währung; Plattformen erkennen Dedup (kein Doppelzählen). Neue Spec-Sektion `spec/07-woocommerce/` + Feature-Matrix [00](../spec/00-overview.md) ergänzt.

---

## Phase 3 — Produkt-Feed 🟢

**Vorab-Designklärung (Task T3.0, blockierend):** Feld-Mapping je Plattform, Format(e) (CSV, ggf. XML), Speicherort/URL & Zugriffsschutz, Intervall, Varianten/Bestände/Preise.

**Vertikale Slices:**
- **T3.1** Feed-Generator: aktive WC-Produkte → kanonische Feed-Zeilen.
- **T3.2** Abrufbare Feed-URL (Endpoint) + Zugriffsschutz; gekoppelt an aktive WC-Integration.
- **T3.3** Cron reaktivieren: `WP_SDTRK_Cron::HOOKS` füllen, `register_cron_actions()` aktivieren, periodische Feed-Aktualisierung.

**Checkpoint C3:** Feed-URL liefert validen Feed der aktiven Produkte; Cron aktualisiert im konfigurierten Intervall; in Meta Commerce Manager / Google Merchant Center einlesbar (Format-Validierung). Spec (WC-Sektion + [lifecycle.md](../spec/01-architecture/lifecycle.md) + Cron-Befund in [99](../spec/99-findings.md)) nachgeführt.

---

## Übergreifende Verifikationsmittel

- **WP_DEBUG/WP_DEBUG_LOG** an → `wp-content/debug.log` zeigt `sdtrk_log()`-Ausgaben (Request/Response).
- **Test-Event-Codes:** Meta `meta_trk_server_debug_code`, TikTok `tt_trk_server_debug_code`; GA4 Debug-Endpoint `ga_trk_debug`.
- **Browser:** DevTools Network → `admin-ajax.php` (`func=validateTracker`) Antwort `state`/`debug`; Pixel-Requests an die jeweiligen Endpunkte.
- Kein automatisiertes Test-Setup im Repo vorhanden → Verifikation manuell über die genannten Debug-Pfade.

---

## Risiken & offene Punkte

- **TikTok Events 2.0** ist ein struktureller Payload-Umbau, nicht nur ein Versionssprung — eigener Verifikationsaufwand.
- **Consent bei WC-Thankyou:** Zusammenspiel von Borlabs-Consent und Server-Feuerung muss explizit definiert werden (T2.0).
- **Asynchrone Zahlungen** (Vorkasse/Überweisung): `woocommerce_thankyou` feuert ggf. vor `processing`/`completed` → Status-Handling klären (T2.0).
- **Feed-Zugriffsschutz:** öffentliche Feed-URL vs. Token — in T3.0 entscheiden.
- Jede Phase schließt mit Spec-Nachführung; ohne sie gilt der Task als **nicht** abgeschlossen.
</content>
