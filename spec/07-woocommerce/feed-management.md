# 07 — Produktfeed-Verwaltung

Admin-Seite zur Steuerung, **welche** veröffentlichten WooCommerce-Produkte im [Produkt-Feed](product-feed.md) erscheinen — ohne pro Produkt Postmeta zu pflegen. Per Default sind alle veröffentlichten Produkte im Feed; verwaltet wird ausschließlich, was **ausgeschlossen** wird.

## Aktivierung & Aufruf

- Verfügbar nur, wenn `Wp_Sdtrk_WC_Feed::is_enabled()` (WooCommerce-Integration aktiv **und** Schalter `wc_feed_enabled` an).
- **Versteckte Zusatzseite** `wp_sdtrk_feed_manage` (registriert via `admin_menu` → `register_page_wp_sdtrk_feed_manage`), per CSS (`display:none`) aus dem Menü ausgeblendet. Aufruf nur über den Button **„Manage feed"** in der WooCommerce-Redux-Sektion (raw-Feld `wc_feed_manage_link`, sichtbar bei `wc_integration` **und** `wc_feed_enabled`). Template: [templates/wp-sdtrk-admin-feed-manage.php](../../templates/wp-sdtrk-admin-feed-manage.php).
- Render-Callback `render_page_wp_sdtrk_feed_manage()` lädt `enqueue_custom_page_css()` + das Template. Seiten-JS/AJAX-Config werden bedingt geladen (`$hook_suffix === 'toplevel_page_wp_sdtrk_feed_manage'` → `enqueue_wp_sdtrk_feed_manage()`), JS-Global `SDTRK_FeedManage = { ajaxUrl, nonce, perPage, gpcTaxonomyUrl, i18n }`. `gpcTaxonomyUrl` zeigt auf die gebündelte Google-Taxonomie ([admin/data/google-product-taxonomy-de.txt](../../admin/data/google-product-taxonomy-de.txt)).

## Datenmodell

Die Ausschluss-Liste liegt in der Option `wp_sdtrk_feed_excluded` und wird über die Helfer `get_excluded_ids()` / `set_excluded_ids()` / `is_excluded()` auf `Wp_Sdtrk_WC_Feed` gelesen/geschrieben — Details und Filterwirkung auf den Feed siehe [product-feed.md › Ausschluss-Liste](product-feed.md). `set_excluded_ids()` invalidiert den Feed-Cache, sodass Änderungen ab dem nächsten Abruf greifen.

Das Google-Kategorie-Mapping liegt in der Option `wp_sdtrk_feed_gpc_map` (`get_gpc_map()` / `set_gpc_map()` / `resolve_gpc()`, ebenfalls Cache-invalidierend) — siehe [product-feed.md › Google-Produktkategorie](product-feed.md).

## UI-Fluss

```
WooCommerce-Sektion ──Button „Manage feed"──▶ Seite wp_sdtrk_feed_manage
  ├─ Kopf: Zähler „X von Y Produkten im Feed" (aria-live)
  ├─ Toolbar: Suche (serverseitig, debounced) · Status-Filter (Alle/Im Feed/Ausgeschlossen) · Bulk (Ausschließen/Aufnehmen)
  ├─ Tabelle (.wpsdtrk-table-glass): ☐ | Bild | Name | Beschreibung | Preis | SKU | Status-Toggle (Problemfelder rot hervorgehoben)
  │    ├─ Zeilen-Toggle ─AJAX─▶ save_feed_exclusion (ein Delta) · optimistisch, Rollback bei Fehler
  │    └─ Mehrfachauswahl + Bulk ─AJAX─▶ save_feed_exclusion (mehrere Deltas)
  ├─ Paginierung (Prev/Next + Seitenzahl)
  └─ Panel „Google-Produktkategorien" (<details>, lazy) ─AJAX─▶ list_gpc_categories / save_gpc_map
```

- **Liste laden:** AJAX `list_feed_products` (`data`: `search`, `page`, `per_page`, `status`). Serverseitig: `wc_get_products(['status'=>'publish','paginate'=>true,'limit'=>per_page,'page'=>page,'s'=>search,'orderby'=>'title'])`. Der Status-Filter verengt die Query über `include`/`exclude` gegen die Ausschluss-Liste. Antwort: `{ state, rows:[{id,name,sku,price,image,excluded,image_status,sku_missing,price_missing,description_status,description_preview}], total, totalPages, page, totalProducts, excludedCount }`.
- **Speichern (Ausschluss):** AJAX `save_feed_exclusion` (`data.changes`: `[{id, excluded}]`). Wendet die Deltas idempotent auf die Ausschluss-Liste an (Set-über-ID), persistiert via `set_excluded_ids()` (inkl. Cache-Invalidierung) und liefert aktualisierte Zähler. Junk-Einträge (fehlende/nicht-positive ID, Nicht-Array) werden übersprungen; String-Booleans aus `$_POST` (`'true'`/`'false'`) werden berücksichtigt.

> Ob Variationen als eigene Feed-Items erscheinen, steuert der Schalter `wc_feed_include_variants` (WooCommerce-Sektion, Default an) — siehe [product-feed.md › Aktivierung](product-feed.md). Er betrifft nur den generierten Feed, nicht diese Liste (die zeigt weiterhin Elternprodukte).

- **Zähler:** `totalProducts` = veröffentlichte Produkte (`wp_count_posts('product')->publish`), `excludedCount` = **nur die aktuell veröffentlichten** ausgeschlossenen Produkte (eine seither gelöschte/depublizierte ID in der Option verfälscht die „im Feed"-Zahl also nicht). Beide Funktionen teilen sich den privaten Helfer `feed_counts()`.

### Feld-Hervorhebung (Qualität)

Pro Zeile liefert `list_feed_products` mehrere Prüf-Ergebnisse, die **nur** für die ≤ `per_page` Produkte der aktuellen Seite berechnet werden (nie der ganze Katalog):

- `image_status` = `Wp_Sdtrk_WC_Feed::image_health()` (Bild gegen Meta-Vorgaben 500×500 / 8 MB; Maße aus DB-Metadaten, keine Bulk-Datei-I/O — Details siehe [product-feed.md › Qualitätsprüfung](product-feed.md)).
- `sku_missing` = SKU ist leer.
- `price_missing` = Preis ist leer oder ≤ 0.
- `description_status` = `Wp_Sdtrk_WC_Feed::evaluate_description()` (leer ⇒ `no_description`, > 5000 Zeichen ⇒ `too_long`, inkl. `length`) auf der aufgelösten Klartext-Beschreibung; `description_preview` ist eine auf 80 Zeichen gekürzte Vorschau für die Spalte.

Das JS hebt **das betroffene Feld selbst** hervor, statt einer eigenen Spalte: Bei einem harten Feed-Fehler (fehlendes/zu kleines/zu großes Bild, leere SKU, Preis 0, leere/zu lange Beschreibung) wird die jeweilige Zelle knallrot (`.wpsdtrk-cell-error`) mit einem ⛔-Hover-Icon (`.wpsdtrk-field-icon-error`), das den Grund als Tooltip trägt (inkl. Ist-Maße/-Größe beim Bild bzw. Ist-Zeichenzahl bei der Beschreibung); zusätzlich bekommt die ganze Zeile eine dezente rote Tönung (`.wpsdtrk-has-error`). Die Beschreibungs-Spalte zeigt die gekürzte Vorschau einzeilig (`.wpsdtrk-feed-desc-text`, Ellipsis).

Eine **fehlende Google-Kategorie wird in dieser Produktliste bewusst nicht angezeigt** (sie ist für Google optional und produktweit nicht sinnvoll pro Zeile zu markieren) — der Handlungsbedarf erscheint stattdessen im Mapping-Panel unten, wo die noch nicht zugewiesenen Kategorien rot hinterlegt sind.

### Panel „Google-Produktkategorien"

Aufklappbares `<details>` unter der Tabelle; beim **ersten Öffnen** lazy befüllt.

- **Kategorien laden:** AJAX `list_gpc_categories` → `{ state, rows:[{term_id, label, count, google_category}], mappedCount }`. `label` ist der Breadcrumb-Pfad (`Oberkategorie › Unterkategorie`), sortiert. Liefert **alle** `product_cat`-Terms (auch leere). Zeilen ohne Mapping (`google_category === ''`) werden rot hinterlegt (`.wpsdtrk-gpc-unmapped`).
- **Autocomplete:** Die gebündelte Google-Taxonomie wird per `fetch(gpcTaxonomyUrl)` einmalig in ein `<datalist>` geladen; die Eingabefelder sind Freitext mit datalist-Vervollständigung (Fehlschlag ist nicht fatal).
- **Speichern:** AJAX `save_gpc_map` (`data.changes`: `[{term_id, category}]`). Nicht-leere `category` setzt das Mapping, leere entfernt es; persistiert via `set_gpc_map()` (Cache-Invalidierung). Nach Erfolg wird die rote Hinterlegung der Zeile passend zum neuen Wert umgeschaltet (leer = weiterhin markiert).

Alle vier AJAX-Funktionen laufen über den bestehenden Sammel-Handler (`func`-Dispatch, Nonce `security_wp-sdtrk`, Capability `manage_options`) und prüfen zusätzlich `feed_ready()` (`Wp_Sdtrk_WC_Feed::is_enabled()`) — bei deaktiviertem Feed liefern sie `state=false` — siehe [04 › Admin-AJAX](../04-admin-and-options/settings-and-menu.md).

## Grenzen

- **Granularität:** Ausschluss greift auf **Elternebene**; einzelne Variationen können nicht separat ausgeschlossen werden (ein ausgeschlossenes variables Elternprodukt entfernt alle seine Variationen).
- **Quelle:** Es werden nur **veröffentlichte** Produkte gelistet (Feed-Quelle ist `status=publish`); Entwürfe erscheinen weder in der Liste noch im Feed.
- **Kein synchroner „Feed neu generieren"-Button:** Da jedes Speichern den Cache leert (Rebuild beim nächsten Abruf), entfällt eine blockierende Live-Generierung, die bei großen Katalogen ein Timeout riskieren würde.
- **Qualitäts-Check nur seitenweise:** Bild- und Kategorie-Prüfung laufen ausschließlich für die aktuell angezeigten Produkte (max. `per_page`), damit auch große Kataloge den Server nicht belasten. Es gibt keinen katalogweiten Voll-Scan.
- **Bild-Check auf Elternebene:** geprüft wird das Beitragsbild des Elternprodukts; variationseigene Bilder werden nicht einzeln bewertet.
