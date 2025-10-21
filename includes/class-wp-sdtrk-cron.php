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
     * Name of the cron hook.
     */
    public const HOOKS = [];

    public static function register_cron_actions(): void
    {
        //add_action('wp_sdtrk_cron_check_expirations', [self::class, 'check_expirations']);
    }

    /**
     * Schedule the hourly event.
     *
     * @return void
     */
    public static function register_cronjobs(): void
    {
        foreach (self::HOOKS as $hook) {
            if (! wp_next_scheduled($hook)) {
                wp_schedule_event(time(), 'hourly', $hook);
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
