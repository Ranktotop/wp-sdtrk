# 04 — Admin & Optionen

Beschreibt das Backend: Einstellungs-UI (Redux), die vollständige Options-Referenz, die Page-Level-Metabox und den Options-Zugriff.

## Inhalt

| Datei | Thema |
|-------|-------|
| [settings-and-menu.md](settings-and-menu.md) | Redux-Framework, Menüstruktur, Save-Hook, Localize, Admin-AJAX |
| [option-reference.md](option-reference.md) | Alle Optionen je Plattform (Feldname → Bedeutung) |
| [metabox-and-helpers.md](metabox-and-helpers.md) | Page-Level-Metabox + `WP_SDTRK_Helper_Options` / `WP_SDTRK_Helper_Base` |

## Auf einen Blick

- **Framework:** Redux Framework (`vendor/redux`).
- **Speicherort:** Option `wp_sdtrk_options` in `wp_options`; Page-Meta via `redux_post_meta('wp_sdtrk_options', $post_id)` in `wp_postmeta`.
- **Menü:** „Smart Serverside Tracking" (Slug `sdtrk_settings`) mit Sektionen General / Tracking Services (Meta, Google, TikTok, LinkedIn, Funnelytics, Mautic, Matomo) / Data Sources / Tutorials.
- **Versteckte Seite:** LinkedIn Conversion-Mapping (`wp_sdtrk_admin_map_linkedin`), per CSS ausgeblendet, nur über den LinkedIn-Tab erreichbar.
- **Zugriff im Code:** ausschließlich über `WP_SDTRK_Helper_Options` (nie direkt `get_option`).
