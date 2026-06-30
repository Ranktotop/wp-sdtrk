<?php
/**
 * Unit test for Wp_Sdtrk_WC_Order_Mapper::cartLines() and
 * Wp_Sdtrk_WC_Integration::build_begin_checkout_payload(): the
 * wp_sdtrk_wc.beginCheckout object the engine seeds as a begin_checkout event on
 * the checkout page. value is the summed line total across all cart lines;
 * items carry the whole cart (see initiate-checkout.md).
 *
 * No WordPress/WooCommerce bootstrap required. Run:
 *   php tests/test-wc-begin-checkout-payload.php
 */

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-order-mapper.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-integration.php';

if (!function_exists('get_woocommerce_currency')) {
    function get_woocommerce_currency() { return 'USD'; }
}

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

/**
 * Minimal WC_Product stand-in: only get_name() is read by cartLines().
 */
class FakeBC_Product {
    private $name;
    public function __construct($name) { $this->name = $name; }
    public function get_name() { return $this->name; }
}

/**
 * Minimal WC_Cart stand-in exposing get_cart() the way cartLines() consumes it.
 * Each cart line mirrors the WooCommerce shape: data (WC_Product), quantity,
 * product_id, variation_id, line_total.
 */
class FakeBC_Cart {
    private $items;
    private $empty;
    public function __construct(array $items, bool $empty = false) {
        $this->items = $items;
        $this->empty = $empty;
    }
    public function get_cart() { return $this->items; }
    public function is_empty() { return $this->empty; }
}

$mapper      = new Wp_Sdtrk_WC_Order_Mapper();
$integration = new Wp_Sdtrk_WC_Integration();

echo "cartLines() maps WC cart lines to {id,name,qty,price}\n";
$cart = new FakeBC_Cart(array(
    // Simple product: id falls back to product_id; price = line_total / qty.
    'k1' => array(
        'data'         => new FakeBC_Product('A'),
        'quantity'     => 2,
        'product_id'   => 10,
        'variation_id' => 0,
        'line_total'   => 20.0,
    ),
    // Variation: id prefers variation_id.
    'k2' => array(
        'data'         => new FakeBC_Product('B'),
        'quantity'     => 1,
        'product_id'   => 11,
        'variation_id' => 99,
        'line_total'   => 5.5,
    ),
));
$lines = $mapper->cartLines($cart);
check('two lines',                 is_array($lines) && count($lines) === 2);
check('line0 id = product_id',     ($lines[0]['id'] ?? null) === '10');
check('line0 name',                ($lines[0]['name'] ?? null) === 'A');
check('line0 qty (int)',           ($lines[0]['qty'] ?? null) === 2);
check('line0 price = total/qty',   isset($lines[0]['price']) && abs((float) $lines[0]['price'] - 10.0) < 0.0001);
check('line1 id = variation_id',   ($lines[1]['id'] ?? null) === '99');
check('line1 price = total/qty',   isset($lines[1]['price']) && abs((float) $lines[1]['price'] - 5.5) < 0.0001);

echo "build_begin_checkout_payload() value = sum(line_total)\n";
$payload = $integration->build_begin_checkout_payload($cart);
$bc = $payload['beginCheckout'] ?? array();
check('value = 25.5',              isset($bc['value']) && abs((float) $bc['value'] - 25.5) < 0.0001);
check('value is string',           is_string($bc['value'] ?? null));
check('currency = shop currency',  ($bc['currency'] ?? null) === 'USD');
check('items carry full cart',     is_array($bc['items'] ?? null) && count($bc['items']) === 2);
check('items[0] id preserved',     ($bc['items'][0]['id'] ?? null) === '10');

echo "empty cart => zero value, empty items\n";
$emptyCart = new FakeBC_Cart(array(), true);
$emptyPayload = $integration->build_begin_checkout_payload($emptyCart);
check('value 0',                   abs((float) ($emptyPayload['beginCheckout']['value'] ?? 1) - 0.0) < 0.0001);
check('items empty',               ($emptyPayload['beginCheckout']['items'] ?? null) === array());

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
