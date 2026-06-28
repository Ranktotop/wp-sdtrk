<?php

/**
 * File: includes/class-wp-sdtrk-cron.php
 *
 * Cron job for checking and expiring product-user entries.
 *
 * @package WP_Fluent_Community_Extreme
 */

if (! defined('ABSPATH')) {
    exit;
}

class WP_SDTRK_Cron
{

    /**
     * Names of the cron hooks. Literal to avoid a load-order dependency on
     * Wp_Sdtrk_WC_Feed; must match Wp_Sdtrk_WC_Feed::CRON_HOOK.
     */
    public const HOOKS = ['wp_sdtrk_cron_generate_feed'];

    /**
     * Bind the cron callback. Side-effect-free, so it is safe to call inline
     * at plugin construction. The schedule reconciliation lives in
     * self_heal_schedule(), which must run on a hook (not at file-include
     * time) so WooCommerce is guaranteed loaded — see that method.
     *
     * @return void
     */
    public static function register_cron_actions(): void
    {
        add_action('wp_sdtrk_cron_generate_feed', ['Wp_Sdtrk_WC_Feed', 'cron_regenerate']);
    }

    /**
     * Reconcile the daily schedule with the feed switch (self-heal).
     *
     * The Activator only runs on (re)activation, so already-active installs —
     * and installs where the feed switch is toggled without re-activating —
     * need their schedule brought in line on a normal request. Schedule while
     * the feed is enabled, and clear any dangling job once it is switched off
     * (the toggle does not deactivate the plugin, so the Deactivator never
     * fires on a mere disable).
     *
     * Must be hooked on `plugins_loaded` (or later), never called at
     * file-include time: is_enabled() resolves WooCommerce via
     * class_exists('WooCommerce'), which is only reliable once all plugins
     * are loaded. Running it earlier could wrongly clear the schedule.
     *
     * @return void
     */
    public static function self_heal_schedule(): void
    {
        if (!class_exists('Wp_Sdtrk_WC_Feed')) {
            return;
        }
        if (Wp_Sdtrk_WC_Feed::is_enabled()) {
            self::register_cronjobs();
        } else {
            self::unregister_cronjobs();
        }
    }

    /**
     * Schedule the daily event(s).
     *
     * @return void
     */
    public static function register_cronjobs(): void
    {
        foreach (self::HOOKS as $hook) {
            if (! wp_next_scheduled($hook)) {
                wp_schedule_event(time(), 'daily', $hook);
            }
        }
    }

    /**
     * Clear the scheduled event.
     *
     * @return void
     */
    public static function unregister_cronjobs(): void
    {
        foreach (self::HOOKS as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }
}
