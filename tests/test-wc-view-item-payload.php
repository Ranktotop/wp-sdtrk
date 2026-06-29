<?php
/**
 * Unit test for the ViewItem data the engine consumes on a product page:
 *   - Wp_Sdtrk_WC_Order_Mapper::productLine() (single product -> one line)
 *   - Wp_Sdtrk_WC_Integration::build_view_item_payload() (wp_sdtrk_wc.viewItem)
 *   - Wp_Sdtrk_WC_Integration::resolve_commerce_source() (precedence resolver)
 *
 * No WordPress/WooCommerce bootstrap required. Run:
 *   php tests/test-wc-view-item-payload.php
 *
 * This is the seam joining the product, the mapper and the engine ingestion —
 * a field rename here would silently break browser + server ViewItem.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-order-mapper.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-integration.php';

if (!function_exists('get_woocommerce_currency')) {
    function get_woocommerce_currency() { return 'USD'; }
}

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

$mapper  = new Wp_Sdtrk_WC_Order_Mapper();
$product = new FakeWC_Product(24215, 'Hybridlehrgang', '49.00');

echo "productLine() shape\n";
$line = $mapper->productLine($product, 1);
check('id is string',            ($line['id'] ?? null) === '24215');
check('name',                    ($line['name'] ?? null) === 'Hybridlehrgang');
check('qty defaults to 1',       ($line['qty'] ?? null) === 1);
check('price is float',          is_float($line['price'] ?? null) && abs($line['price'] - 49.00) < 0.0001);

echo "build_view_item_payload() shape\n";
$integration = new Wp_Sdtrk_WC_Integration();
$payload = $integration->build_view_item_payload($product);
$vi = $payload['viewItem'] ?? [];
check('prodId is string id',     ($vi['prodId'] ?? null) === '24215');
check('name',                    ($vi['name'] ?? null) === 'Hybridlehrgang');
check('value reflects price',    isset($vi['value']) && abs((float) $vi['value'] - 49.00) < 0.0001);
check('currency = shop currency',($vi['currency'] ?? null) === 'USD');
check('items is single line',    is_array($vi['items'] ?? null) && count($vi['items']) === 1);
check('items[0] id',             ($vi['items'][0]['id'] ?? null) === '24215');

echo "resolve_commerce_source() precedence (order > addToCart > viewItem)\n";
// resolve_commerce_source(bool orderReceived, bool hasPendingAtc, bool isProduct)
check('order received wins',     Wp_Sdtrk_WC_Integration::resolve_commerce_source(true,  true,  true)  === 'order');
check('addToCart over viewItem', Wp_Sdtrk_WC_Integration::resolve_commerce_source(false, true,  true)  === 'addToCart');
check('viewItem on product',     Wp_Sdtrk_WC_Integration::resolve_commerce_source(false, false, true)  === 'viewItem');
check('none otherwise',          Wp_Sdtrk_WC_Integration::resolve_commerce_source(false, false, false) === 'none');

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
