# Umsetzungsplan — WooCommerce: ViewItem + AddToCart ergänzen

> Ist-Stand-Grundlage: [`spec/`](../spec/README.md) ist die Quelle der Wahrheit.
> **Pflicht je Aufgabe:** Eine Code-Änderung ist erst fertig, wenn die betroffene Spec den neuen Ist-Zustand widerspiegelt (siehe [CLAUDE.md](../CLAUDE.md)). Die Spec ist **kein** Changelog — veraltete Beschreibungen ersetzen, nicht ergänzen.

## Überblick

Die WooCommerce-Integration trackt heute nur **Purchase** (Order-Received-Seite). Es fehlen zwei Funnel-Events:

- **ViewItem** — beim Aufruf einer Produkt-Detailseite (`is_product()`).
- **AddToCart** — beim Hinzufügen eines Produkts in den Warenkorb.

Beide Events sind in der gesamten Catcher-/Tracker-Schicht **bereits vollständig verdrahtet**: Browser (`convert_eventname`: `view_item`→`ViewContent`, `add_to_cart`→`AddToCart`, je Catcher) **und** Server (`Wp_Sdtrk_Tracker_*::convert_eventname`, [class-wp-sdtrk-tracker-meta.php:379-386](../public/class-wp-sdtrk-tracker-meta.php#L379)). Es ist **keine** Änderung an Catchern oder Server-Trackern nötig. Die Arbeit besteht ausschließlich darin, die **Produkt- bzw. Warenkorb-Daten in die Engine einzuspeisen** — exakt nach dem bestehenden Purchase-Muster ([07 › Purchase-Tracking](../spec/07-woocommerce/purchase-tracking.md)).

## Architektur-Entscheidungen

- **Ein geseedetes Event pro Seitenaufbau (bestehendes Modell).** Die Engine baut **ein** Event und feuert es über `catchPageHit(2) → catchEventHit(2)` browser- **und** serverseitig in einem Durchlauf. ViewItem/AddToCart werden — wie Purchase — als dieses eine Event geseedet. Es gibt **keine** neue Runtime-Event-Mechanik.
- **ViewItem = Seiten-Event.** Auf `is_product()` wird das aktuelle Produkt als `wp_sdtrk_wc.viewItem` ans Engine-Skript lokalisiert. `parseEventName()` würde aus einer gesetzten `prodId` ohnehin `view_item` ableiten ([wp-sdtrk-event.js:420-423](../public/js/wp-sdtrk-event.js#L420)); wir setzen den Namen zusätzlich explizit und liefern `value`/`currency`/`items`, damit ViewContent/view_item mit Wert + Inhalt feuert. **Kein** Once-Guard — ein ViewItem feuert pro Produktseiten-Aufruf (korrekt).
- **AddToCart = server-deferred (Nutzer-Entscheidung).** Single-Product-Seiten nutzen per Default ein Formular-Submit (kein AJAX, Seite navigiert weg), Archive feuern WooCommerces `added_to_cart`. Statt zwei Client-Pfade zu pflegen, wird beim Hook `woocommerce_add_to_cart` die Position in die **WC-Session** geschrieben und beim **nächsten Seitenaufbau** als `wp_sdtrk_wc.addToCart` geseedet. Deckt **alle** Add-Flows ab und passt 1:1 zum Seed-Modell. Bewusster Trade-off: bei reinen AJAX-Adds feuert AddToCart erst beim nächsten Pageload (nicht echtzeit).
- **Once-Guard AddToCart = Session-Verbrauch.** Die Session-Positionen werden beim Lokalisieren **gelöscht** → ein Reload feuert AddToCart nicht erneut (analog zur Rolle der `localStorage`-Marke bei Purchase, nur serverseitig).
- **Eine Localize-Methode mit Präzedenz.** `localize_order_data` wird zu `localize_commerce_data` verallgemeinert (gleicher Hook `wp_enqueue_scripts`, Priorität 20). Sie lokalisiert **genau eine** der drei Datenquellen in der Reihenfolge **order > addToCart > viewItem**, sodass pro Seitenaufbau nur ein Commerce-Event geseedet wird.
- **Gebündelt unter `wc_integration` (Nutzer-Entscheidung).** Keine neuen Redux-Schalter; beide Events laufen unter dem bestehenden Integrations-Gate `is_active()` — konsistent mit Purchase.
- **Keine Catcher-/Tracker-Änderung.** Mehr-Produkt-/Währungs-Payloads laufen über die bestehenden `getItems()`/`getCurrency()`-Pfade aller Catcher. Nur Verifikation, kein Umbau.

## Datenfluss (Ziel-Ist-Zustand)

```
ViewItem:
  is_product()  ──▶ localize_commerce_data()  ──▶ wp_sdtrk_wc.viewItem {prodId,name,value,currency,items[1]}
                                                       │
                                                       ▼
                                  Engine collect_eventData(): setEventName('view_item') + value/currency/items
                                                       │
                                  catchPageHit(2) → catchEventHit(2)  ──▶ Browser (ViewContent/view_item) + Server (S2S)

AddToCart:
  woocommerce_add_to_cart  ──▶ capture_add_to_cart()  ──▶ WC()->session 'wp_sdtrk_atc' += {id,name,qty,price}
        (AJAX + Formular)                                          │
                                                                   ▼ (nächster Seitenaufbau)
  localize_commerce_data()  ──▶ wp_sdtrk_wc.addToCart {value,currency,items[]}  +  session leeren (Once-Guard)
                                                       │
                                  Engine: setEventName('add_to_cart') + value/currency/items
                                                       │
                                  catchPageHit(2) → catchEventHit(2)  ──▶ Browser (AddToCart) + Server (S2S)
```

## Abhängigkeitsgraph

```
Mapper::productLine()  ─┬─▶ build_view_item_payload() ─▶ localize_commerce_data() ─▶ Engine .viewItem seed
                        └─▶ capture_add_to_cart() (session) ─▶ localize_commerce_data() ─▶ Engine .addToCart seed
                                                                        │
                                              Präzedenz order > addToCart > viewItem (eine Quelle/Load)
                                                                        │
                                                          Tests (JS-Seed + PHP-Payload)  ◀── beide Slices
                                                                        │
                                                              Spec-Konsolidierung
```

Implementierung bottom-up, aber **vertikal je Event** geschnitten: jeder Task liefert einen vollständigen Pfad (PHP-Daten → Engine-Seed → Spec) und lässt das System lauffähig.

## Aufgaben (Kurzfassung — Details in [todo.md](todo.md))

### Phase 1 — ViewItem (Produktseiten)
- **T1** Single-Product-Mapper + ViewItem-Payload (PHP) + Localize-Verallgemeinerung
- **T2** Engine seedet `view_item` + Spec

### Checkpoint A — ViewItem feuert end-to-end (Browser + Server)

### Phase 2 — AddToCart (server-deferred)
- **T3** Capture `woocommerce_add_to_cart` → WC-Session (PHP)
- **T4** Consume + Engine seedet `add_to_cart` + Präzedenz `order > addToCart > viewItem` + Spec

### Checkpoint B — AddToCart feuert end-to-end, Reload feuert nicht erneut

### Phase 3 — Tests & Spec-Konsolidierung
- **T5** Automatisierte Tests (JS-Seed-Fixtures + PHP-Payload/Präzedenz, Nicht-WC-/Purchase-Regression)
- **T6** Spec-Konsolidierung (README-Feuermodell, Overview-Matrix, Querverweise, ggf. [99-findings](../spec/99-findings.md))

### Checkpoint C — Alle Akzeptanzkriterien erfüllt, Spec = Ist-Zustand

## Risiken & Mitigationen

| Risiko | Impact | Mitigation |
|--------|--------|------------|
| `WC()->session` für Gäste evtl. nicht initialisiert | Mittel | WooCommerce startet die Session beim Warenkorb-Interaktions-Hook; trotzdem `WC()->session`-Existenz prüfen und sonst sauber überspringen. |
| Clear-on-Localize verliert AddToCart, wenn Engine-JS nicht läuft (Bot/JS aus/AdBlock) | Niedrig | Bewusst akzeptiert (gleiche Klasse wie „Server hängt am Browser", [07 › Trade-offs](../spec/07-woocommerce/README.md)). In Spec dokumentieren. |
| Mehrere Adds vor einem Pageload → ein zusammengefasstes AddToCart-Event statt N | Niedrig | Folge des Seed-Modells (ein Event/Load). Warenkorb-Positionen verlustfrei als `items[]` mitgesendet; als Trade-off dokumentieren. |
| Präzedenz-Kollision (z. B. „auf Produktseite bleiben"-Setting → Produktseite mit pending Add) | Niedrig | Feste Reihenfolge `order > addToCart > viewItem` in PHP **und** Engine. ViewItem entfällt für diesen einen Load. Dokumentieren. |
| Preis inkl./exkl. Steuer inkonsistent zu Purchase (`get_total`) | Niedrig | Default: Anzeigepreis (`wc_get_price_to_display`) für ViewItem/AddToCart-`value`; in Spec festhalten. |

## Offene Detail-Entscheidungen (nicht blockierend, Default gesetzt)

- **Preisbasis `value`:** Default Anzeigepreis inkl. Steuer (`wc_get_price_to_display`). Bei Bedarf später auf Netto umstellbar.
- **`qty` bei ViewItem:** fix `1` (Detailseiten-Aufruf, noch kein Mengenkontext).
