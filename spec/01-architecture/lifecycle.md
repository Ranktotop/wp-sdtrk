# 01 — Lebenszyklus (Aktivierung, Deaktivierung, Cron, Deinstallation)

## 1. Aktivierung — `Wp_Sdtrk_Activator::activate()`

Ausgelöst über `register_activation_hook`. Schritte:

1. **DB-Tabelle anlegen** via `create_db_linkedin_mapping()` → `dbDelta()`:

   ```sql
   CREATE TABLE {prefix}sdtrk_linkedin (
     id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
     event   VARCHAR(255) NOT NULL,
     convid  VARCHAR(255) NOT NULL,
     rules   LONGTEXT     NOT NULL,
     PRIMARY KEY (id),
     UNIQUE KEY idx_event_convid (event, convid)
   );
   ```

   Details: [05 › ORM & Schema](../05-data-model/orm-and-schema.md).

2. **`flush_rewrite_rules()`** — obwohl aktuell keine eigenen Routen registriert werden (siehe [99 Befunde](../99-findings.md)).

3. **Cron registrieren** via `WP_SDTRK_Cron::register_cronjobs()`.

## 2. Deaktivierung — `Wp_Sdtrk_Deactivator::deactivate()`

1. Foreign-Key-Aufräumlogik vorhanden, aber **leeres Mapping** (`$map = []`) → derzeit ohne Wirkung.
2. **Cron deregistrieren** via `WP_SDTRK_Cron::unregister_cronjobs()`.

> Die DB-Tabelle bleibt bei Deaktivierung **erhalten** (kein Drop).

## 3. Cron — `WP_SDTRK_Cron` (`includes/class-wp-sdtrk-cron.php`)

Infrastruktur ist vorbereitet, aber **ohne aktive Tasks**:

```php
public const HOOKS = [];   // derzeit leer
```

| Methode | Aufruf | Funktion |
|---------|--------|----------|
| `register_cron_actions()` | in `define_public_hooks()` | würde Actions an Cron-Hooks binden |
| `register_cronjobs()` | bei Aktivierung | plant je Hook ein **stündliches** Event (`wp_schedule_event(time(), 'hourly', …)`), wenn nicht vorhanden |
| `unregister_cronjobs()` | bei Deaktivierung | `wp_clear_scheduled_hook()` je Hook |

Da `HOOKS` leer ist, werden aktuell **keine** Cron-Jobs geplant. Das README erwähnt frühere Sync-Features (CSV/Google-Sheet/Live-Feed, „hourly") — diese Cron-Nutzung ist im aktuellen Code **nicht aktiv**. Siehe [99 Befunde](../99-findings.md).

## 4. Deinstallation — `uninstall.php`

Enthält nur den Standard-Guard:

```php
if (! defined('WP_UNINSTALL_PLUGIN')) exit;
```

**Keine Datenlöschung**: Weder die Tabelle `{prefix}sdtrk_linkedin` noch die Option `wp_sdtrk_options` werden bei Deinstallation entfernt. Daten bleiben dauerhaft bestehen. Siehe [99 Befunde](../99-findings.md).
