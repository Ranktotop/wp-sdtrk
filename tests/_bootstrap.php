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
