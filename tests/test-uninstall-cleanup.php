<?php
/**
 * Standalone unit test for the uninstall cleanup routine.
 *
 * No WordPress bootstrap required. Run:
 *   php tests/test-uninstall-cleanup.php
 *
 * Verifies that wp_sdtrk_uninstall_cleanup() removes every persistent artifact
 * the plugin writes: the linkedin table, the plugin options, the feed token /
 * cache options, the Redux transients option, the per-post metabox meta, and
 * the daily feed cron — on a single site (non-multisite path).
 */

require_once __DIR__ . '/_bootstrap.php';

$GLOBALS['__calls'] = [
    'delete_option'           => [],
    'delete_post_meta_by_key' => [],
    'clear_hook'              => [],
    'queries'                 => [],
];

if (!defined('WP_UNINSTALL_PLUGIN')) {
    define('WP_UNINSTALL_PLUGIN', true);
}

if (!function_exists('delete_option')) {
    function delete_option($k) { $GLOBALS['__calls']['delete_option'][] = $k; return true; }
}
if (!function_exists('delete_post_meta_by_key')) {
    function delete_post_meta_by_key($k) { $GLOBALS['__calls']['delete_post_meta_by_key'][] = $k; return true; }
}
if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($h) { $GLOBALS['__calls']['clear_hook'][] = $h; }
}
if (!function_exists('is_multisite')) {
    function is_multisite() { return false; }
}

// Minimal $wpdb stub recording DROP TABLE queries.
class WPDB_Stub
{
    public $prefix = 'wp_';
    public function query($sql) { $GLOBALS['__calls']['queries'][] = $sql; return true; }
}
$GLOBALS['wpdb'] = new WPDB_Stub();

require dirname(__DIR__) . '/uninstall.php';

$fails = 0;
function check($label, $cond)
{
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$c = $GLOBALS['__calls'];

echo "options\n";
check('deletes wp_sdtrk_options',            in_array('wp_sdtrk_options', $c['delete_option'], true));
check('deletes wp_sdtrk_feed_token',         in_array('wp_sdtrk_feed_token', $c['delete_option'], true));
check('deletes wp_sdtrk_feed_cache',         in_array('wp_sdtrk_feed_cache', $c['delete_option'], true));
check('deletes redux transients option',     in_array('wp_sdtrk_options-transients', $c['delete_option'], true));

echo "post meta\n";
check('deletes wp_sdtrk_options post meta',  in_array('wp_sdtrk_options', $c['delete_post_meta_by_key'], true));

echo "cron\n";
check('clears feed cron hook',               in_array('wp_sdtrk_cron_generate_feed', $c['clear_hook'], true));

echo "table\n";
check('drops sdtrk_linkedin table',          count(array_filter($c['queries'], function ($q) {
    return strpos($q, 'DROP TABLE') !== false && strpos($q, 'wp_sdtrk_linkedin') !== false;
})) === 1);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
