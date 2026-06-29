<?php
/**
 * Standalone unit test for the product-feed cold-cache stampede guard.
 *
 * Run: php tests/test-feed-cache-lock.php
 *
 * get_or_build_cached() must:
 *   - return the cache untouched when warm (no build, no lock),
 *   - on a cold cache with a free lock: build once under a transient lock,
 *     persist + return it, and release the lock,
 *   - on a cold cache with the lock already held: return 503 and NOT build
 *     (so parallel requests don't all run the full live generation).
 *
 * generate() stays cheap here: wc_get_products() is intentionally NOT defined,
 * so collect() returns [] and generate() renders an empty (but valid) feed.
 */

require_once __DIR__ . '/_bootstrap.php';

$GLOBALS['__opts'] = [];
$GLOBALS['__trans'] = [];
$GLOBALS['__updates'] = [];

if (!function_exists('get_option')) {
    function get_option($k, $default = false) { return array_key_exists($k, $GLOBALS['__opts']) ? $GLOBALS['__opts'][$k] : $default; }
}
if (!function_exists('update_option')) {
    function update_option($k, $v, $autoload = null) { $GLOBALS['__opts'][$k] = $v; $GLOBALS['__updates'][] = $k; return true; }
}
if (!function_exists('get_transient')) {
    function get_transient($k) { return array_key_exists($k, $GLOBALS['__trans']) ? $GLOBALS['__trans'][$k] : false; }
}
if (!function_exists('set_transient')) {
    function set_transient($k, $v, $ttl = 0) { $GLOBALS['__trans'][$k] = $v; return true; }
}
if (!function_exists('delete_transient')) {
    function delete_transient($k) { unset($GLOBALS['__trans'][$k]); return true; }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($k = '') { return 'StubSite'; }
}
if (!function_exists('get_site_url')) {
    function get_site_url() { return 'http://shop'; }
}

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-feed.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$feed = new Wp_Sdtrk_WC_Feed();

echo "warm cache\n";
$GLOBALS['__opts'][Wp_Sdtrk_WC_Feed::CACHE_OPTION] = '<rss>cached</rss>';
$GLOBALS['__updates'] = [];
$r = $feed->get_or_build_cached();
check('warm => 200',                  $r['code'] === 200);
check('warm => returns cache',        $r['body'] === '<rss>cached</rss>');
check('warm => no build',             !in_array(Wp_Sdtrk_WC_Feed::CACHE_OPTION, $GLOBALS['__updates'], true));
check('warm => no lock left',         get_transient(Wp_Sdtrk_WC_Feed::LOCK_TRANSIENT) === false);

echo "cold cache, free lock\n";
$GLOBALS['__opts'] = [];
$GLOBALS['__trans'] = [];
$GLOBALS['__updates'] = [];
$r = $feed->get_or_build_cached();
check('cold/free => 200',             $r['code'] === 200);
check('cold/free => non-empty body',  is_string($r['body']) && strpos($r['body'], 'Product Feed') !== false);
check('cold/free => cache persisted', in_array(Wp_Sdtrk_WC_Feed::CACHE_OPTION, $GLOBALS['__updates'], true));
check('cold/free => lock released',   get_transient(Wp_Sdtrk_WC_Feed::LOCK_TRANSIENT) === false);

echo "cold cache, lock held\n";
$GLOBALS['__opts'] = [];
$GLOBALS['__trans'] = [Wp_Sdtrk_WC_Feed::LOCK_TRANSIENT => 1];
$GLOBALS['__updates'] = [];
$r = $feed->get_or_build_cached();
check('cold/held => 503',             $r['code'] === 503);
check('cold/held => empty body',      $r['body'] === '');
check('cold/held => no build',        !in_array(Wp_Sdtrk_WC_Feed::CACHE_OPTION, $GLOBALS['__updates'], true));

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
