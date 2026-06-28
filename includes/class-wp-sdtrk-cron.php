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

    public static function register_cron_actions(): void
    {
        add_action('wp_sdtrk_cron_generate_feed', ['Wp_Sdtrk_WC_Feed', 'cron_regenerate']);

        // Self-heal scheduling for already-active installs (Activator only runs
        // on (re)activation). Only schedule while the feed is actually enabled.
        if (class_exists('Wp_Sdtrk_WC_Feed') && Wp_Sdtrk_WC_Feed::is_enabled()) {
            self::register_cronjobs();
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
