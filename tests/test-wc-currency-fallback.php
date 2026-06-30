<?php
/**
 * Unit test (F4): the currency fallback in the new commerce payload builders.
 * When get_woocommerce_currency() is unavailable, build_view_item_payload() and
 * build_add_to_cart_payload() must emit currency '' so the catchers apply their
 * own EUR fallback. This file deliberately does NOT define
 * get_woocommerce_currency() (a separate process from the other payload tests).
 *
 * Run:  php tests/test-wc-currency-fallback.php
 */

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-order-mapper.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-integration.php';

// NOTE: get_woocommerce_currency() and wc_get_price_to_display() are intentionally
// left undefined to exercise the function_exists() fallbacks.

class FakeWC_Product
{
    public function __construct(private $id, private $name, private $price) {}
    public function get_id() { return $this->id; }
    public function get_name() { return $this->name; }
    public function get_price() { return $this->price; }
}

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$integration = new Wp_Sdtrk_WC_Integration();

echo "build_view_item_payload(): currency '' when WC currency fn absent\n";
$vi = $integration->build_view_item_payload(new FakeWC_Product(24215, 'X', '49.00'));
check('viewItem currency = empty string',  ($vi['viewItem']['currency'] ?? null) === '');
check('viewItem value still set',          isset($vi['viewItem']['value']) && abs((float) $vi['viewItem']['value'] - 49.00) < 0.0001);

echo "build_add_to_cart_payload(): currency '' when WC currency fn absent\n";
$atc = $integration->build_add_to_cart_payload(array(
    array('id' => '1', 'name' => 'A', 'qty' => 1, 'price' => 10.0),
));
check('addToCart currency = empty string', ($atc['addToCart']['currency'] ?? null) === '');

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
