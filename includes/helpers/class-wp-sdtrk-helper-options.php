<?php
// File: includes/helpers/class-wp-sdtrk-helper-options.php

class WP_SDTRK_Helper_Options
{
    /**
     * Get a metabox option from a specific post/page.
     *
     * @param  int    $post_id
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function get_metabox_option(int $post_id, string $key, mixed $default = null): string|array|bool|null
    {
        $meta = get_post_meta($post_id, 'wp_sdtrk_options', true);

        if (!is_array($meta)) {
            return $default;
        }

        return $meta[$key] ?? $default;
    }

    /**
     * Get a plugin metabox string option. Empty strings are treated as non-existing.
     * Returns false if the option is not found or is an empty string.
     *
     * @param  int    $post_id
     * @param  string $key
     * @param  string  $default
     * @return mixed
     */
    public static function get_string_metabox_option(int $post_id, string $key): string|false
    {
        $value = self::get_metabox_option($post_id, $key, "");
        return is_string($value) && trim($value) !== '' ? $value : false;
    }

    /**
     * Get a plugin metabox bool option. Returns default if the option is not found or is not '1' or '0'.
     *
     * @param  int    $post_id
     * @param  string $key
     * @param  bool  $default
     * @return mixed
     */
    public static function get_bool_metabox_option(int $post_id, string $key, bool $default = false): bool
    {
        $default_str = $default ? '1' : '0';
        $value = self::get_metabox_option($post_id, $key, $default_str);
        return $value === '1' ? true : ($value === '0' ? false : $default);
    }


    /**
     * Get a plugin option.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function get_option(string $key, mixed $default = null): string|array|bool|null
    {
        $options = get_option('wp_sdtrk_options', []);
        return $options[$key] ?? $default;
    }

    /**
     * Get a plugin string option. Empty strings are treated as non-existing.
     * Returns false if the option is not found or is an empty string.
     *
     * @param  string $key
     * @param  string  $default
     * @return mixed
     */
    public static function get_string_option(string $key): string|false
    {
        $value = self::get_option($key, "");
        return is_string($value) && trim($value) !== '' && $value !== "none" ? $value : false;
    }

    /**
     * Get a plugin bool option. Returns default if the option is not found or is not '1' or '0'.
     *
     * @param  string $key
     * @param  bool  $default
     * @return mixed
     */
    public static function get_bool_option(string $key, bool $default = false): bool
    {
        $default_str = $default ? '1' : '0';
        $value = self::get_option($key, $default_str);
        return $value === '1' ? true : ($value === '0' ? false : $default);
    }

    /**
     * Retrieve the saved scroll triggers if enabled.
     *
     * @return int[]
     */
    public static function get_scroll_triggers(): array
    {
        //if not enabled return empty array
        $enabled = self::get_option('trk_scroll', false);
        if (!$enabled) {
            return [];
        }
        $string_triggers = self::get_option('trk_scroll_group_percent', []);

        //convert to int and return
        return array_map('intval', $string_triggers);
    }

    /**
     * Retrieve the saved time triggers if time triggers are enabled.
     *
     * @return int[]
     */
    public static function get_time_triggers(): array
    {
        //if not enabled return empty array
        $enabled = self::get_option('trk_time', false);
        if (!$enabled) {
            return [];
        }
        $string_triggers = self::get_option('trk_time_group_seconds', []);

        //convert to int and return
        return array_map('intval', $string_triggers);
    }
}
