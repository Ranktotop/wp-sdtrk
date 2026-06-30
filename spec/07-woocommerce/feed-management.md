# 07 — Produktfeed-Verwaltung

Admin-Seite zur Steuerung, **welche** veröffentlichten WooCommerce-Produkte im [Produkt-Feed](product-feed.md) erscheinen — ohne pro Produkt Postmeta zu pflegen. Per Default sind alle veröffentlichten Produkte im Feed; verwaltet wird ausschließlich, was **ausgeschlossen** wird.

## Aktivierung & Aufruf

- Verfügbar nur, wenn `Wp_Sdtrk_WC_Feed::is_enabled()` (WooCommerce-Integration aktiv **und** Schalter `wc_feed_enabled` an).
- **Versteckte Zusatzseite** `wp_sdtrk_feed_manage` (registriert via `admin_menu` → `register_page_wp_sdtrk_feed_manage`), per CSS (`display:none`) aus dem Menü ausgeblendet. Aufruf nur über den Button **„Manage feed"** in der WooCommerce-Redux-Sektion (raw-Feld `wc_feed_manage_link`, sichtbar bei `wc_integration` **und** `wc_feed_enabled`). Template: [templates/wp-sdtrk-admin-feed-manage.php](../../templates/wp-sdtrk-admin-feed-manage.php).
- Render-Callback `render_page_wp_sdtrk_feed_manage()` lädt `enqueue_custom_page_css()` + das Template. Seiten-JS/AJAX-Config werden bedingt geladen (`$hook_suffix === 'toplevel_page_wp_sdtrk_feed_manage'` → `enqueue_wp_sdtrk_feed_manage()`), JS-Global `SDTRK_FeedManage = { ajaxUrl, nonce, perPage, i18n }`.

## Datenmodell

Die Ausschluss-Liste liegt in der Option `wp_sdtrk_feed_excluded` und wird über die Helfer `get_excluded_ids()` / `set_excluded_ids()` / `is_excluded()` auf `Wp_Sdtrk_WC_Feed` gelesen/geschrieben — Details und Filterwirkung auf den Feed siehe [product-feed.md › Ausschluss-Liste](product-feed.md). `set_excluded_ids()` invalidiert den Feed-Cache, sodass Änderungen ab dem nächsten Abruf greifen.

## UI-Fluss

```
WooCommerce-Sektion ──Button „Manage feed"──▶ Seite wp_sdtrk_feed_manage
  ├─ Kopf: Zähler „X von Y Produkten im Feed" (aria-live)
  ├─ Toolbar: Suche (serverseitig, debounced) · Status-Filter (Alle/Im Feed/Ausgeschlossen) · Bulk (Ausschließen/Aufnehmen)
  ├─ Tabelle (.wpsdtrk-table-glass): ☐ | Bild | Name | SKU | Preis | Status-Toggle
  │    ├─ Zeilen-Toggle ─AJAX─▶ save_feed_exclusion (ein Delta) · optimistisch, Rollback bei Fehler
  │    └─ Mehrfachauswahl + Bulk ─AJAX─▶ save_feed_exclusion (mehrere Deltas)
  └─ Paginierung (Prev/Next + Seitenzahl)
```

- **Liste laden:** AJAX `list_feed_products` (`data`: `search`, `page`, `per_page`, `status`). Serverseitig: `wc_get_products(['status'=>'publish','paginate'=>true,'limit'=>per_page,'page'=>page,'s'=>search,'orderby'=>'title'])`. Der Status-Filter verengt die Query über `include`/`exclude` gegen die Ausschluss-Liste. Antwort: `{ state, rows:[{id,name,sku,price,image,excluded}], total, totalPages, page, totalProducts, excludedCount }`.
- **Speichern:** AJAX `save_feed_exclusion` (`data.changes`: `[{id, excluded}]`). Wendet die Deltas idempotent auf die Ausschluss-Liste an (Set-über-ID), persistiert via `set_excluded_ids()` (inkl. Cache-Invalidierung) und liefert aktualisierte Zähler (`excludedCount`, `totalProducts`). Junk-Einträge (fehlende/nicht-positive ID, Nicht-Array) werden übersprungen; String-Booleans aus `$_POST` (`'true'`/`'false'`) werden berücksichtigt.

Beide AJAX-Funktionen laufen über den bestehenden Sammel-Handler (`func`-Dispatch, Nonce `security_wp-sdtrk`, Capability `manage_options`) — siehe [04 › Admin-AJAX](../04-admin-and-options/settings-and-menu.md).

## Grenzen

- **Granularität:** Ausschluss greift auf **Elternebene**; einzelne Variationen können nicht separat ausgeschlossen werden (ein ausgeschlossenes variables Elternprodukt entfernt alle seine Variationen).
- **Quelle:** Es werden nur **veröffentlichte** Produkte gelistet (Feed-Quelle ist `status=publish`); Entwürfe erscheinen weder in der Liste noch im Feed.
- **Kein synchroner „Feed neu generieren"-Button:** Da jedes Speichern den Cache leert (Rebuild beim nächsten Abruf), entfällt eine blockierende Live-Generierung, die bei großen Katalogen ein Timeout riskieren würde.
