<?php
/**
 * Regression guard for the commerce-source precedence through the real
 * localize_commerce_data() seam: with BOTH a pending add-to-cart buffer and a
 * product page, addToCart must win over viewItem; once the buffer is consumed,
 * the same product page falls through to viewItem.
 *
 * WooCommerce/WP primitives are stubbed. Run:
 *   php tests/test-wc-commerce-precedence.php
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

// Product page context for the viewItem branch.
if (!function_exists('is_product')) { function is_product() { return true; } }
if (!function_exists('get_the_ID')) { function get_the_ID() { return 24215; } }
class FakeWC_Product
{
    public function __construct(private $id) {}
    public function get_id() { return $this->id; }
    public function get_name() { return 'Product ' . $this->id; }
    public function get_price() { return '49.00'; }
}
if (!function_exists('wc_get_product')) { function wc_get_product($id) { return new FakeWC_Product($id); } }

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-order-mapper.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-integration.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$session     = $GLOBALS['__wc']->session;
$integration = new Wp_Sdtrk_WC_Integration();

echo "addToCart wins over viewItem on a product page with a pending buffer\n";
$session->set('wp_sdtrk_atc', array(array('id' => '777', 'name' => 'X', 'qty' => 1, 'price' => 9.99)));
$integration->localize_commerce_data();
$first = end($GLOBALS['__localized']);
check('localized addToCart, not viewItem', isset($first['addToCart']) && !isset($first['viewItem']));
check('buffer cleared',                    $session->get('wp_sdtrk_atc', array()) === array());

echo "same product page falls through to viewItem once the buffer is empty\n";
$integration->localize_commerce_data();
$second = end($GLOBALS['__localized']);
check('localized viewItem',                isset($second['viewItem']) && !isset($second['addToCart']));
check('viewItem prodId from product',      ($second['viewItem']['prodId'] ?? null) === '24215');

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
