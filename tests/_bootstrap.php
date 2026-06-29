<?php
/**
 * Minimal stubs so pure-logic plugin classes can be unit-tested with the PHP CLI,
 * without a full WordPress/WooCommerce bootstrap.
 *
 * Only the WordPress/plugin primitives that the classes under test actually call
 * are stubbed here. Keep this list tight — if a test needs more, add it explicitly.
 */

if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}

if (!function_exists('sdtrk_log')) {
    function sdtrk_log($msg, $level = 'debug', $skip = false) { /* no-op in tests */ }
}

/*
 * Sanitization primitives. Faithful enough to be meaningful (strip tags,
 * collapse whitespace, validate emails) yet idempotent on already-clean values
 * so the live-verified server-payload tests keep producing identical payloads.
 */
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        $str = strip_tags((string) $str);
        $str = preg_replace('/[\r\n\t]+/', ' ', $str);
        $str = preg_replace('/ {2,}/', ' ', $str);
        return trim($str);
    }
}
if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        $email = strip_tags((string) $email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        $url = strip_tags((string) $url);
        return trim(str_replace(' ', '%20', $url));
    }
}
