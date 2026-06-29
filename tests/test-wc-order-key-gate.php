<?php
/**
 * Security regression test: current_received_order() must validate the order key
 * before the order (and thus its buyer PII) is exposed to the page. Without the
 * key check the order-received endpoint resolves by id alone and the localized
 * PII is harvestable by id enumeration.
 *
 * Run:  php tests/test-wc-order-key-gate.php
 */

require_once __DIR__ . '/_bootstrap.php';

if (!function_exists('is_order_received_page')) { function is_order_received_page() { return true; } }
if (!function_exists('get_query_var')) { function get_query_var($k) { return 4711; } }
if (!function_exists('absint')) { function absint($v) { return abs((int) $v); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v) { return is_string($v) ? trim($v) : ''; } }
if (!function_exists('wp_unslash')) { function wp_unslash($v) { return $v; } }

class FakeWC_Order
{
    public function get_id() { return 4711; }
    public function get_order_key() { return 'wc_order_ABC123'; }
}
if (!function_exists('wc_get_order')) { function wc_get_order($id) { return new FakeWC_Order(); } }

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-integration.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$integration = new Wp_Sdtrk_WC_Integration();
$ref = new ReflectionMethod('Wp_Sdtrk_WC_Integration', 'current_received_order');
$ref->setAccessible(true);

echo "current_received_order() order-key gate\n";

$_GET['key'] = 'wc_order_ABC123';
check('valid key => order resolved', $ref->invoke($integration) instanceof FakeWC_Order);

$_GET['key'] = 'wc_order_WRONG';
check('wrong key => null', $ref->invoke($integration) === null);

unset($_GET['key']);
check('missing key => null', $ref->invoke($integration) === null);

$_GET['key'] = '';
check('empty key => null', $ref->invoke($integration) === null);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
