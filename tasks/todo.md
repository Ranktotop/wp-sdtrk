# Task-Liste — WooCommerce Produktfeed-Verwaltung

> Reihenfolge nach [plan.md](plan.md). Jeder Task = ein vollständiger vertikaler Pfad.
> **Definition of Done je Task:** Code geändert **und** betroffene Spec auf Ist-Zustand gebracht (siehe [CLAUDE.md](../CLAUDE.md)), Tests grün.

Bestätigte Entscheidungen: **Im Feed / Ausgeschlossen** (eine Ausschluss-Option, keine WC-Kategorie-Zuweisung) · **Katalog 2000+** → serverseitige Suche + Paginierung · **Tabelle mit Status-Toggle + Bulk** statt Cross-List-DnD · Default = veröffentlichte Produkte im Feed.

Legende: ☐ offen · ☑ erledigt

---

## Phase 1 — Backend: Ausschluss-Persistenz + Feed-Filter

### ☐ T1 — Option-Helfer auf `Wp_Sdtrk_WC_Feed` (PHP)
- **Tun:** Standalone-Option `wp_sdtrk_feed_excluded` (`autoload=false`).
  - `get_excluded_ids(): int[]` — liest Option, `array_map('intval')`, dedupe, `array_values`; `[]` bei fehlend/korrupt.
  - `set_excluded_ids(int[] $ids): void` — sanitisiert, `update_option(...)`, **danach `delete_option(CACHE_OPTION)`** (Cache-Invalidierung).
  - `is_excluded(int $id): bool`.
  - Konstante `EXCLUDED_OPTION = 'wp_sdtrk_feed_excluded'`.
- **Abhängig von:** —
- **Dateien:** [public/class-wp-sdtrk-wc-feed.php](../public/class-wp-sdtrk-wc-feed.php), `tests/test-wc-feed-exclusions.php` · Scope: S
- **Akzeptanz:**
  - [ ] `get_excluded_ids()` liefert sauberes int-Array (dedupe, gefiltert) auch bei skalarem/korruptem Optionswert.
  - [ ] `set_excluded_ids()` persistiert **und** löscht `wp_sdtrk_feed_cache`.
- **Verifikation:** [ ] `php tests/test-wc-feed-exclusions.php` grün (Getter-Sanitisierung + Cache-Clear via Stub).

### ☐ T2 — `collect()` respektiert Ausschlüsse (PHP)
- **Tun:** In [`collect()`](../public/class-wp-sdtrk-wc-feed.php#L145) `wc_get_products([... , 'exclude' => $this->get_excluded_ids()])`. Eltern-Ausschluss → Variationen entfallen automatisch (nur über `get_children()` gesammelt).
- **Abhängig von:** T1
- **Dateien:** `public/class-wp-sdtrk-wc-feed.php`, `tests/test-wc-feed-*.php` (collect-/generate-Test) · Scope: S
- **Akzeptanz:**
  - [ ] Ausgeschlossene Produkt-ID erscheint nicht in den von `collect()` erzeugten Rows.
  - [ ] Ausgeschlossenes variables Elternprodukt → keine seiner Variationen im Feed.
- **Verifikation:** [ ] Test mit gefakten `wc_get_products` prüft, dass `exclude` durchgereicht wird und das XML aus `generate()` das Produkt nicht enthält.

### ☐ T3 — Spec: Feed respektiert Ausschlüsse
- **Tun:** [product-feed.md](../spec/07-woocommerce/product-feed.md) — `collect()` filtert über `wp_sdtrk_feed_excluded`; Cache-Invalidierung bei Änderung; Variationen-Grenze (nur Elternebene). [05 data-model/README.md](../spec/05-data-model/README.md) — neue Option ergänzen.
- **Abhängig von:** T1–T2
- **Dateien:** Spec · Scope: S
- **Akzeptanz:** [ ] Spec nennt Ausschluss-Option + Filterverhalten, kein Changelog-Stil.

### ☑/☐ Checkpoint A — Backend (menschliche Freigabe)
- [ ] Alle `php tests/test-wc-*.php` grün.
- [ ] **Live-Smoke:** `wp_sdtrk_feed_excluded` manuell setzen → Feed-URL abrufen → ausgeschlossenes Produkt fehlt im XML; nach erneutem Setzen ist Cache neu aufgebaut.

---

## Phase 2 — Admin-Seite: Gerüst + Navigation

### ☐ T4 — Versteckte Seite `wp_sdtrk_feed_manage` + Render-Callback (PHP)
- **Tun:** Analog LinkedIn:
  - `register_page_wp_sdtrk_feed_manage()` — `add_menu_page(slug='wp_sdtrk_feed_manage', cap='manage_options', cb=render…)` + `admin_head`-CSS `#toplevel_page_wp_sdtrk_feed_manage{display:none!important}`.
  - `render_page_wp_sdtrk_feed_manage()` — `enqueue_custom_page_css()` + include `templates/wp-sdtrk-admin-feed-manage.php`.
  - Loader: `add_action('admin_menu', $plugin_admin, 'register_page_wp_sdtrk_feed_manage')` in [includes/class-wp-sdtrk.php](../includes/class-wp-sdtrk.php) (neben Zeile 235).
  - Template-Stub: Wrap `.wrap` + Titel + leerer Tabellen-Container (`.wpsdtrk-table-glass`), Capability-Guard-Kopf wie LinkedIn-Template.
- **Abhängig von:** —
- **Dateien:** `admin/class-wp-sdtrk-admin.php`, `includes/class-wp-sdtrk.php`, `templates/wp-sdtrk-admin-feed-manage.php` · Scope: M
- **Akzeptanz:** [ ] Aufruf `admin.php?page=wp_sdtrk_feed_manage` zeigt gestylte Seite; Menüeintrag versteckt; nur `manage_options`.

### ☐ T5 — Redux-Button „Feed verwalten" in der WC-Sektion (PHP)
- **Tun:** In der WooCommerce-Sektion ([admin/class-wp-sdtrk-admin.php:830](../admin/class-wp-sdtrk-admin.php#L830)) ein `raw`-Feld nach `wc_feed_url_info`: `<a href="admin.php?page=wp_sdtrk_feed_manage" class="button button-primary">Feed verwalten</a>`, `'required' => [['wc_integration','=','1'],['wc_feed_enabled','=','1']]`.
- **Abhängig von:** T4
- **Dateien:** `admin/class-wp-sdtrk-admin.php` · Scope: XS
- **Akzeptanz:** [ ] Button erscheint **nur** bei aktiver Integration + aktivem Feed und führt zur Seite.

### ☐ Checkpoint B — Navigation (menschliche Freigabe)
- [ ] Button sichtbar/versteckt korrekt gegated; Klick öffnet gestylte (leere) Seite im Menü-Look.

---

## Phase 3 — Produktliste: serverseitige Suche + Paginierung

### ☐ T6 — AJAX `list_feed_products` (PHP)
- **Tun:** Private Methode in [admin/class-wp-sdtrk-admin-ajax.php](../admin/class-wp-sdtrk-admin-ajax.php):
  `list_feed_products(array $data, array $meta): array` — liest `search`, `page`, `per_page`, `status` aus `$data`; `wc_get_products(['status'=>'publish','paginate'=>true,'limit'=>$per_page,'page'=>$page,'s'=>$search,'orderby'=>'title','order'=>'ASC'])`; baut Rows `{id,name,sku,price,image,excluded}` (excluded via `Wp_Sdtrk_WC_Feed::is_excluded`); Status-Filter „im Feed/ausgeschlossen" serverseitig anwenden; Rückgabe `{state, rows, total, totalPages, inFeed, totalProducts}`.
- **Abhängig von:** T1 (Ausschluss-Lookup)
- **Dateien:** `admin/class-wp-sdtrk-admin-ajax.php`, ggf. `tests/test-admin-ajax-feed.php` · Scope: M
- **Akzeptanz:** [ ] Liefert paginierte, gefilterte Rows + korrekte Zähler; respektiert Nonce/Cap (geerbt vom Dispatcher).

### ☐ T7 — Seiten-JS + Enqueue + Render (JS/PHP)
- **Tun:**
  - `admin/js/wp-sdtrk-admin-feed-manage.js`: lädt Liste via `SDTRK_FeedManage.ajaxUrl` (`func:'list_feed_products'`), rendert Tabelle, Suchfeld (debounced, serverseitig), Status-Filter, Paginierung (Prev/Next + Seite), Live-Zähler.
  - Enqueue analog LinkedIn: in `enqueue_scripts()` `if ($hook_suffix === 'toplevel_page_wp_sdtrk_feed_manage')` → `enqueue_feed_manage_page()` mit Localize `SDTRK_FeedManage = {ajaxUrl, nonce, labels…}`.
  - Template: Toolbar + Tabellen-Skelett + Paginierungs-Container, das JS füllt.
- **Abhängig von:** T6
- **Dateien:** `admin/js/wp-sdtrk-admin-feed-manage.js`, `admin/class-wp-sdtrk-admin.php`, `templates/wp-sdtrk-admin-feed-manage.php` · Scope: M
- **Akzeptanz:** [ ] Seite lädt paginiert; Suche/Filter wirken serverseitig; ausgeschlossene Produkte zeigen ihren Status.

### ☐ Checkpoint C — Liste (menschliche Freigabe)
- [ ] **Live (großer Katalog):** Liste lädt zügig, Suche/Paginierung funktionieren ohne Voll-Last; kein Laden aller Produkte ins DOM.

---

## Phase 4 — Toggle + Bulk + Speichern

### ☐ T8 — AJAX `save_feed_exclusion` (PHP)
- **Tun:** `save_feed_exclusion(array $data, array $meta): array` — `$data['changes'] = [{id, excluded(bool)}]`; lädt `get_excluded_ids()`, wendet Deltas an (hinzufügen/entfernen), `set_excluded_ids()` (persistiert + Cache-Clear); Rückgabe `{state, message, inFeed, totalProducts, excludedCount}`. IDs sanitisieren (`intval`), nicht existierende/nicht-publish ignorieren.
- **Abhängig von:** T1
- **Dateien:** `admin/class-wp-sdtrk-admin-ajax.php`, `tests/test-admin-ajax-feed.php` · Scope: S
- **Akzeptanz:**
  - [ ] Einzel- und Mehrfach-Deltas aktualisieren `wp_sdtrk_feed_excluded` korrekt (idempotent).
  - [ ] Cache wird gelöscht; aktualisierte Zähler zurückgegeben.
- **Verifikation:** [ ] `php tests/test-admin-ajax-feed.php` grün (Delta-Anwendung, Sanitisierung, Cache-Clear).

### ☐ T9 — Toggle + Bulk-Aktionen im JS (JS)
- **Tun:** In `wp-sdtrk-admin-feed-manage.js`:
  - Zeilen-Toggle „Im Feed ⇄ Ausgeschlossen" → `func:'save_feed_exclusion'` mit einem Change; **Optimistic UI** + Rollback bei Fehler; `wpsdtrk_show_notice()`.
  - Checkbox-Mehrfachauswahl + Bulk-Buttons „Ausschließen/Aufnehmen" (mit Confirm-Modal bei großer Auswahl) → mehrere Changes in einem Call.
  - Live-Zähler nach Antwort aktualisieren.
- **Abhängig von:** T8
- **Dateien:** `admin/js/wp-sdtrk-admin-feed-manage.js`, `templates/wp-sdtrk-admin-feed-manage.php` · Scope: M
- **Akzeptanz:** [ ] Toggle/Bulk persistiert; UI spiegelt Status sofort; Fehler rollt zurück + Fehler-Notice.

### ☐ Checkpoint D — End-to-End (menschliche Freigabe)
- [ ] **Live:** Produkt auf „Ausgeschlossen" schalten → Feed-URL neu laden → Produkt fehlt; Aufnahme zurück → Produkt wieder da; Bulk an ~10 Produkten funktioniert; Status hält über Reload der Admin-Seite.

---

## Phase 5 — Politur + finale Spec/Tests

### ☐ T10 — UX-Politur
- **Tun:** Empty-States (kein Produkt / keine Treffer), Lade-/Disabled-Zustände, „Alle auf dieser Seite auswählen", A11y (Toggle als echtes `button[aria-pressed]`/Switch, Fokus), optionaler Button „Feed jetzt neu generieren" (`func` ruft `regenerate_cache()`), Anzeige „zuletzt generiert".
- **Abhängig von:** T9
- **Dateien:** Template, JS, ggf. AJAX (`regenerate_feed_cache`) · Scope: M
- **Akzeptanz:** [ ] Saubere Zustände/Feedback; Tastatur-Bedienung möglich.

### ☐ T11 — Finale Spec
- **Tun:** **Neu** `spec/07-woocommerce/feed-management.md` (Zweck, Option, AJAX-`func`s, UI-Fluss-Diagramm wie LinkedIn-Mapping). Ergänzen: [07-woocommerce/README.md](../spec/07-woocommerce/README.md) (Index + Artefakt-Tabelle), [04 settings-and-menu.md](../spec/04-admin-and-options/settings-and-menu.md) (versteckte Seite + AJAX-`func`s `list_feed_products`/`save_feed_exclusion`).
- **Abhängig von:** T4–T10
- **Dateien:** Spec · Scope: M
- **Akzeptanz:** [ ] Spec spiegelt den Ist-Zustand vollständig, Querverweise konsistent, kein Changelog-Stil.

### ☐ Checkpoint E — Release-Bereitschaft
- [ ] Alle `php tests/test-*.php` + `node tests/*.mjs` grün.
- [ ] Spec = Ist-Zustand (neue Datei verlinkt, Indizes aktualisiert).
- [ ] Live-Smoke-Test bestanden (CP-D), dann Release/Tag.
