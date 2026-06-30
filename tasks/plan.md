# Plan — WooCommerce Produktfeed-Verwaltung

> Feature: Eine Admin-Seite, auf der gesteuert wird, **welche** veröffentlichten WooCommerce-Produkte im
> [Produkt-Feed](../spec/07-woocommerce/product-feed.md) erscheinen — ohne pro Produkt Postmeta zu pflegen.
> Erreichbar über einen Button **„Feed verwalten"** in der WooCommerce-Redux-Sektion (nur wenn der Feed aktiv ist).

## 1. Bestätigte Entscheidungen (Nutzer-Freigabe)

| Frage | Entscheidung | Konsequenz |
|-------|--------------|------------|
| **Datenmodell** | **Nur „Im Feed" / „Ausgeschlossen"** — eine einzige Ausschluss-Liste, *keine* WC-Kategorie-Zuweisung. | Standalone-Option `wp_sdtrk_feed_excluded` (Array von Produkt-IDs). Default = **im Feed**. |
| **Katalog-Größe** | **Groß (2000+)** | **Serverseitige Suche + Paginierung von Anfang an.** Kein Laden aller Produkte ins DOM. |
| **Interaktion** | „Drag & Drop **oder so**" | Bei 2000+ ist Cross-List-DnD unpraktikabel → **paginierte Tabelle mit Status-Toggle + Bulk-Aktionen**. Skaliert, ehrt die Vorgabe. |

> **Warum kein Drag & Drop über zwei Spalten?** Zwei Spalten („Im Feed" links, „Ausgeschlossen" rechts) mit
> jQuery-UI-`connectToSortable` setzt voraus, dass **alle** Produkte gleichzeitig im DOM liegen. Bei 2000+ Produkten
> ist das nicht ladbar/bedienbar und mit serverseitiger Paginierung inkompatibel (ein Produkt kann nicht auf eine
> andere, nicht geladene Seite gezogen werden). Stattdessen: eine durchsuchbare, paginierte Tabelle, jede Zeile mit
> einem Status-Toggle „Im Feed ⇄ Ausgeschlossen", plus Mehrfachauswahl für Bulk-Ausschluss/-Aufnahme. Optisch
> weiterhin „links die Produkte, rechts der Status" — nur skalierbar.

## 2. Architektur — Anlehnung an die bestehende LinkedIn-Mapping-Seite

Die [LinkedIn-Mapping-Seite](../spec/05-data-model/linkedin-mapping.md) ist die **exakte Vorlage** (versteckte
Admin-Seite, Glass-Tabelle, AJAX-`func`-Dispatch). Wir spiegeln dieses Muster 1:1:

| Baustein | Vorlage (LinkedIn) | Neu (Feed) |
|----------|--------------------|------------|
| Versteckte Admin-Seite | `register_page_wp_sdtrk_admin_map_linkedin()` ([admin/class-wp-sdtrk-admin.php:1022](../admin/class-wp-sdtrk-admin.php#L1022)) | `register_page_wp_sdtrk_feed_manage()` |
| Render-Callback | `render_page_wp_sdtrk_admin_map_linkedin()` (:1049) | `render_page_wp_sdtrk_feed_manage()` |
| Template | `templates/wp-sdtrk-admin-map-linkedin.php` | `templates/wp-sdtrk-admin-feed-manage.php` |
| Bedingter Asset-Enqueue | `if ($hook_suffix === 'toplevel_page_wp_sdtrk_admin_map_linkedin')` (:118) | `… === 'toplevel_page_wp_sdtrk_feed_manage'` |
| Seiten-JS + Localize-Objekt | `js/wp-sdtrk-admin-map-linkedin.js` + `SDTRK_Linkedin` (:989) | `js/wp-sdtrk-admin-feed-manage.js` + `SDTRK_FeedManage` |
| Redux-Button (raw-Feld) | `admin.php?page=wp_sdtrk_admin_map_linkedin` (:633) | `admin.php?page=wp_sdtrk_feed_manage` |
| AJAX-Endpoints | `get_linkedin_mapping` / `delete_linkedin_mapping` ([admin/class-wp-sdtrk-admin-ajax.php](../admin/class-wp-sdtrk-admin-ajax.php)) | `list_feed_products` / `save_feed_exclusion` |
| Glass-Styling | `wpsdtrk-table-glass`, `enqueue_custom_page_css()` (:85) | dieselben Klassen/CSS |
| Notice/Modal-Utils | `wpsdtrk_show_notice()`, Confirm-Modal | dieselben |

**AJAX-Dispatch** (unverändert genutzt): Action `wp_sdtrk_handle_admin_ajax_callback`, Nonce `security_wp-sdtrk`,
Capability `manage_options`, Routing über `$_POST['func']` → gleichnamige private Methode, Antwort `{state, message, …}`.

## 3. Datenmodell & Feed-Integration

### Persistenz
- **Neue Option** `wp_sdtrk_feed_excluded` (eigenständig, **nicht** Redux — analog `wp_sdtrk_feed_token`),
  `autoload = false`. Inhalt: `int[]` der **ausgeschlossenen** Produkt-IDs.
- **Default = im Feed:** Die Liste enthält nur Ausschlüsse. Neu veröffentlichte Produkte sind automatisch enthalten
  (kein Eintrag = enthalten) — erfüllt „per Default veröffentlichte Produkte im Feed".
- Lese-/Schreib-Helfer auf `Wp_Sdtrk_WC_Feed` (rein bzw. seiteneffekt-gekapselt):
  - `get_excluded_ids(): int[]` — liest + sanitisiert die Option (`array_map('intval')`, dedupe, `array_values`).
  - `set_excluded_ids(int[] $ids): void` — persistiert **und löscht den Feed-Cache** (`delete_option(CACHE_OPTION)`),
    damit Änderungen ab dem nächsten Feed-Abruf greifen (kalter Cache → Rebuild über den bestehenden Stampede-Lock).
  - `is_excluded(int $id): bool` — Komfort-Prüfer.

### Feed-Generierung
- [`Wp_Sdtrk_WC_Feed::collect()`](../public/class-wp-sdtrk-wc-feed.php#L145) ergänzt die Query um
  `'exclude' => $this->get_excluded_ids()`. Eltern-Ausschluss greift transitiv auf seine Variationen (Variationen
  werden nur über die `get_children()`-Schleife des Elternprodukts gesammelt). **Variations-Granularität ist v1 außen vor.**
- `feed_items()`/`render_xml()` (rein) bleiben unberührt.

### Cache
- Ausschluss-Änderung → `wp_sdtrk_feed_cache` löschen. Der nächste Endpoint-Request baut unter dem bestehenden
  Transient-Lock neu auf. (Kein synchroner Voll-Rebuild im Admin-Request — schützt vor langen Requests bei großen Katalogen.)

## 4. UI/UX — Management-Seite

```
Feed verwalten (wp_sdtrk_feed_manage)
├─ Kopf: Titel + Kurz-Erklärung + Live-Zähler („X von Y Produkten im Feed")
├─ Toolbar: Suchfeld (serverseitig) · Filter „Status: Alle / Im Feed / Ausgeschlossen" · [Bulk: Ausschließen | Aufnehmen]
├─ Tabelle (.wpsdtrk-table-glass): ☐ | Bild | Name | SKU | Preis | Status-Toggle
│    └─ Toggle pro Zeile: „Im Feed ⇄ Ausgeschlossen" → AJAX save_feed_exclusion (Delta) → Notice
└─ Paginierung (Prev/Next + Seitenzahl), per_page z. B. 50
```

- **Serverseitige Suche/Paginierung:** AJAX `list_feed_products({ search, page, per_page, status })` →
  `wc_get_products(['status'=>'publish', 'paginate'=>true, 'limit'=>per_page, 'page'=>page, 's'=>search, …])`,
  liefert `{ rows:[{id,name,sku,price,image,excluded}], total, totalPages }`. **Nie alle Produkte ins DOM.**
- **Speichern als Delta:** `save_feed_exclusion({ changes:[{id, excluded}] })` — fügt IDs hinzu/entfernt sie aus
  `wp_sdtrk_feed_excluded`, löscht Cache, gibt neue Zähler zurück. Einzel-Toggle = ein Change; Bulk = mehrere.
- **Optimistic UI** mit Rollback bei AJAX-Fehler; `wpsdtrk_show_notice()` für Erfolg/Fehler.
- **Styling:** `wpsdtrk-table-glass` + `wp-sdtrk-custom-pages.css` (Dark/Glass, Grün-Akzent `#57b957`).

## 5. Abhängigkeitsgraph

```
[P1 Backend: Option-Helfer + collect()-Filter + Cache-Clear + Tests + Spec]
      │  (Feed respektiert Ausschlüsse — unabhängig von jeder UI testbar)
      ▼
[P2 Seiten-Gerüst + Navigation: versteckte Seite, Redux-Button, leeres Template, CSS]
      │  (Button → gestylte Seite)
      ▼
[P3 Produktliste: AJAX list_feed_products + serverseitige Suche/Paginierung + Render]
      │  (Seite zeigt paginierte, durchsuchbare Produktliste mit Status)
      ▼
[P4 Toggle + Bulk + Speichern: AJAX save_feed_exclusion + Optimistic UI + Cache-Clear]
      │  (Änderung → persistiert → Feed spiegelt sie)
      ▼
[P5 Politur + finale Spec/Tests: Zähler, Empty-States, Bulk-Confirm, A11y, Guardrails]
```

Jede Phase ist eine **vertikale Scheibe** (ein vollständiger Pfad), nicht eine horizontale Schicht. P1 liefert bereits
geschäftlichen Wert (Feed-Steuerung per Option) und ist isoliert testbar, bevor irgendeine UI existiert.

## 6. Checkpoints (menschliche Freigabe zwischen Phasen)

- **CP-A (nach P1):** Manueller Smoke-Test — Option setzen, Feed-URL abrufen, ausgeschlossenes Produkt fehlt im XML.
  Unit-Suite grün. → Freigabe für UI-Arbeit.
- **CP-B (nach P2):** Button erscheint nur bei aktivem Feed, öffnet gestylte (leere) Seite im Menü-Look. → Freigabe.
- **CP-C (nach P3):** Liste lädt paginiert/durchsuchbar bei großem Katalog ohne Performance-Einbruch. → Freigabe.
- **CP-D (nach P4):** End-to-End — Toggle/Bulk ändert den realen Feed. → Freigabe für Politur.
- **CP-E (nach P5):** Release-Bereitschaft (Spec vollständig nachgezogen, Tests grün, Live-Smoke-Test).

## 7. Spec-Pflicht (CLAUDE.md)

Eine Code-Änderung gilt erst als fertig, wenn die Spec den neuen Ist-Zustand exakt spiegelt:
- **Neu:** `spec/07-woocommerce/feed-management.md` (Management-Seite: Zweck, Option, AJAX, UI-Fluss).
- **Ergänzen:** [product-feed.md](../spec/07-woocommerce/product-feed.md) (collect() respektiert Ausschlüsse; Cache-Invalidierung),
  [07-woocommerce/README.md](../spec/07-woocommerce/README.md) (Index-Zeile + Datei-/Artefakt-Tabelle),
  [04 settings-and-menu.md](../spec/04-admin-and-options/settings-and-menu.md) (neue versteckte Seite + neue AJAX-`func`s),
  [05 data-model/README.md](../spec/05-data-model/README.md) (neue Option `wp_sdtrk_feed_excluded`).

## 8. Risiken & Trade-offs

- **Großer Katalog:** Suche/Paginierung serverseitig (gelöst). Die Ausschluss-Option wächst mit der Zahl der
  Ausschlüsse — bei „fast alles ausgeschlossen" groß; akzeptiert (Array von Ints, `autoload=false`). Falls künftig
  invertierte Speicherung (Inklusionsliste) nötig: separater Folge-Task.
- **Variationen:** v1 schließt nur auf Elternebene aus. Einzel-Variation-Ausschluss = Folge-Task (in Spec als Grenze notieren).
- **Cache-Latenz:** Änderung wird erst beim nächsten Feed-Abruf sichtbar (Cache-Clear + Rebuild). Optionaler
  „Feed jetzt neu generieren"-Button → P5-Politur (nice-to-have).
- **Nur veröffentlichte Produkte** stehen zur Verfügung (Feed-Quelle ist `status=publish`). Entwürfe erscheinen nicht;
  konsistent mit dem bestehenden Feed.

## 9. Verifikation (Definition of Done je Task)

- PHP-Tests: `php tests/test-*.php` grün; JS-Tests: `node tests/*.mjs` grün (bestehendes Schema).
- Bei Code-Änderung Spec nachgezogen (Abschnitt 7).
- Manueller Live-Smoke-Test je Checkpoint (Feed-XML, Admin-Seite).
