<?php
/**
 * Unit test for Wp_Sdtrk_WC_Integration::build_order_payload(): the order data
 * localized to the engine on the order-received page (wp_sdtrk_wc.order).
 *
 * No WordPress/WooCommerce bootstrap required. Run:
 *   php tests/test-wc-order-payload.php
 *
 * This is the seam joining the order, the mapper (lineItems) and the engine
 * ingestion — a field rename here would silently break browser + server Purchase.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-order-mapper.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-integration.php';

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
    public function get_id() { return 32548; }
    public function get_total() { return '2150.00'; }
    public function get_currency() { return 'USD'; }
    public function get_billing_email() { return 'buyer@example.com'; }
    public function get_billing_first_name() { return 'Ada'; }
    public function get_billing_last_name() { return 'Lovelace'; }
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
    new FakeWC_Item(24215, 'Hybridlehrgang', 1, '2000.00'),
    new FakeWC_Item(777, 'Skript', 2, '150.00'),
];

$integration = new Wp_Sdtrk_WC_Integration();
$payload = $integration->build_order_payload($order);
$o = $payload['order'] ?? [];

echo "build_order_payload() shape\n";
check('orderId is string id',        ($o['orderId'] ?? null) === '32548');
check('value is string total',       ($o['value'] ?? null) === '2150.00');
check('currency = shop currency',    ($o['currency'] ?? null) === 'USD');
check('email = billing email',       ($o['email'] ?? null) === 'buyer@example.com');
check('firstName = billing first',   ($o['firstName'] ?? null) === 'Ada');
check('lastName = billing last',     ($o['lastName'] ?? null) === 'Lovelace');

echo "items = full cart (lineItems)\n";
check('two items',                   is_array($o['items'] ?? null) && count($o['items']) === 2);
check('item 0 id (string)',          ($o['items'][0]['id'] ?? null) === '24215');
check('item 0 name',                 ($o['items'][0]['name'] ?? null) === 'Hybridlehrgang');
check('item 1 qty',                  ($o['items'][1]['qty'] ?? null) === 2);
check('item 1 unit price',           abs(($o['items'][1]['price'] ?? 0) - 75.00) < 0.0001);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
