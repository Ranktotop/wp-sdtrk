<?php
/**
 * Regression guard (review fix #2): a purchase supersedes a pending add-to-cart.
 * When localize_commerce_data() resolves to the order source while the WC session
 * still holds a buffer, the buffer must be cleared so it cannot fire a phantom
 * add_to_cart for already-bought items on a later page.
 *
 * WooCommerce/WP primitives are stubbed. Run:
 *   php tests/test-wc-purchase-clears-atc.php
 */

require_once __DIR__ . '/_bootstrap.php';

if (!class_exists('WooCommerce')) { class WooCommerce {} }
if (!class_exists('WP_SDTRK_Helper_Options')) {
    class WP_SDTRK_Helper_Options
    {
        public static function get_bool_option($k, $d = false) { return $k === 'wc_integration'; }
    }
}
if (!function_exists('get_woocommerce_currency')) { function get_woocommerce_currency() { return 'USD'; } }
if (!function_exists('absint')) { function absint($n) { return abs((int) $n); } }
if (!function_exists('wp_unslash')) { function wp_unslash($v) { return $v; } }

// Order-received page context.
if (!function_exists('is_order_received_page')) { function is_order_received_page() { return true; } }
if (!function_exists('get_query_var')) { function get_query_var($k) { return $k === 'order-received' ? 4711 : ''; } }
class FakeWC_Order
{
    public function get_id() { return 4711; }
    public function get_order_key() { return 'wc_secretkey'; }
    public function get_total() { return '59.00'; }
    public function get_currency() { return 'USD'; }
    public function get_billing_email() { return 'b@example.com'; }
    public function get_billing_first_name() { return 'Ada'; }
    public function get_billing_last_name() { return 'L'; }
    public function get_items() { return array(); }
}
if (!function_exists('wc_get_order')) { function wc_get_order($id) { return $id == 4711 ? new FakeWC_Order() : null; } }
$_GET['key'] = 'wc_secretkey';

// Not a product page.
if (!function_exists('is_product')) { function is_product() { return false; } }

class FakeWC_Session
{
    public array $store = array();
    public function get($k, $d = null) { return $this->store[$k] ?? $d; }
    public function set($k, $v) { $this->store[$k] = $v; }
}
class FakeWC_Container
{
    public $session;
    public function __construct($session) { $this->session = $session; }
}
$GLOBALS['__wc'] = new FakeWC_Container(new FakeWC_Session());
if (!function_exists('WC')) { function WC() { return $GLOBALS['__wc']; } }

$GLOBALS['__localized'] = array();
if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $name, $data) { $GLOBALS['__localized'][] = $data; }
}

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-order-mapper.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-integration.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$session = $GLOBALS['__wc']->session;
$session->set('wp_sdtrk_atc', array(array('id' => '808', 'name' => 'X', 'qty' => 1, 'price' => 20.0)));

(new Wp_Sdtrk_WC_Integration())->localize_commerce_data();

echo "purchase wins and clears the pending add-to-cart buffer\n";
$last = end($GLOBALS['__localized']);
check('order source localized',       isset($last['order']));
check('not addToCart',                !isset($last['addToCart']));
check('ATC buffer cleared',           $session->get('wp_sdtrk_atc', array()) === array());

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
