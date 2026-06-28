# 05 — Datenmodell

Beschreibt die Persistenzschicht: das ActiveRecord-artige Base-Model, das DB-Schema und das einzige aktuell genutzte fachliche Modell — LinkedIn-Mapping.

## Inhalt

| Datei | Thema |
|-------|-------|
| [orm-and-schema.md](orm-and-schema.md) | `WP_SDTRK_Model_Base` (CRUD, Casts) + DB-Schema |
| [linkedin-mapping.md](linkedin-mapping.md) | LinkedIn-Mappings, Rules, CRUD-Helper, Admin-UI-Fluss |

## Überblick

- **Pattern:** Abstrakte Basisklasse `WP_SDTRK_Model_Base` (ActiveRecord-artig) + statischer DAO-Helper `WP_SDTRK_Helper_Base`.
- **Tabellen:** genau **eine** eigene Tabelle — `{prefix}sdtrk_linkedin`.
- **Fachmodelle:** `WP_SDTRK_Model_Linkedin` (+ Value-Object `WP_SDTRK_Model_Linkedin_Rule`).
- Restliche Konfiguration liegt in WordPress-Optionen/Metaboxen ([04](../04-admin-and-options/README.md)), nicht in eigenen Tabellen.
