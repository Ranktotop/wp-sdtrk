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

Aktiv für die tägliche Produkt-Feed-Regenerierung der [WooCommerce-Integration](../07-woocommerce/README.md):

```php
public const HOOKS = ['wp_sdtrk_cron_generate_feed'];
```

| Methode | Aufruf | Funktion |
|---------|--------|----------|
| `register_cron_actions()` | in `define_public_hooks()` | bindet `wp_sdtrk_cron_generate_feed` an `Wp_Sdtrk_WC_Feed::cron_regenerate`; plant den Job nach (Self-Heal), wenn der Feed aktiv ist, und löscht einen verbliebenen Job, wenn er deaktiviert ist (der Schalter deaktiviert nicht das Plugin, der Deactivator greift also nicht) |
| `register_cronjobs()` | bei Aktivierung | plant je Hook ein **tägliches** Event (`wp_schedule_event(time(), 'daily', …)`), wenn nicht vorhanden |
| `unregister_cronjobs()` | bei Deaktivierung | `wp_clear_scheduled_hook()` je Hook |

Der Cron-Callback regeneriert nur, wenn die WooCommerce-Integration **und** `wc_feed_enabled` aktiv sind ([07 › Produkt-Feed](../07-woocommerce/product-feed.md)). Ist der Feed deaktiviert, ist der geplante Job ein No-Op.

## 4. Deinstallation — `uninstall.php`

Enthält nur den Standard-Guard:

```php
if (! defined('WP_UNINSTALL_PLUGIN')) exit;
```

**Keine Datenlöschung**: Weder die Tabelle `{prefix}sdtrk_linkedin` noch die Option `wp_sdtrk_options` werden bei Deinstallation entfernt. Daten bleiben dauerhaft bestehen. Siehe [99 Befunde](../99-findings.md).
