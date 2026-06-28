# T3.0 — Design-Memo: Produkt-Feed

> Verbindliche Entscheidungen für Phase 3 ([plan.md](plan.md) → T3.1–T3.4).
> Voraussetzung: aktive [WooCommerce-Integration](wc-design.md) (`is_active()`).

## 0. Bestätigte Entscheidungen (Nutzer-Freigabe)

| Frage | Entscheidung |
|-------|--------------|
| **Format** | **RSS 2.0 mit `g:`-Namespace** (Google Merchant Center **und** Meta Commerce Manager lesen dieses Format). |
| **Zugriffsschutz** | **Token in der URL** — Feed nur mit gültigem Token abrufbar. |
| **Intervall** | **Täglich** (`daily`) per WP-Cron. |

## 1. Aktivierung

- Feed nur verfügbar, wenn `Wp_Sdtrk_WC_Integration::is_active()` **und** neuer Schalter `wc_feed_enabled` (Redux, WooCommerce-Sektion) an.
- **Token:** eigenständige Option `wp_sdtrk_feed_token` (nicht Redux). Wird bei Bedarf via `wp_generate_password(32, false)` erzeugt und persistiert. In der WooCommerce-Redux-Sektion zeigt ein Info-Feld die fertige Feed-URL inkl. Token zum Kopieren.

## 2. Endpoint (T3.2)

- **Query-Var statt Rewrite-Rule** (kein `flush_rewrite_rules` nötig): Abruf über `…/?wp_sdtrk_feed=1&token=<TOKEN>`.
- Hook `template_redirect`: wenn `wp_sdtrk_feed` gesetzt → prüfen `is_active()` + `wc_feed_enabled` + `hash_equals(token)`. Bei Erfolg `Content-Type: application/xml`, Feed ausgeben, `exit`. Bei ungültigem Token: `403`.

## 3. Feed-Generator (T3.1)

Klasse `Wp_Sdtrk_WC_Feed` (in `load_dependencies()` registriert).

- `feed_items(array $rows): array` — nimmt bereits extrahierte Produkt-Rohdaten und normalisiert sie (rein, testbar).
- `render_xml(array $items): string` — baut den RSS-2.0/`g:`-String (rein, testbar).
- `collect(): array` — fragt veröffentlichte WC-Produkte ab (`wc_get_products`), inkl. Variationen, und erzeugt die Rohdaten-Zeilen (WC-abhängig).
- `generate(): string` — `render_xml(feed_items(collect()))`.

**Feld-Mapping (`g:`):**

| Feld | Quelle |
|------|--------|
| `g:id` | Produkt-/Variations-ID (SKU falls vorhanden, sonst ID) |
| `title` | Produktname |
| `description` | Kurz-/Langbeschreibung (Tags entfernt) |
| `link` | Permalink |
| `g:image_link` | Beitragsbild-URL |
| `g:availability` | `in_stock` / `out_of_stock` |
| `g:price` | `"<Betrag> <Währung>"` (Order-/Shop-Währung) |
| `g:condition` | `new` |
| `g:brand` | Brand-Option bzw. Blogname |
| `g:item_group_id` | Eltern-ID bei Variationen |

**Varianten:** Variable Produkte werden als mehrere Items (je Variation) mit gemeinsamer `g:item_group_id` (= Eltern-ID) ausgegeben. Einfache Produkte als ein Item.

## 4. Cron (T3.3)

- `WP_SDTRK_Cron::HOOKS = ['wp_sdtrk_cron_generate_feed']`.
- `register_cron_actions()` bindet den Hook an die Feed-Regenerierung.
- `register_cronjobs()` plant `daily` (statt bisher hart `hourly`).
- Re-/Deaktivierung über den bestehenden Activator/Deactivator (rufen bereits `register_cronjobs()`/`unregister_cronjobs()`).
- **Caching:** Der Cron schreibt das generierte XML in eine Option/Transient (`wp_sdtrk_feed_cache`); der Endpoint liefert den Cache (Fallback: Live-Generierung, falls Cache leer). So ist der Abruf schnell und der Cron hält den Feed aktuell.
- Callback feuert nur, wenn `is_active()` + `wc_feed_enabled`.

## 5. Risiken / Hinweise

- **Währung:** dieselbe `EUR`-Problematik wie beim Server-Tracking betrifft den Feed **nicht** (Feed nutzt `$product->get_price()` + `get_woocommerce_currency()` direkt).
- **Große Kataloge:** `collect()` lädt alle veröffentlichten Produkte; bei sehr großen Shops ggf. Batchen — vorerst nicht implementiert (Hinweis).
- **Bilder/Pflichtfelder:** Google verlangt valide `image_link`/`price`; Produkte ohne Bild/Preis werden mit-exportiert, können aber im Merchant Center beanstandet werden (kein Filter in v1).
