<?php

/**
 * WooCommerce integration entry point.
 *
 * Central activation gate for WooCommerce conversion tracking. The whole
 * integration is inert unless WooCommerce is installed AND the `wc_integration`
 * Redux switch is enabled.
 */
class Wp_Sdtrk_WC_Integration
{
    /**
     * Pure activation predicate (unit-testable, no WP/WC dependencies).
     *
     * @param bool $wc_present       WooCommerce class is loaded.
     * @param bool $switch_enabled   The wc_integration Redux switch is on.
     * @return bool
     */
    public static function is_active_for(bool $wc_present, bool $switch_enabled): bool
    {
        return $wc_present && $switch_enabled;
    }

    /**
     * Whether WooCommerce itself is available.
     *
     * @return bool
     */
    public static function is_wc_active(): bool
    {
        return class_exists('WooCommerce');
    }

    /**
     * Whether the WooCommerce tracking integration is active
     * (WooCommerce present AND the Redux switch enabled).
     *
     * @return bool
     */
    public static function is_active(): bool
    {
        return self::is_active_for(
            self::is_wc_active(),
            WP_SDTRK_Helper_Options::get_bool_option('wc_integration', false)
        );
    }
}
