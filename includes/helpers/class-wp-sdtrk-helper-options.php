<?php
// File: includes/helpers/class-wp-sdtrk-helper-options.php

class WP_SDTRK_Helper_Options
{
    /**
     * Get a plugin option.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function get_option(string $key, $default = null)
    {
        $options = get_option('wp_sdtrk_options', []);
        return $options[$key] ?? $default;
    }

    /**
     * Retrieve the saved scroll triggers if enabled.
     *
     * @return string[]
     */
    public static function get_scroll_triggers(): array
    {
        //if not enabled return empty array
        $enabled = self::get_option('trk_scroll', false);
        if (!$enabled) {
            return [];
        }

        //if not found return empty array
        return self::get_option('trk_scroll_group_percent', []);
    }

    /**
     * Retrieve the saved time triggers if enabled.
     *
     * @return string[]
     */
    public static function get_time_triggers(): array
    {
        //if not enabled return empty array
        $enabled = self::get_option('trk_time', false);
        if (!$enabled) {
            return [];
        }

        //if not found return empty array
        return self::get_option('trk_time_group_seconds', []);
    }
}
