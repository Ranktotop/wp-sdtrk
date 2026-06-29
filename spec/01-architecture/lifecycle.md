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

2. **Cron registrieren** via `WP_SDTRK_Cron::register_cronjobs()`.

## 2. Deaktivierung — `Wp_Sdtrk_Deactivator::deactivate()`

**Cron deregistrieren** via `WP_SDTRK_Cron::unregister_cronjobs()`.

> Die DB-Tabelle bleibt bei Deaktivierung **erhalten** (kein Drop) — sie wird erst bei der Deinstallation entfernt (Abschnitt 4).

## 3. Cron — `WP_SDTRK_Cron` (`includes/class-wp-sdtrk-cron.php`)

Aktiv für die tägliche Produkt-Feed-Regenerierung der [WooCommerce-Integration](../07-woocommerce/README.md):

```php
public const HOOKS = ['wp_sdtrk_cron_generate_feed'];
```

| Methode | Aufruf | Funktion |
|---------|--------|----------|
| `register_cron_actions()` | inline in `define_public_hooks()` | bindet nur (nebenwirkungsfrei) `wp_sdtrk_cron_generate_feed` an `Wp_Sdtrk_WC_Feed::cron_regenerate` |
| `self_heal_schedule()` | Hook `plugins_loaded` | plant den Job nach (Self-Heal), wenn der Feed aktiv ist, und löscht einen verbliebenen Job, wenn er deaktiviert ist (der Schalter deaktiviert nicht das Plugin, der Deactivator greift also nicht). **Bewusst auf `plugins_loaded`**, nicht zur Datei-Include-Zeit: `is_enabled()` löst WooCommerce über `class_exists('WooCommerce')` auf, was erst nach dem Laden aller Plugins zuverlässig ist — früher ausgeführt könnte es den Zeitplan fälschlich löschen |
| `register_cronjobs()` | bei Aktivierung | plant je Hook ein **tägliches** Event (`wp_schedule_event(time(), 'daily', …)`), wenn nicht vorhanden |
| `unregister_cronjobs()` | bei Deaktivierung | `wp_clear_scheduled_hook()` je Hook |

Der Cron-Callback regeneriert nur, wenn die WooCommerce-Integration **und** `wc_feed_enabled` aktiv sind ([07 › Produkt-Feed](../07-woocommerce/product-feed.md)). Ist der Feed deaktiviert, ist der geplante Job ein No-Op.

## 4. Deinstallation — `uninstall.php`

Nach dem Standard-Guard (`WP_UNINSTALL_PLUGIN`) entfernt `wp_sdtrk_uninstall_cleanup()` **alle** persistenten Artefakte des Plugins:

| Artefakt | Entfernung |
|----------|-----------|
| Tabelle `{prefix}sdtrk_linkedin` | `DROP TABLE IF EXISTS` |
| Option `wp_sdtrk_options` (+ `wp_sdtrk_options-transients`) | `delete_option()` |
| Option `wp_sdtrk_feed_token` | `delete_option()` |
| Option `wp_sdtrk_feed_cache` | `delete_option()` |
| Post-Meta `wp_sdtrk_options` (Metabox: `wp_sdtrk_product_id`, `wp_sdtrk_bypass_consent`) | `delete_post_meta_by_key()` |
| Cron-Event `wp_sdtrk_cron_generate_feed` | `wp_clear_scheduled_hook()` |

**Multisite-fähig:** Bei `is_multisite()` läuft die Bereinigung je Site (`get_sites()` → `switch_to_blog()`/`restore_current_blog()`), sonst einmalig.
