<?php
/**
 * Regression guard for the commerce-source precedence through the real
 * localize_commerce_data() seam. Precedence: order > beginCheckout > addToCart
 * > viewItem.
 *  - With a pending add-to-cart buffer AND a product page, addToCart wins over
 *    viewItem; once the buffer is consumed, the same product page falls through
 *    to viewItem.
 *  - On the checkout page (non-empty cart), beginCheckout wins even over a
 *    pending add-to-cart buffer, and drops that buffer (a purchase-like
 *    supersede) so it cannot fire a phantom add_to_cart later.
 *  - An empty cart on the checkout page yields no commerce event.
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
class FakeWC_Cart
{
    public function get_cart() { return $GLOBALS['__cart_items']; }
    public function is_empty() { return count($GLOBALS['__cart_items']) === 0; }
}
class FakeWC_Container
{
    public $session;
    public $cart;
    public function __construct($session, $cart) { $this->session = $session; $this->cart = $cart; }
}
$GLOBALS['__cart_items'] = array();
$GLOBALS['__wc'] = new FakeWC_Container(new FakeWC_Session(), new FakeWC_Cart());
if (!function_exists('WC')) { function WC() { return $GLOBALS['__wc']; } }

$GLOBALS['__localized'] = array();
if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $name, $data) { $GLOBALS['__localized'][] = $data; }
}

// Page-context toggles (default: product page, not checkout).
$GLOBALS['__is_product']  = true;
$GLOBALS['__is_checkout'] = false;
if (!function_exists('is_product'))  { function is_product()  { return (bool) $GLOBALS['__is_product']; } }
if (!function_exists('is_checkout')) { function is_checkout() { return (bool) $GLOBALS['__is_checkout']; } }
if (!function_exists('get_the_ID'))  { function get_the_ID()  { return 24215; } }
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

echo "beginCheckout wins over a pending add-to-cart on the checkout page\n";
$GLOBALS['__is_checkout'] = true;
$GLOBALS['__cart_items']  = array(
    'k1' => array('data' => new FakeWC_Product(501), 'quantity' => 2, 'product_id' => 501, 'variation_id' => 0, 'line_total' => 30.0),
);
$session->set('wp_sdtrk_atc', array(array('id' => '777', 'name' => 'X', 'qty' => 1, 'price' => 9.99)));
$integration->localize_commerce_data();
$third = end($GLOBALS['__localized']);
check('localized beginCheckout, not addToCart', isset($third['beginCheckout']) && !isset($third['addToCart']));
check('beginCheckout value = 30',               abs((float) ($third['beginCheckout']['value'] ?? 0) - 30.0) < 0.0001);
check('pending buffer dropped on checkout win',  $session->get('wp_sdtrk_atc', array()) === array());

echo "empty cart on the checkout page yields no commerce event\n";
$GLOBALS['__cart_items'] = array();
$GLOBALS['__is_product'] = false; // genuine checkout page is not a product page
$before = count($GLOBALS['__localized']);
$integration->localize_commerce_data();
check('nothing localized for empty checkout', count($GLOBALS['__localized']) === $before);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
