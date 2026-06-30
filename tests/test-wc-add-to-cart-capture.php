<?php
/**
 * Unit test for Wp_Sdtrk_WC_Integration::capture_add_to_cart(): the
 * woocommerce_add_to_cart hook that buffers added products in the WC session
 * (wp_sdtrk_atc) for the next page load to seed as an add_to_cart event.
 *
 * WooCommerce primitives are stubbed. Run:
 *   php tests/test-wc-add-to-cart-capture.php
 */

require_once __DIR__ . '/_bootstrap.php';

// Make the activation gate return true: WooCommerce present + switch on.
if (!class_exists('WooCommerce')) { class WooCommerce {} }
if (!class_exists('WP_SDTRK_Helper_Options')) {
    class WP_SDTRK_Helper_Options
    {
        // Honour a global so the wc_integration switch can be toggled per assertion.
        public static function get_bool_option($k, $d = false)
        {
            if ($k === 'wc_integration') { return $GLOBALS['__wc_switch'] ?? true; }
            return $d;
        }
    }
}
$GLOBALS['__wc_switch'] = true;
if (!function_exists('get_woocommerce_currency')) {
    function get_woocommerce_currency() { return 'USD'; }
}

// Fake WC session: array-backed get/set.
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

// wc_get_product returns a product whose id mirrors the requested id.
class FakeWC_Product
{
    public function __construct(private $id) {}
    public function get_id() { return $this->id; }
    public function get_name() { return 'Product ' . $this->id; }
    public function get_price() { return '9.99'; }
}
if (!function_exists('wc_get_product')) {
    function wc_get_product($id) { return $id ? new FakeWC_Product($id) : null; }
}

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-order-mapper.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-integration.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$integration = new Wp_Sdtrk_WC_Integration();
$session = $GLOBALS['__wc']->session;

echo "capture_add_to_cart() buffers lines in the WC session\n";
$integration->capture_add_to_cart('key1', 24215, 1);
$integration->capture_add_to_cart('key2', 777, 2);

$pending = $session->get('wp_sdtrk_atc', array());
check('two pending lines',        is_array($pending) && count($pending) === 2);
check('line 0 id (string)',       ($pending[0]['id'] ?? null) === '24215');
check('line 0 qty 1',             ($pending[0]['qty'] ?? null) === 1);
check('line 0 has float price',   is_float($pending[0]['price'] ?? null));
check('line 1 id',                ($pending[1]['id'] ?? null) === '777');
check('line 1 qty coerced to 2',  ($pending[1]['qty'] ?? null) === 2);

echo "variation id takes precedence over product id\n";
$integration->capture_add_to_cart('key3', 10, 1, 55);
$pending = $session->get('wp_sdtrk_atc', array());
check('line 2 uses variation id', ($pending[2]['id'] ?? null) === '55');

echo "missing product is skipped (no fatal, no append)\n";
$integration->capture_add_to_cart('key4', 0, 1, 0);
$pending = $session->get('wp_sdtrk_atc', array());
check('still three lines',        count($pending) === 3);

echo "inactive integration short-circuits (switch off => no append)\n";
$GLOBALS['__wc_switch'] = false;
$integration->capture_add_to_cart('key5', 24215, 1);
check('no append when inactive',  count($session->get('wp_sdtrk_atc', array())) === 3);
$GLOBALS['__wc_switch'] = true;

echo "no WC session short-circuits (no fatal, no append)\n";
$savedSession = $GLOBALS['__wc']->session;
$GLOBALS['__wc']->session = null;
$integration->capture_add_to_cart('key6', 24215, 1);
$GLOBALS['__wc']->session = $savedSession;
check('no fatal, buffer intact',  count($session->get('wp_sdtrk_atc', array())) === 3);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
