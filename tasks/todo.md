# Task-Liste — Umsetzung TODO.md

Geordnet nach Abhängigkeit. Jeder Task: **Akzeptanzkriterien** (AK) + **Verifikation** (V). Spec-Nachführung ist Teil der AK (nie weglassen). Details/Begründung: [plan.md](plan.md).

Status-Legende: `[ ]` offen · `[~]` in Arbeit · `[x]` fertig

---

## Phase 0 — Meta-CAPI Dispatch-Bugfix 🔴

### [x] T0.1 — Klasse umbenennen + Alias
- **Tun:** In [public/class-wp-sdtrk-tracker-meta.php:3](../public/class-wp-sdtrk-tracker-meta.php#L3) Klasse `Wp_Sdtrk_Tracker_Fb` → `Wp_Sdtrk_Tracker_Meta` umbenennen; am Dateiende `class_alias('Wp_Sdtrk_Tracker_Meta', 'Wp_Sdtrk_Tracker_Fb');` für Abwärtskompatibilität ergänzen.
- **AK:** `class_exists('Wp_Sdtrk_Tracker_Meta') === true`; Dispatch in [class-wp-sdtrk-public-ajax.php:56](../public/class-wp-sdtrk-public-ajax.php#L56) findet die Klasse; keine Fatal Errors bei Plugin-Load.
- **V:** Browser-Page-Hit mit `type:'meta'` → DevTools-Antwort von `validateTracker` hat `state !== false`; `debug.log` zeigt CAPI-Request.

### [x] T0.2 — Spec nachführen (Bugfix)
- **Tun:** Befund [spec/99-findings.md](../spec/99-findings.md#meta-capi-dispatch) entfernen + Prioritätstabelle anpassen. Feature-Matrix [spec/00-overview.md:32](../spec/00-overview.md) (Meta Server-API ✅). [spec/02-server-tracking/README.md](../spec/02-server-tracking/README.md) Status-Tabelle, [platform-meta-capi.md](../spec/02-server-tracking/platform-meta-capi.md) und [ajax-pipeline.md](../spec/02-server-tracking/ajax-pipeline.md) (Klassenname). [directory-and-naming.md](../spec/01-architecture/directory-and-naming.md) + [bootstrap-and-loader.md](../spec/01-architecture/bootstrap-and-loader.md) auf neuen Klassennamen.
- **AK:** Kein „feuert nicht"/`Fb`-Dispatch-Hinweis mehr in der Spec; Klassenname konsistent `Wp_Sdtrk_Tracker_Meta` (Alias dokumentiert).
- **V:** `grep -ri "Wp_Sdtrk_Tracker_Fb\|feuert.*nicht\|Dispatch-Bug" spec/` ohne offene Treffer.

> **CHECKPOINT C0** — Review: Meta-Server-Event im Events Manager sichtbar, Spec sauber. Erst danach P1.

---

## Phase 1 — API-Integrationen prüfen & aktualisieren 🟡

### [x] T1.0 — Audit (read-only) → [api-audit.md](api-audit.md)
- **Tun:** Pro Integration aktuelle Anbieter-Doku prüfen (Endpoint/Version, Payload, Auth, Pflichtfelder, Deprecations). Befundliste nach `tasks/api-audit.md` schreiben mit Quellen-Links. Quellen-getrieben — keine Annahmen.
- **AK:** Audit-Dokument listet je Integration: Ist-Stand, aktueller Soll-Stand, Quelle, Handlungsbedarf (ja/nein).
- **V:** Dokument vorhanden; jede „ja"-Zeile hat einen Folge-Task unten.

### [x] T1.1 — Meta Graph-API-Version anheben (→ v23.0)
- **Tun:** `v11.0` in [class-wp-sdtrk-tracker-meta.php:57](../public/class-wp-sdtrk-tracker-meta.php#L57) auf aktuelle Version; Payload gegen aktuelle CAPI-Doku abgleichen.
- **AK:** Request geht an aktuelle Version; Test-Event akzeptiert (kein Deprecation-/Feldfehler).
- **V:** Meta Events Manager Test-Event-Code zeigt Event ohne Warnungen.

### [x] T1.2 — TikTok Events API 2.0 migrieren
- **Tun:** Endpoint `v1.2/pixel/track/` → `v1.3/event/track/`; Payload auf `event_source`/`event_source_id`/`data[]`/Unix-`event_time` umbauen ([class-wp-sdtrk-tracker-tt.php](../public/class-wp-sdtrk-tracker-tt.php)); `Access-Token`-Header beibehalten/prüfen.
- **AK:** Server-Event wird von TikTok akzeptiert (kein 40x); Event-Mapping unverändert korrekt.
- **V:** TikTok Events Manager Test-Event sichtbar.

### [x] T1.3 — GA4 MP verifizieren (ggf. anpassen) — aktuell, keine Änderung
- **Tun:** Endpoint/Params gegen aktuelle MP-Doku prüfen; nur bei Abweichung ändern.
- **AK:** Debug-Endpoint (`ga_trk_debug`) liefert `validationMessages: []`.
- **V:** GA4 DebugView zeigt Event.

### [x] T1.4 — Browser-Pixel-Snippets verifizieren
- **Tun:** Script-URLs/globale APIs für LinkedIn, Funnelytics, Mautic, Matomo gegen aktuelle Anbieter-Snippets prüfen ([public/js/wp-sdtrk-lin.js](../public/js/wp-sdtrk-lin.js), `-fl.js`, `-mtc.js`, `-mtm.js`); nur bei Abweichung anpassen.
- **Ergebnis:** LinkedIn/Mautic/Matomo (und Meta/GA4/TikTok-Browser) aktuell — keine Änderung. **Funnelytics** offen: `track-v3.js` vs. offizielle Basis `track.js` → Live-Verifikation nötig, in [TODO.md](../TODO.md) als verbleibender Punkt notiert (kein blinder Change).
- **V:** Audit dokumentiert in [api-audit.md](api-audit.md) (Abschnitt 4).

### [x] T1.5 — Spec nachführen (APIs)
- **Tun:** Plattformseiten unter [spec/02-server-tracking/](../spec/02-server-tracking/) (und [03](../spec/03-browser-tracking/) wo betroffen) auf neue Endpoints/Versionen/Payloads. Befund „API-Versionen fest verdrahtet" in [spec/99-findings.md](../spec/99-findings.md) anpassen/entfernen.
- **AK:** Keine veralteten Versionsangaben mehr in der Spec; Tabellen stimmen mit Code überein.
- **V:** Endpoint-Strings in Spec == Code.

> **CHECKPOINT C1** — Review: alle aktiven Server-Pfade verifiziert, Spec aktuell. Erst danach P2.

---

## Phase 2 — WooCommerce-Integration 🟢

### [ ] T2.0 — Designklärung (blockierend)
- **Tun:** Memo `tasks/wc-design.md`: genaue Hooks (`woocommerce_thankyou` + Order-Status-Handling für async Zahlungen), Mapping WC-Order → Event-Array-Schema, Consent-Zusammenspiel (Borlabs + Bypass-Metabox), Verhalten bei mehreren/asynchronen Zahlungen, Dedup-Strategie (`event_id` aus Order-ID).
- **AK:** Jede offene Designfrage aus TODO.md ist beantwortet und entschieden.
- **V:** Memo vorhanden; T2.1–T2.5 referenzieren es.

### [ ] T2.1 — WC-Erkennung + Redux-Switch
- **Tun:** `class_exists('WooCommerce')`-Gate; neue Redux-Sektion/Switch (`wc_integration`-o.ä.) in [admin/class-wp-sdtrk-admin.php](../admin/class-wp-sdtrk-admin.php), nur sichtbar wenn WC aktiv.
- **AK:** Ohne WC: Sektion unsichtbar/aus. Mit WC: Switch sichtbar, Zustand persistiert in `wp_sdtrk_options`.
- **V:** Admin-UI mit/ohne aktives WooCommerce geprüft; Option via `get_bool_option` lesbar.

### [ ] T2.2 — Order → kanonisches Event-Mapping
- **Tun:** Klasse `Wp_Sdtrk_WC_Order_Mapper` (in `load_dependencies()` registrieren), die ein WC-Order-Objekt in das von [Wp_Sdtrk_Tracker_Event](../public/class-wp-sdtrk-tracker-event.php) erwartete Array übersetzt: Produkte→content_ids/contents/items, Summe→value, Währung, Käuferdaten (E-Mail/Name), `event_id` aus Order-ID.
- **AK:** Mapper erzeugt für eine Testbestellung ein Array, aus dem `new Wp_Sdtrk_Tracker_Event($arr)` alle Getter korrekt befüllt.
- **V:** Debug-Dump des gemappten Events stimmt mit Bestellung überein.

### [ ] T2.3 — thankyou Browser-Pixel (Meta, Proof of path)
- **Tun:** Auf `woocommerce_thankyou` Purchase-Event über die bestehende Catcher-/Engine-Architektur für Meta auslösen, gespeist aus T2.2-Daten.
- **AK:** Order-Received-Seite feuert Meta-Browser-Purchase mit Produkten/Wert/Währung und Order-`eventID`.
- **V:** DevTools: `fbq('track','Purchase',…)` mit korrekten Daten + eventID.

### [ ] T2.4 — Browser-Pixel auf alle Plattformen
- **Tun:** T2.3 auf alle aktiven Plattformen (ga, tt, lin, fl, mtc, mtm) ausweiten.
- **AK:** Jede aktivierte Plattform feuert das Purchase/Conversion-Event auf der Thankyou-Seite.
- **V:** DevTools je Plattform geprüft.

### [ ] T2.5 — thankyou Server-APIs (consent-gated, dedup)
- **Tun:** Server-Tracker (Meta/GA4/TikTok) auf der Thankyou-Seite feuern, sofern aktiviert **und** Consent akzeptiert; gemeinsame `event_id` mit dem Browser-Event (Dedup).
- **AK:** Bei akzeptiertem Consent feuern Browser **und** Server mit identischer `event_id`; ohne Consent kein Server-Call (außer Bypass).
- **V:** Events Manager je Plattform zeigt 1 deduplizierten Purchase; `debug.log` zeigt Server-Requests.

### [ ] T2.6 — Spec nachführen (WooCommerce)
- **Tun:** Neue Sektion `spec/07-woocommerce/` mit `README.md`-Index + Querverweisen (Schema beibehalten). Feature-Matrix [spec/00-overview.md](../spec/00-overview.md) ergänzen. Ggf. Hinweis „kein WooCommerce" im Zweck-Abschnitt präzisieren.
- **AK:** Sektion beschreibt Ist-Zustand (Erkennung, Switch, Hooks, Mapping, Dedup); Index/Querverweise konsistent.
- **V:** Links auflösbar; Matrix == Code.

> **CHECKPOINT C2** — Review: Testbestellung trackt Browser+Server dedupliziert; Spec-Sektion steht. Erst danach P3.

---

## Phase 3 — Produkt-Feed 🟢

### [ ] T3.0 — Designklärung (blockierend)
- **Tun:** Memo `tasks/feed-design.md`: Feld-Mapping je Plattform (Meta/Google), Format(e) (CSV, ggf. XML), Speicherort & URL, Zugriffsschutz (öffentlich vs. Token), Aktualisierungsintervall, Umgang mit Varianten/Beständen/Preisen.
- **AK:** Alle offenen Designfragen aus TODO.md entschieden.
- **V:** Memo vorhanden; T3.1–T3.3 referenzieren es.

### [ ] T3.1 — Feed-Generator
- **Tun:** Generator: aktive WC-Produkte → Feed-Zeilen gemäß T3.0-Mapping. Nur aktiv wenn WC-Integration (T2.1) an.
- **AK:** Generator liefert für aktive Produkte valide Zeilen (Varianten/Preise/Bestand wie definiert).
- **V:** Generierter Feed gegen eine Testproduktmenge geprüft.

### [ ] T3.2 — Feed-URL + Zugriffsschutz
- **Tun:** Abrufbarer Endpoint (Rewrite-Route oder Query-Var) liefert den Feed; Zugriffsschutz gemäß T3.0.
- **AK:** URL liefert validen Feed; Schutz greift wie definiert; nur bei aktiver WC-Integration erreichbar.
- **V:** Abruf der URL liefert erwartetes Format; Format-Validator (Meta/Google) akzeptiert.

### [ ] T3.3 — Cron reaktivieren
- **Tun:** `WP_SDTRK_Cron::HOOKS` ([includes/class-wp-sdtrk-cron.php:21](../includes/class-wp-sdtrk-cron.php#L21)) mit Feed-Hook füllen; `register_cron_actions()` aktivieren; Intervall gemäß T3.0; Re-/Deaktivierung über bestehenden Activator/Deactivator prüfen.
- **AK:** Cron-Job ist nach Aktivierung geplant (`wp_get_scheduled_event`); Feed wird im Intervall neu generiert.
- **V:** WP-Cron-Liste zeigt Job; manueller Trigger regeneriert Feed.

### [ ] T3.4 — Spec nachführen (Feed + Cron)
- **Tun:** Feed in WC-Spec-Sektion dokumentieren. [spec/01-architecture/lifecycle.md](../spec/01-architecture/lifecycle.md) (Cron jetzt aktiv) und Cron-Befund in [spec/99-findings.md](../spec/99-findings.md) (leere Cron) auf neuen Ist-Zustand.
- **AK:** Spec beschreibt aktiven Cron + Feed; kein „Cron leer/no-op"-Befund mehr.
- **V:** Lifecycle/Findings == Code.

> **CHECKPOINT C3** — Review: Feed-URL valide, Cron läuft, Spec aktuell. Plan abgeschlossen.
</content>
