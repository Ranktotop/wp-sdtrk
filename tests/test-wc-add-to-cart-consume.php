<?php
/**
 * Integration test for the consume side of add_to_cart: localize_commerce_data()
 * picks the addToCart source when a buffer is pending, localizes it onto the
 * engine and CLEARS the WC session (the once-guard: a reload does not re-fire).
 *
 * WooCommerce/WP primitives are stubbed. Run:
 *   php tests/test-wc-add-to-cart-consume.php
 */

require_once __DIR__ . '/_bootstrap.php';

if (!class_exists('WooCommerce')) { class WooCommerce {} }
if (!class_exists('WP_SDTRK_Helper_Options')) {
    class WP_SDTRK_Helper_Options
    {
        public static function get_bool_option($k, $d = false) { return $k === 'wc_integration'; }
    }
}
if (!function_exists('get_woocommerce_currency')) {
    function get_woocommerce_currency() { return 'USD'; }
}

class FakeWC_Session
{
    public array $store = array();
    public function get($k, $d = null) { return $this->store[$k] ?? $d; }
    public function set($k, $v) { $this->store[$k] = $v; }
}
class FakeWC_Container
{
    public $session;
    public $cart = null; // localize_commerce_data() reads WC()->cart; null is fine here (no checkout context).
    public function __construct($session) { $this->session = $session; }
}
$GLOBALS['__wc'] = new FakeWC_Container(new FakeWC_Session());
if (!function_exists('WC')) { function WC() { return $GLOBALS['__wc']; } }

// Capture wp_localize_script calls.
$GLOBALS['__localized'] = array();
if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $name, $data) { $GLOBALS['__localized'][] = array($handle, $name, $data); }
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
$session->set('wp_sdtrk_atc', array(
    array('id' => '24215', 'name' => 'A', 'qty' => 1, 'price' => 49.0),
));

$integration = new Wp_Sdtrk_WC_Integration();
$integration->localize_commerce_data();

echo "localize_commerce_data() consumes the add_to_cart buffer\n";
$last = end($GLOBALS['__localized']);
check('localized engine global',      $last && $last[0] === 'wp_sdtrk-engine' && $last[1] === 'wp_sdtrk_wc');
check('localized addToCart payload',  isset($last[2]['addToCart']));
check('value reflects buffer',        isset($last[2]['addToCart']['value']) && abs((float) $last[2]['addToCart']['value'] - 49.0) < 0.0001);
check('session cleared (once-guard)', $session->get('wp_sdtrk_atc', array()) === array());

// A second render with the now-empty session localizes nothing more.
$before = count($GLOBALS['__localized']);
$integration->localize_commerce_data();
check('no re-fire on reload',         count($GLOBALS['__localized']) === $before);

echo "corrupt (non-array) session value is tolerated\n";
// A foreign plugin could poison wp_sdtrk_atc with a scalar. pending_add_to_cart()
// must coerce it to [] so resolve_commerce_source() sees no pending buffer.
$session->set('wp_sdtrk_atc', 'garbage-string');
$before = count($GLOBALS['__localized']);
$integration->localize_commerce_data();
check('scalar session => no localize', count($GLOBALS['__localized']) === $before);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
