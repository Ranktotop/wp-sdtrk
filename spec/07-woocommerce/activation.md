# 07 — Aktivierung & Registrierung

## Aktivierungs-Gate

`Wp_Sdtrk_WC_Integration` ([public/class-wp-sdtrk-wc-integration.php](../../public/class-wp-sdtrk-wc-integration.php)) ist die zentrale Entscheidungsinstanz:

- `is_active_for(bool $wc_present, bool $switch_enabled): bool` — reine Logik: `$wc_present && $switch_enabled`.
- `is_wc_active(): bool` — `class_exists('WooCommerce')`.
- `is_active(): bool` — `is_active_for(is_wc_active(), get_bool_option('wc_integration', false))`.

Sämtliche WooCommerce-Funktionalität ist inaktiv, solange `is_active()` `false` liefert.

## Redux-Schalter

In [admin/class-wp-sdtrk-admin.php](../../admin/class-wp-sdtrk-admin.php) (`wp_sdtrk_register_redux_options()`) wird die Sektion **WooCommerce** nur registriert, wenn `class_exists('WooCommerce')`:

| Option | Typ | Default | Bedeutung |
|--------|-----|---------|-----------|
| `wc_integration` | `switch` | `0` | Schaltet die WooCommerce-Integration ein. |

Ohne aktives WooCommerce ist die Sektion unsichtbar.

## Hook-Registrierung

In [includes/class-wp-sdtrk.php](../../includes/class-wp-sdtrk.php) (`define_public_hooks()`) wird eine Instanz erzeugt und über den Loader verdrahtet. Die Hooks werden **unbedingt** registriert; die Gates (`is_active()`) liegen in den Callbacks, um Lade-Reihenfolge-Probleme (WooCommerce lädt ebenfalls auf `plugins_loaded`) zu vermeiden.

| Hook | Methode | Zweck |
|------|---------|-------|
| `wp_enqueue_scripts` (Priorität 20) | `localize_order_data` | Order-Daten (`wp_sdtrk_wc.order`) auf das Engine-Skript legen — nur auf der Order-Received-Seite |

Die Priorität 20 stellt sicher, dass das Engine-Skript bereits registriert/enqueued ist (Standard-Priorität), wenn die Order-Daten daran lokalisiert werden. Das Purchase-Tracking selbst läuft anschließend in der Engine (siehe [purchase-tracking.md](purchase-tracking.md)) — es gibt keinen Order-Status-Hook und keinen AJAX-Persist mehr.

Die Tracker-Klassen (`Wp_Sdtrk_Tracker_Meta/_Ga/_Tt`) sowie `Wp_Sdtrk_WC_Order_Mapper` sind über `load_dependencies()` geladen.
