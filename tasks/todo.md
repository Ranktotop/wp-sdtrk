# TODO: ChatGPT Ads (OpenAI) Integration

Details, Akzeptanzkriterien und Verifikation stehen in [plan.md](plan.md).

## Vorab
- [ ] Offene Fragen 1–5 aus plan.md mit Marc klären

## Phase 1: Browser-Pixel
- [x] T1: Pixel lädt und misst `page_viewed` (Admin-Browser-Felder, registerScript, Engine, Catcher, Backload) (M)
- [x] T2: Conversion-Events mit Minor-Unit-Beträgen und `contents[]` (Browser) (S)
- [x] T3: Signal-Events als `custom` mit validiertem `custom_event_name` (Browser) (S)
- [ ] **Checkpoint A:** JS-Tests grün, Smoke-Test mit Pixel `4J4brGr1XUDTEKZhUQjeaa`, Review

## Phase 2: Conversions API
- [x] T4: `Wp_Sdtrk_Tracker_Oai`, Page und Events, Bearer-Auth, Admin-Server-Felder, Bootstrap (M)
- [x] T5: `oppref`-Erfassung (eigenes Cookie), `obref`, `user` (email_sha256, ip, ua) (M)
- [x] T6: Server-Signal-Events und Dedupe-Parität Browser ↔ Server (S)
- [x] T7: Fehlerauswertung (reale Antwort erfassen) und `validate_only`-Schalter (S)
- [ ] **Checkpoint B:** alle Tests grün, Hybrid-Dedupe lokal geprüft, Review

## Phase 3: Abschluss
- [x] T8: WooCommerce-Pfad per Tests absichern (order_created / checkout_started / items_added / contents_viewed) (S)
- [x] T9: Spec nachführen (neue `platform-openai.md` und alle Querverweise) (M)
- [ ] T10: i18n (.pot/.po/.mo), Version 1.16.0, Changelog (S)
- [ ] **Checkpoint C:** Release-Zip gebaut, Live-Smoke-Test im Ads Manager, Freigabe für Tag
