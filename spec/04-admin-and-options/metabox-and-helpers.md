# 04 — Metabox & Options-Helper

## 1. Page-Level-Metabox

Registriert über `after_setup_theme` → `Wp_Sdtrk_Admin::register_redux_metabox()` als Redux-Metabox für den Post-Typ `page`.

| Feld | Typ | Wirkung |
|------|-----|---------|
| `wp_sdtrk_product_id` | text | Produkt-ID dieser Seite (z. B. für `ViewContent`/Items) |
| `wp_sdtrk_bypass_consent` | switch | Consent für diese Seite umgehen → setzt `trkow` im Frontend ([03 › Consent](../03-browser-tracking/consent-management.md#3-force--bypass-modus)) |

Speicherung: `wp_postmeta` via `redux_post_meta('wp_sdtrk_options', $post_id)`.

## 2. `WP_SDTRK_Helper_Options`

Datei: `includes/helpers/class-wp-sdtrk-helper-options.php`. Einziger zentraler Zugriffspunkt auf Optionen (statische Methoden).

| Methode | Verhalten |
|---------|-----------|
| `get_option($key, $default=null)` | Rohwert aus `wp_sdtrk_options` |
| `get_string_option($key)` | string oder `false`, wenn leer/`"none"` |
| `get_bool_option($key, $default=false)` | `'1'→true`, `'0'→false` |
| `get_metabox_option($post_id, $key, $default=null)` | Page-Meta-Rohwert |
| `get_string_metabox_option($post_id, $key)` | Page-Meta string oder `false` |
| `get_bool_metabox_option($post_id, $key, $default=false)` | Page-Meta bool |
| `get_scroll_triggers(): int[]` | Scroll-Prozente (nur wenn `trk_scroll=1`) |
| `get_time_triggers(): int[]` | Zeit-Sekunden (nur wenn `trk_time=1`) |

Beispiel:
```php
$pixel  = WP_SDTRK_Helper_Options::get_string_option('meta_pixelid');
$debug  = WP_SDTRK_Helper_Options::get_bool_option('meta_trk_debug');
$prodId = WP_SDTRK_Helper_Options::get_string_metabox_option(get_the_ID(), 'wp_sdtrk_product_id');
```

## 3. `WP_SDTRK_Helper_Base` (generisches DAO/ORM)

Datei: `includes/helpers/class-wp-sdtrk-helper-base.php`. Abstrakte Basis für Model-bezogene Helfer (z. B. LinkedIn). Konfiguration pro Subklasse über `protected static $table` und `protected static $model_class`.

| Methode | Zweck |
|---------|-------|
| `find($criteria, $order=[], $limit=null, $offset=null)` | flexible WHERE/ORDER/LIMIT-Abfrage (Scalar = `=`, Array = `IN`) |
| `findOneBy($criteria)` | genau einen Datensatz |
| `get_by_id($id)` / `get_by_ids($ids)` | nach Primärschlüssel |
| `get_all()` | alle Datensätze |
| `normalizeDateTime($input)` | DateTime-Normalisierung |

Nutzt `$wpdb->prepare()` mit Format-Strings aus der Model-Definition (`getDbFields()`). Details zum Model: [05 › ORM & Schema](../05-data-model/orm-and-schema.md).
