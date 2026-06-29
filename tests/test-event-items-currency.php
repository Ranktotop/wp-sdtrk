<?php
/**
 * Standalone unit test for the multi-product + currency additions to the
 * server-side event model (Wp_Sdtrk_Tracker_Event).
 *
 * No WordPress bootstrap required. Run:
 *   php tests/test-event-items-currency.php
 *
 * getItems()    -> the structured per-line list, or [] when absent (back-compat).
 * getCurrency() -> the event currency, or 'EUR' fallback when absent/empty.
 */

require_once __DIR__ . '/_bootstrap.php';

if (!class_exists('WP_SDTRK_Helper_Options')) {
    class WP_SDTRK_Helper_Options
    {
        public static function get_string_option($k) { return false; }
    }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($k) { return 'StubSite'; }
}

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-event.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

echo "getItems()\n";
$empty = new Wp_Sdtrk_Tracker_Event([]);
check('absent items => []',            $empty->getItems() === []);
check('absent currency => EUR',        $empty->getCurrency() === 'EUR');
check('empty currency => EUR',         (new Wp_Sdtrk_Tracker_Event(['currency' => '']))->getCurrency() === 'EUR');

$items = [
    ['id' => '101', 'name' => 'A', 'qty' => 1, 'price' => 99.9],
    ['id' => '202', 'name' => 'B', 'qty' => 2, 'price' => 25.0],
];
$ev = new Wp_Sdtrk_Tracker_Event(['items' => $items, 'currency' => 'USD']);
check('items passed through',          $ev->getItems() === $items);
check('two items',                     count($ev->getItems()) === 2);
check('currency = USD',                $ev->getCurrency() === 'USD');
check('non-array items => []',         (new Wp_Sdtrk_Tracker_Event(['items' => 'x']))->getItems() === []);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
