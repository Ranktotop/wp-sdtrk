<?php
/**
 * Standalone unit test for the product-feed token rotation.
 *
 * Run: php tests/test-feed-token.php
 *
 * rotate_token() must mint a fresh token, persist it to the TOKEN_OPTION, and
 * return it; calling it twice must yield two different tokens. get_token()
 * must then return the rotated value.
 */

require_once __DIR__ . '/_bootstrap.php';

$GLOBALS['__opts'] = [];
$GLOBALS['__pwcount'] = 0;

if (!function_exists('get_option')) {
    function get_option($k, $default = false) { return $GLOBALS['__opts'][$k] ?? $default; }
}
if (!function_exists('update_option')) {
    function update_option($k, $v, $autoload = null) { $GLOBALS['__opts'][$k] = $v; return true; }
}
if (!function_exists('wp_generate_password')) {
    // Deterministic but varying: a 32-char token that differs per call.
    function wp_generate_password($length = 12, $special = true) {
        $GLOBALS['__pwcount']++;
        return str_pad('tok' . $GLOBALS['__pwcount'], $length, 'x');
    }
}

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-feed.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$feed = new Wp_Sdtrk_WC_Feed();

echo "rotate_token()\n";
$first  = $feed->rotate_token();
check('returns a 32-char token',          is_string($first) && strlen($first) === 32);
check('persisted to option',              ($GLOBALS['__opts']['wp_sdtrk_feed_token'] ?? null) === $first);
check('get_token reflects rotation',      $feed->get_token() === $first);

$second = $feed->rotate_token();
check('second rotation differs',          $second !== $first);
check('option now holds latest',          ($GLOBALS['__opts']['wp_sdtrk_feed_token'] ?? null) === $second);
check('old token no longer valid',        $feed->verify_token($first) === false);
check('new token valid',                  $feed->verify_token($second) === true);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
