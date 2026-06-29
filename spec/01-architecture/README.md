# 01 — Architektur

Dieser Bereich beschreibt den strukturellen Aufbau des Plugins: wie es startet, wie Hooks registriert werden, wie der Code organisiert ist und was im Lebenszyklus (Aktivierung/Deaktivierung/Cron/Deinstallation) passiert.

## Inhalt

| Datei | Thema |
|-------|-------|
| [bootstrap-and-loader.md](bootstrap-and-loader.md) | Einstiegspunkt, `Wp_Sdtrk`-Orchestrator, Loader-Pattern, registrierte Hooks |
| [directory-and-naming.md](directory-and-naming.md) | Verzeichnisstruktur, Datei-/Klassen-/Hook-Namenskonventionen |
| [lifecycle.md](lifecycle.md) | Aktivierung, Deaktivierung, Cron-Infrastruktur, Deinstallation |
| [build-and-release.md](build-and-release.md) | GitHub-Actions-Pipeline: JS-Minify, `$loadMinified`-Switch, ZIP, Release-Asset |

## Architektur in einem Satz

Klassisches **WordPress-Plugin-Boilerplate-Muster**: Ein zentraler Orchestrator (`Wp_Sdtrk`) lädt alle Abhängigkeiten, sammelt sämtliche Hook-Registrierungen in einem **Loader** und führt sie gesammelt aus. Klare Trennung in `admin/` (Backend), `public/` (Frontend + Server-Tracker) und `includes/` (Kern, Models, Helpers).

## High-Level-Diagramm

```
wp-sdtrk.php (Bootstrap)
   │  Composer-Autoload, Update-Checker, Konstante WP_SDTRK_VERSION
   │  register_activation_hook / register_deactivation_hook
   └─ run_wp_sdtrk() → new Wp_Sdtrk()->run()
          │
          ├─ load_dependencies()      ── lädt alle Klassen + Redux + new Wp_Sdtrk_Loader
          ├─ set_locale()             ── i18n (plugins_loaded → load_plugin_textdomain)
          ├─ define_admin_hooks()     ── Wp_Sdtrk_Admin + AJAX/Form/Redux-Hooks
          ├─ define_public_hooks()    ── Wp_Sdtrk_Public + Script-/AJAX-Hooks + Cron
          └─ loader->run()            ── add_action()/add_filter()/add_shortcode() für alle gesammelten Hooks
```

Details siehe [bootstrap-and-loader.md](bootstrap-and-loader.md).
