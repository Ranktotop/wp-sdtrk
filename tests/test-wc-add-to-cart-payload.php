<?php
/**
 * Unit test for Wp_Sdtrk_WC_Integration::build_add_to_cart_payload(): the
 * wp_sdtrk_wc.addToCart object the engine seeds as an add_to_cart event. value
 * is the summed line total (price*qty) across all buffered lines; items carry
 * the whole buffer (multi-add merged into one event — see
 * view-item-and-add-to-cart.md).
 *
 * No WordPress/WooCommerce bootstrap required. Run:
 *   php tests/test-wc-add-to-cart-payload.php
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

$integration = new Wp_Sdtrk_WC_Integration();

echo "build_add_to_cart_payload() value = sum(price*qty)\n";
$pending = array(
    array('id' => '1', 'name' => 'A', 'qty' => 2, 'price' => 10.0),
    array('id' => '2', 'name' => 'B', 'qty' => 1, 'price' => 5.5),
);
$payload = $integration->build_add_to_cart_payload($pending);
$atc = $payload['addToCart'] ?? array();
check('value = 25.5',            isset($atc['value']) && abs((float) $atc['value'] - 25.5) < 0.0001);
check('currency = shop currency',($atc['currency'] ?? null) === 'USD');
check('items carry full buffer', is_array($atc['items'] ?? null) && count($atc['items']) === 2);
check('items[0] id preserved',   ($atc['items'][0]['id'] ?? null) === '1');
check('items[1] qty preserved',  ($atc['items'][1]['qty'] ?? null) === 1);

echo "empty buffer => zero value, empty items\n";
$empty = $integration->build_add_to_cart_payload(array());
check('value 0',                 abs((float) ($empty['addToCart']['value'] ?? 1) - 0.0) < 0.0001);
check('items empty',             ($empty['addToCart']['items'] ?? null) === array());

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
