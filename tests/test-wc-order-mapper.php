<?php
/**
 * Standalone unit test for Wp_Sdtrk_WC_Order_Mapper.
 *
 * No WordPress/WooCommerce bootstrap required. Run:
 *   php tests/test-wc-order-mapper.php
 *
 * Verifies that a WooCommerce order is translated into the associative array
 * schema that Wp_Sdtrk_Tracker_Event consumes, and that the resulting Event
 * getters return the expected values (incl. shared event_id = order id).
 */

require_once __DIR__ . '/_bootstrap.php';

// ---- Minimal helper/option + WP stubs needed by Wp_Sdtrk_Tracker_Event ----
if (!class_exists('WP_SDTRK_Helper_Event')) {
    class WP_SDTRK_Helper_Event
    {
        public static function getClientIp() { return '0.0.0.0'; }
        public static function getCurrentURL($strip = false) { return 'http://stub/'; }
        public static function getCurrentReferer($strip = false) { return ''; }
        public static function getGlobalEventMap() { return []; }
    }
}
if (!class_exists('WP_SDTRK_Helper_Options')) {
    class WP_SDTRK_Helper_Options
    {
        public static function get_string_option($k) { return false; }
    }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($k) { return 'StubSite'; }
}
if (!function_exists('get_site_url')) {
    function get_site_url() { return 'http://stub'; }
}

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-event.php';
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
    public function get_id() { return 4711; }
    public function get_total() { return '149.90'; }
    public function get_currency() { return 'USD'; }
    public function get_billing_email() { return 'buyer@example.com'; }
    public function get_billing_first_name() { return 'Ada'; }
    public function get_billing_last_name() { return 'Lovelace'; }
    public function get_customer_ip_address() { return '203.0.113.7'; }
    public function get_customer_user_agent() { return 'Mozilla/5.0 (Test)'; }
    public function get_checkout_order_received_url() { return 'http://stub/checkout/order-received/4711/'; }
    public function get_items() { return $this->items; }
    public function get_meta($key, $single = true) { return ''; }
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
$arr    = $mapper->toEventArray($order);

echo "toEventArray() array shape\n";
check('eventName is ["purchase"]',     ($arr['eventName'] ?? null) === ['purchase']);
check('orderId carries the order id',  ($arr['orderId'] ?? null) === ['4711']);
check('value carries the order total', ($arr['value'] ?? null) === ['149.90']);
check('currency = order currency',     ($arr['currency'] ?? null) === 'USD');
check('eventSourceAdress is single ip', ($arr['eventSourceAdress'] ?? null) === '203.0.113.7');
check('eventSourceAgent is single ua',  ($arr['eventSourceAgent'] ?? null) === 'Mozilla/5.0 (Test)');

echo "Event getters fed by the mapped array\n";
$event = new Wp_Sdtrk_Tracker_Event($arr);
check('getEventName() === purchase',   $event->getEventName() === 'purchase');
check('getEventValue() === 149.90',    abs($event->getEventValue() - 149.90) < 0.0001);
check('getTransactionId() === 4711',   $event->getTransactionId() === '4711');
check('getEventId() === 4711 (dedup)', $event->getEventId() === '4711');
check('getProductId() === first item', $event->getProductId() === '101');
check('getProductName() === first',    $event->getProductName() === 'Analytical Engine');
check('getUserEmail() === buyer',      $event->getUserEmail() === 'buyer@example.com');
check('getUserFirstName() === Ada',    $event->getUserFirstName() === 'Ada');
check('getUserLastName() === Lovelace', $event->getUserLastName() === 'Lovelace');

echo "lineItems() structured list (multi-product)\n";
$lines = $mapper->lineItems($order);
check('two line items returned',       count($lines) === 2);
check('line 0 id = 101',               ($lines[0]['id'] ?? null) === '101');
check('line 1 qty = 2',                ($lines[1]['qty'] ?? null) === 2);
check('line 1 name = Punch Cards',     ($lines[1]['name'] ?? null) === 'Punch Cards');

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
