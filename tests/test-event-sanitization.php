<?php
/**
 * Standalone unit test for per-field input sanitization in the server-side
 * event model (Wp_Sdtrk_Tracker_Event).
 *
 * Run: php tests/test-event-sanitization.php
 *
 * Untrusted AJAX input flows through the getters into the server payloads, so
 * each getter must sanitize: text fields strip tags/collapse whitespace, email
 * is validated, items are typed (id/name sanitized, qty int, price float), the
 * user-agent and URLs are cleaned. Clean values must pass through unchanged
 * (idempotent) — that's what keeps the live-verified payloads stable.
 */

require_once __DIR__ . '/_bootstrap.php';

if (!class_exists('WP_SDTRK_Helper_Options')) {
    class WP_SDTRK_Helper_Options
    {
        public static function get_string_option($k) { return false; }
    }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($k = '') { return 'StubSite'; }
}

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-event.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

echo "dirty input is sanitized\n";
$dirty = new Wp_Sdtrk_Tracker_Event([
    'prodId'           => ['<b>999</b>'],
    'prodName'         => ['<script>alert(1)</script>Shoes'],
    'userFirstName'    => ["Ada\n\t"],
    'userLastName'     => ['Lovelace<img src=x>'],
    'userEmail'        => ['not a <valid> email'],
    'pageName'         => 'Title  with   spaces',
    'eventSourceAgent' => "Mozilla/5.0\r\n(evil)",
    'eventSource'      => 'https://shop/<script>',
    'items'            => [
        ['id' => '<i>24215</i>', 'name' => 'Hybrid<br>lehrgang', 'qty' => '2', 'price' => '75.0'],
    ],
]);

check('prodId tags stripped',        $dirty->getProductId() === '999');
check('prodName tags stripped',      $dirty->getProductName() === 'alert(1)Shoes');
check('first name trimmed',          $dirty->getUserFirstName() === 'Ada');
check('last name tags stripped',     $dirty->getUserLastName() === 'Lovelace');
check('invalid email => empty',      $dirty->getUserEmail() === '');
check('page name whitespace collapsed', $dirty->getPageName() === 'Title with spaces');
check('user-agent newlines removed', $dirty->getEventAgent() === 'Mozilla/5.0 (evil)');
check('event source tags stripped',  strpos($dirty->getEventSource(), '<script>') === false);

$it = $dirty->getItems();
check('item id sanitized',           $it[0]['id'] === '24215');
check('item name sanitized',         $it[0]['name'] === 'Hybridlehrgang');
check('item qty cast to int',        $it[0]['qty'] === 2);
check('item price cast to float',    $it[0]['price'] === 75.0);

echo "clean input is unchanged (idempotent)\n";
$clean = new Wp_Sdtrk_Tracker_Event([
    'prodId'        => ['24215'],
    'prodName'      => ['Hybridlehrgang'],
    'userEmail'     => ['buyer@example.com'],
    'userFirstName' => ['Ada'],
    'eventSource'   => 'https://shop/checkout/order-received/4711/',
    'eventSourceAgent' => 'Mozilla/5.0 (Test)',
    'items'         => [['id' => '777', 'name' => 'Skript', 'qty' => 2, 'price' => 75.0]],
]);
check('clean prodId unchanged',      $clean->getProductId() === '24215');
check('clean email unchanged',       $clean->getUserEmail() === 'buyer@example.com');
check('clean agent unchanged',       $clean->getEventAgent() === 'Mozilla/5.0 (Test)');
check('clean source unchanged',      $clean->getEventSource() === 'https://shop/checkout/order-received/4711/');
check('clean items unchanged',       $clean->getItems() === [['id' => '777', 'name' => 'Skript', 'qty' => 2, 'price' => 75.0]]);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
