# 01 — Bootstrap & Loader

## 1. Einstiegspunkt: `wp-sdtrk.php`

Die Haupt-Plugin-Datei übernimmt beim Laden durch WordPress:

1. **Sicherheits-Guard** (`if (! defined('WPINC')) die;`).
2. **Plugin-Header** (Name, Version, Autor, Textdomain) — von WP für die Plugin-Verwaltung gelesen.
3. **Versions-Konstante**: `define('WP_SDTRK_VERSION', '1.7.6')`.
4. **Composer-Autoloader**: `require_once __DIR__ . '/vendor/autoload.php'` (sofern vorhanden) — u. a. für den Update-Checker.
5. **Update-Checker** (`YahnisElsts\PluginUpdateChecker\v5\PucFactory`) gegen `https://github.com/Ranktotop/wp-sdtrk/` mit aktivierten Release-Assets.
6. **Aktivierungs-/Deaktivierungs-Hooks**: `register_activation_hook` → `activate_wp_sdtrk()`, `register_deactivation_hook` → `deactivate_wp_sdtrk()` (laden jeweils die Activator-/Deactivator-Klasse on demand).
7. **Start**: `run_wp_sdtrk()` → erzeugt `new Wp_Sdtrk()` und ruft `->run()`.

## 2. Orchestrator: `Wp_Sdtrk` (`includes/class-wp-sdtrk.php`)

Zentrale Klasse. Im Konstruktor läuft die feste Reihenfolge:

```
__construct()
  ├─ load_dependencies()
  ├─ set_locale()
  ├─ define_admin_hooks()
  └─ define_public_hooks()
```

### 2.1 `load_dependencies()`

`require_once` für alle Klassen (keine Autoloading-Konvention für Plugin-Code, nur für `vendor/`). Geladen werden u. a.:

- **Kern:** `Wp_Sdtrk_Loader`, `Wp_Sdtrk_i18n`, Redux-Framework (`vendor/redux/redux-core/framework.php`)
- **Admin:** `Wp_Sdtrk_Admin`, `Wp_Sdtrk_Admin_Ajax_Handler`, `Wp_Sdtrk_Admin_Form_Handler`
- **Public:** `Wp_Sdtrk_Public`, `Wp_Sdtrk_Public_Ajax_Handler`, `Wp_Sdtrk_Public_Form_Handler`
- **Tracker:** `Wp_Sdtrk_Tracker_Event`, `Wp_Sdtrk_Tracker_Fb` (Datei `tracker-meta.php`), `Wp_Sdtrk_Tracker_Ga`, `Wp_Sdtrk_Tracker_Tt`
- **Decryptor:** `Wp_Sdtrk_Decrypter_ds24`
- **Models:** `WP_SDTRK_Model_Base`, `WP_SDTRK_Model_Linkedin`, `WP_SDTRK_Model_Linkedin_Rule`
- **Helpers:** `WP_SDTRK_Helper_Base`, `WP_SDTRK_Helper_Options`, `WP_SDTRK_Helper_Linkedin`, `WP_SDTRK_Helper_Event`
- **Cron:** `WP_SDTRK_Cron`

Zum Schluss: `$this->loader = new Wp_Sdtrk_Loader();`.

### 2.2 `run()`

```php
public function run() { $this->loader->run(); }
```

## 3. Loader-Pattern: `Wp_Sdtrk_Loader` (`includes/class-wp-sdtrk-loader.php`)

Sammelt Hook-Registrierungen, statt sie sofort an WordPress zu geben. Drei Sammlungen: `$actions`, `$filters`, `$shortcodes`.

**API:**

```php
add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1)
add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1)
add_shortcode($tag, $component, $callback, $priority = 10, $accepted_args = 2)
```

Jeder Eintrag ist ein Array `['hook','component','callback','priority','accepted_args']`. `run()` iteriert über alle Sammlungen und ruft das jeweilige WordPress-`add_*()` mit `[$component, $callback]` als Callback auf.

**Vorteil:** Alle Registrierungen sind an einer Stelle nachvollziehbar; Komponenten bleiben testbar/entkoppelt.

## 4. Registrierte Hooks

### 4.1 i18n (`set_locale`)

| Hook | Komponente | Callback |
|------|-----------|----------|
| `plugins_loaded` | `Wp_Sdtrk_i18n` | `load_plugin_textdomain` (lädt `/languages`) |

### 4.2 Admin (`define_admin_hooks`) — Komponente `Wp_Sdtrk_Admin`

| Hook | Callback | Zweck |
|------|----------|-------|
| `admin_enqueue_scripts` | `enqueue_styles` | Admin-CSS |
| `admin_enqueue_scripts` | `enqueue_scripts` | Admin-JS + Localize |
| `wp_ajax_wp_sdtrk_handle_admin_ajax_callback` | `register_ajax_handler` | Admin-AJAX (eingeloggt) |
| `wp_ajax_nopriv_wp_sdtrk_handle_admin_ajax_callback` | `register_ajax_handler` | Admin-AJAX (anonym) |
| `after_setup_theme` | `wp_sdtrk_register_redux_options` | Redux-Optionspanel |
| `after_setup_theme` | `register_redux_metabox` | Redux-Metabox (Page-Level) |
| `redux/options/wp_sdtrk_options/saved` | `after_redux_save` | Nachbearbeitung nach Speichern |
| `in_admin_footer` | `inject_global_admin_ui` | Globales Modal/Notice-Markup |
| `admin_init` | `register_form_handler` | Form-Handler (LinkedIn-Mapping) |
| `admin_menu` | `register_page_wp_sdtrk_admin_map_linkedin` | Versteckte LinkedIn-Mapping-Seite |

### 4.3 Public (`define_public_hooks`) — Komponente `Wp_Sdtrk_Public`

| Hook | Callback | Zweck |
|------|----------|-------|
| `wp_enqueue_scripts` | `enqueue_styles` | Frontend-CSS |
| `wp_enqueue_scripts` | `enqueue_scripts` | Frontend-JS (Engine + Catcher) + Localize |
| `wp_ajax_wp_sdtrk_handle_public_ajax_callback` | `register_ajax_handler` | Public-AJAX (eingeloggt) |
| `wp_ajax_nopriv_wp_sdtrk_handle_public_ajax_callback` | `register_ajax_handler` | Public-AJAX (anonym) — Kern des Server-Trackings |
| `init` | `register_front_end_routes` | **leerer Stub** (siehe [99 Befunde](../99-findings.md)) |

Zusätzlich registriert `define_public_hooks()` die Cron-Actions über `WP_SDTRK_Cron` (siehe [lifecycle.md](lifecycle.md)).

> Der AJAX-Dispatch (`register_ajax_handler` → konkrete Handler-Klasse) ist im Detail in [02 › AJAX-Pipeline](../02-server-tracking/ajax-pipeline.md) beschrieben.
