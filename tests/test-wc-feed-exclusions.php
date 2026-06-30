<?php
/**
 * Standalone unit test for the product-feed exclusion list helpers.
 *
 * Run: php tests/test-wc-feed-exclusions.php
 *
 * get_excluded_ids() must read the wp_sdtrk_feed_excluded option and return a
 * clean int list (intval, drop non-positive, dedupe), tolerating a missing or
 * corrupt (non-array / scalar) stored value by returning [].
 *
 * set_excluded_ids() must persist a sanitized int list AND invalidate the feed
 * cache (delete_option(CACHE_OPTION)) so the change is reflected on the next
 * feed request.
 *
 * is_excluded() is a strict-membership convenience over get_excluded_ids().
 */

require_once __DIR__ . '/_bootstrap.php';

$GLOBALS['__opts']    = [];
$GLOBALS['__deleted'] = [];

if (!function_exists('get_option')) {
    function get_option($k, $default = false) { return array_key_exists($k, $GLOBALS['__opts']) ? $GLOBALS['__opts'][$k] : $default; }
}
if (!function_exists('update_option')) {
    function update_option($k, $v, $autoload = null) { $GLOBALS['__opts'][$k] = $v; return true; }
}
if (!function_exists('delete_option')) {
    function delete_option($k) { unset($GLOBALS['__opts'][$k]); $GLOBALS['__deleted'][] = $k; return true; }
}

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-feed.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$feed = new Wp_Sdtrk_WC_Feed();
$EX   = Wp_Sdtrk_WC_Feed::EXCLUDED_OPTION;
$CA   = Wp_Sdtrk_WC_Feed::CACHE_OPTION;

echo "get_excluded_ids() — empty / missing\n";
$GLOBALS['__opts'] = [];
check('missing option => []',            $feed->get_excluded_ids() === []);

echo "get_excluded_ids() — sanitization\n";
$GLOBALS['__opts'][$EX] = ['10', 10, '11', 0, -5, '0', 'abc', 12];
$ids = $feed->get_excluded_ids();
check('all ints',                        $ids === array_map('intval', $ids));
check('dedup + drop non-positive',       $ids === [10, 11, 12]);

echo "get_excluded_ids() — corrupt scalar value\n";
$GLOBALS['__opts'][$EX] = 'not-an-array';
check('scalar => []',                    $feed->get_excluded_ids() === []);

echo "set_excluded_ids() — persist + sanitize\n";
$GLOBALS['__opts']    = [];
$GLOBALS['__deleted'] = [];
$feed->set_excluded_ids(['7', 7, 9, -1, 0, 'x']);
check('persisted to EXCLUDED_OPTION',    ($GLOBALS['__opts'][$EX] ?? null) === [7, 9]);
check('cache option deleted',            in_array($CA, $GLOBALS['__deleted'], true));

echo "is_excluded()\n";
$GLOBALS['__opts'][$EX] = [7, 9];
check('member => true',                  $feed->is_excluded(7) === true);
check('non-member => false',             $feed->is_excluded(8) === false);
check('strict (no string coercion)',     $feed->is_excluded(0) === false);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
