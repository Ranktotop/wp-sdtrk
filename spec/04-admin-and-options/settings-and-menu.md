# 04 — Settings, Menü & Admin-AJAX

## 1. Redux-Framework

Registrierung über `after_setup_theme` → `Wp_Sdtrk_Admin::wp_sdtrk_register_redux_options()`.

| Artefakt | Wert |
|----------|------|
| Options-Key (`wp_options`) | `wp_sdtrk_options` |
| Menü-Slug | `sdtrk_settings` |
| Menü-Titel | „Smart Serverside Tracking" |
| Save-Hook | `redux/options/wp_sdtrk_options/saved` → `after_redux_save($options, $changed)` |

## 2. Menüstruktur

```
Smart Serverside Tracking (sdtrk_settings)
├── General                 # Fingerprint, Time/Scroll/Click/Visibility-Trigger, Brandname
├── Tracking Services
│   ├── Meta                # el-facebook
│   ├── Google              # el-globe
│   ├── TikTok              # el-share
│   ├── LinkedIn            # fa-linkedin  → Button zur Mapping-Seite
│   ├── Funnelytics         # el-glass
│   ├── Mautic              # el-envelope
│   └── Matomo              # el-idea
├── Data Sources           # Digistore24-Decryption
└── Tutorials              # Doku/Videos
```

**Versteckte Zusatzseite:** `wp_sdtrk_admin_map_linkedin` (registriert via `admin_menu` → `register_page_wp_sdtrk_admin_map_linkedin`), per CSS (`display:none`) aus dem Menü ausgeblendet; Aufruf nur über den Link im LinkedIn-Tab. Template: `templates/wp-sdtrk-admin-map-linkedin.php`. Siehe [05 › LinkedIn-Mapping](../05-data-model/linkedin-mapping.md).

## 3. Metabox (Page-Level)

`after_setup_theme` → `register_redux_metabox()` registriert für den Post-Typ `page` Felder (`wp_sdtrk_product_id`, `wp_sdtrk_bypass_consent`). Details: [metabox-and-helpers.md](metabox-and-helpers.md).

## 4. Save-Nachbearbeitung — `after_redux_save()`

Nach jedem Speichern validiert der Hook u. a. die LinkedIn-Mappings gegen die aktuelle Konfiguration und entfernt ungültig gewordene Mappings (z. B. wenn ein zugehöriges Event deaktiviert wurde).

## 5. Enqueue & Localize (Admin)

`Wp_Sdtrk_Admin::enqueue_scripts()` lädt Admin-JS und stellt ein JS-Global bereit:

```php
wp_localize_script('wp_sdtrk', 'wp_sdtrk', [
  'ajax_url'      => admin_url('admin-ajax.php'),
  '_nonce'        => wp_create_nonce('security_wp-sdtrk'),
  'notice_success'=> __('…','wp-sdtrk'),
  'notice_error'  => __('…','wp-sdtrk'),
  // weitere Labels/Bestätigungstexte
]);
```

Für die LinkedIn-Mapping-Seite zusätzlich `SDTRK_Linkedin = { ajaxUrl, nonce }`.

## 6. Admin-AJAX — `Wp_Sdtrk_Admin_Ajax_Handler`

Gleiches `func`-Dispatch-Muster wie Public (Action `wp_sdtrk_handle_admin_ajax_callback`, Nonce `security_wp-sdtrk`):

| `func` | Aufgabe |
|--------|---------|
| `get_linkedin_mapping` | Mapping-Details laden (id, event, convid, rules) |
| `delete_linkedin_mapping` | Mapping löschen |

Antwort: JSON `{ state, message, mapping? }`.

## 7. Admin-Formulare — `Wp_Sdtrk_Admin_Form_Handler`

Gebunden via `admin_init` → `register_form_handler`. Verarbeitet POSTs der Mapping-Seite (jeweils eigener Nonce):

| `wp_sdtrk_form_action` | Nonce | Aufgabe |
|------------------------|-------|---------|
| `create_linkedin_mapping` | `wp_sdtrk_create_linkedin_mapping` | neues Mapping anlegen |
| `update_linkedin_mapping` | `wp_sdtrk_update_linkedin_mapping` | Mapping aktualisieren |

Validierung: Nonce, Pflichtfelder, Tag- vs. Rule-basierte Events, Eindeutigkeit (event+convid). Fehler → Admin-Notice; Erfolg → `wp_safe_redirect`.

## 8. Admin-Notices

`admin/partials/wp-sdtrk-admin-notice.php` rendert `<div id="wpsdtrk-notice-area">`; JS `wpsdtrk_show_notice(message, type)` zeigt Erfolg/Fehler (Auto-Fade). Globales Modal/Notice-Markup wird via `in_admin_footer` → `inject_global_admin_ui` injiziert.
