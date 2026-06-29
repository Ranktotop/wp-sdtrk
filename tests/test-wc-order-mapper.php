<?php
/**
 * Standalone unit test for Wp_Sdtrk_WC_Order_Mapper::lineItems().
 *
 * No WordPress/WooCommerce bootstrap required. Run:
 *   php tests/test-wc-order-mapper.php
 *
 * Verifies that a WooCommerce order's items are translated into the structured
 * per-line list the engine consumes on the order-received page (id/name/qty/
 * unit-price), for all cart positions.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-order-mapper.php';

// ---- Fake WooCommerce order/item (duck-typed) ----
class FakeWC_Item
{
    public function __construct(private $pid, private $name, private $qty, private $total) {}
    public function get_product_id() { return $this->pid; }
    public function get_name() { return $this->name; }
    public function get_quantity() { return $this->qty; }
    public function get_total() { return $this->total; }
}

class FakeWC_Order
{
    public array $items = [];
    public function get_items() { return $this->items; }
}

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$order = new FakeWC_Order();
$order->items = [
    new FakeWC_Item(101, 'Analytical Engine', 1, '99.90'),
    new FakeWC_Item(202, 'Punch Cards',       2, '50.00'),
];

$mapper = new Wp_Sdtrk_WC_Order_Mapper();
$lines  = $mapper->lineItems($order);

echo "lineItems() structured list (multi-product)\n";
check('two line items returned',   count($lines) === 2);
check('line 0 id = 101 (string)',  ($lines[0]['id'] ?? null) === '101');
check('line 0 name',               ($lines[0]['name'] ?? null) === 'Analytical Engine');
check('line 0 qty = 1',            ($lines[0]['qty'] ?? null) === 1);
check('line 0 unit price = 99.90', abs(($lines[0]['price'] ?? 0) - 99.90) < 0.0001);
check('line 1 qty = 2',            ($lines[1]['qty'] ?? null) === 2);
check('line 1 unit price = 25.00', abs(($lines[1]['price'] ?? 0) - 25.00) < 0.0001);

echo "lineItems() empty order\n";
$empty = new FakeWC_Order();
check('empty order => []',         $mapper->lineItems($empty) === []);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
