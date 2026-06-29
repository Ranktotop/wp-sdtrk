# 01 — Verzeichnis- & Namenskonventionen

## 1. Verzeichnisstruktur

```
wp-sdtrk/
├── wp-sdtrk.php                 # Bootstrap (Plugin-Header, Autoload, Start)
├── uninstall.php               # Deinstallations-Hook (aktuell minimal)
├── index.php                   # "Silence is golden"
├── composer.json / .lock       # Dependencies (Redux, Update-Checker, installers)
├── README.txt / disclamer.txt  # WP.org-Readme + Haftungsausschluss
│
├── includes/                   # Kern, Models, Helpers, Cron
│   ├── class-wp-sdtrk.php             # Orchestrator
│   ├── class-wp-sdtrk-loader.php      # Hook-Loader
│   ├── class-wp-sdtrk-activator.php   # Aktivierung (DB, Cron)
│   ├── class-wp-sdtrk-deactivator.php # Deaktivierung
│   ├── class-wp-sdtrk-i18n.php        # Textdomain
│   ├── class-wp-sdtrk-cron.php        # Cron-Infrastruktur
│   ├── models/                        # Datenmodelle (ActiveRecord-artig)
│   │   ├── class-wp-sdtrk-model-base.php
│   │   ├── class-wp-sdtrk-model-linkedin.php
│   │   └── class-wp-sdtrk-model-linkedin-rule.php
│   └── helpers/                       # Statische Helfer
│       ├── class-wp-sdtrk-helper-base.php      # generisches DAO/ORM
│       ├── class-wp-sdtrk-helper-options.php   # Options-/Metabox-Zugriff
│       ├── class-wp-sdtrk-helper-linkedin.php  # LinkedIn-CRUD
│       └── class-wp-sdtrk-helper-event.php     # Event-/Request-Helfer (IP, Hash, cURL)
│
├── admin/                      # Backend
│   ├── class-wp-sdtrk-admin.php        # Admin-Setup, Redux, Enqueue
│   ├── class-wp-sdtrk-admin-ajax.php   # Admin-AJAX-Handler
│   ├── class-wp-sdtrk-admin-form.php   # Admin-Form-Handler (LinkedIn)
│   ├── css/  js/  partials/
│
├── public/                    # Frontend + Server-Tracker
│   ├── class-wp-sdtrk-public.php        # Enqueue/Localize, Decryption, AJAX-Bind
│   ├── class-wp-sdtrk-public-ajax.php   # validateTracker()-Dispatch
│   ├── class-wp-sdtrk-tracker-event.php # Event-Wrapper (Getter)
│   ├── class-wp-sdtrk-tracker-meta.php  # Klasse Wp_Sdtrk_Tracker_Meta (Meta CAPI)
│   ├── class-wp-sdtrk-tracker-ga.php    # GA4 Measurement Protocol
│   ├── class-wp-sdtrk-tracker-tt.php    # TikTok Events API
│   ├── class-wp-sdtrk-decryptor-ds24.php# Digistore24-Entschlüsselung
│   ├── css/
│   └── js/                              # Engine + Catcher (siehe 03)
│
├── templates/                 # PHP-Templates (LinkedIn-Mapping-Seite, Modals, Notices)
├── languages/                 # Übersetzungen
└── vendor/                    # Composer (redux, yahnis-elsts, composer)
```

## 2. Datei-Namenskonvention

`class-{slug}-{modul}[-{untermodul}].php`, alles **kebab-case**:

- `class-wp-sdtrk.php`, `class-wp-sdtrk-loader.php`
- `class-wp-sdtrk-tracker-meta.php`, `class-wp-sdtrk-helper-options.php`

## 3. Klassen-Namenskonvention

`Wp_Sdtrk_{Modul}_{Untermodul}` in **Upper_Snake_Case** (PHP-Klassennamen sind case-insensitiv, daher kommen zwei Schreibweisen gemischt vor):

- Kern/Boilerplate: `Wp_Sdtrk`, `Wp_Sdtrk_Loader`, `Wp_Sdtrk_Admin`, `Wp_Sdtrk_Public`
- Tracker: `Wp_Sdtrk_Tracker_Ga`, `Wp_Sdtrk_Tracker_Tt`, `Wp_Sdtrk_Tracker_Meta` (Alias `Wp_Sdtrk_Tracker_Fb`)
- Helpers/Models/Cron werden teils **GROSS** geschrieben: `WP_SDTRK_Helper_Options`, `WP_SDTRK_Model_Base`, `WP_SDTRK_Cron`

> ⚠️ **Inkonsistenz:** Präfix wechselt zwischen `Wp_Sdtrk_*` und `WP_SDTRK_*`. Funktional egal (case-insensitiv), aber stilistisch uneinheitlich. Die Datei `tracker-meta.php` enthält die Klasse `Wp_Sdtrk_Tracker_Meta`; der historische Name `Wp_Sdtrk_Tracker_Fb` bleibt als `class_alias` erhalten.

## 4. Hook-/Action-Namenskonvention

| Typ | Muster | Beispiel |
|-----|--------|----------|
| AJAX (eingeloggt) | `wp_ajax_wp_sdtrk_handle_{area}_ajax_callback` | `wp_ajax_wp_sdtrk_handle_public_ajax_callback` |
| AJAX (anonym) | `wp_ajax_nopriv_wp_sdtrk_handle_{area}_ajax_callback` | `wp_ajax_nopriv_wp_sdtrk_handle_public_ajax_callback` (nur Public; Admin-AJAX ist eingeloggt-only) |
| Redux-Save | `redux/options/wp_sdtrk_options/saved` | – |
| Nonce-Action | `security_wp-sdtrk` | (für Public- und Admin-AJAX gemeinsam) |

## 5. JavaScript-Handle-/Variablen-Konvention

Erzeugt durch `Wp_Sdtrk_Public::get_jsHandler($type, $name)`:

| `$type` | Ergebnis für `$name = 'meta'` | Verwendung |
|---------|-------------------------------|------------|
| `name` | `wp_sdtrk-meta` | Script-Handle (`wp_register_script`) |
| `file` | `wp-sdtrk-meta` | Dateiname (`js/wp-sdtrk-meta.js`) |
| `var` | `wp_sdtrk_meta` | JS-Global via `wp_localize_script` |

## 6. Datenbank-/Options-Namen

| Artefakt | Name |
|----------|------|
| Redux-Options-Key (`wp_options`) | `wp_sdtrk_options` |
| Redux-Settings-Slug (Menü) | `sdtrk_settings` |
| LinkedIn-Mapping-Seite (Slug) | `wp_sdtrk_admin_map_linkedin` |
| Eigene DB-Tabelle | `{wpdb->prefix}sdtrk_linkedin` (Model-`$table` = `sdtrk_linkedin`) |
| Erstpartei-Cookie-Präfix | `wpsdtrk_` (z. B. `wpsdtrk_utm_source`) |
