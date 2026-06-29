<?php
/**
 * Unit test for the GA4 Measurement Protocol payload (server-side Purchase):
 * multi-product items[], shop currency, transaction_id = order id.
 *
 * Run:  php tests/test-ga-server-payload.php
 */

require_once __DIR__ . '/_bootstrap.php';

$GLOBALS['captured'] = null;

if (!class_exists('WP_SDTRK_Helper_Event')) {
    class WP_SDTRK_Helper_Event
    {
        public static function do_post($url, $payload, $headers = array(), $debug = false)
        {
            $GLOBALS['captured'] = $payload;
            return array('ok' => true);
        }
        public static function getCurrentReferer($strip = false) { return ''; }
        public static function getCurrentURL($strip = false) { return 'https://shop/'; }
        public static function getClientIp() { return '0.0.0.0'; }
    }
}
if (!class_exists('WP_SDTRK_Helper_Options')) {
    class WP_SDTRK_Helper_Options
    {
        public static function get_string_option($k)
        {
            $map = ['ga_measurement_id' => 'G-XYZ', 'ga_trk_server_token' => 'SECRET'];
            return $map[$k] ?? false;
        }
        public static function get_bool_option($k, $default = false)
        {
            return $k === 'ga_trk_server' ? true : false;
        }
    }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($k) { return 'StubSite'; }
}
if (!function_exists('get_site_url')) {
    function get_site_url() { return 'https://shop'; }
}
$_SERVER['REQUEST_URI'] = '/checkout/order-received/4711/';

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-event.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-ga.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$eventArr = [
    'eventName'     => ['purchase'],
    'value'         => ['2150'],
    'currency'      => 'USD',
    'orderId'       => ['4711'],
    'brandName'     => 'EIA',
    'items'         => [
        ['id' => '24215', 'name' => 'Hybridlehrgang', 'qty' => 1, 'price' => 2000.0],
        ['id' => '777',   'name' => 'Skript',         'qty' => 2, 'price' => 75.0],
    ],
    'prodId'        => ['24215'],
    'prodName'      => ['Hybridlehrgang'],
    'eventSource'   => 'https://shop/checkout/order-received/4711/',
    'eventTime'     => 1782741408,
];

$event   = new Wp_Sdtrk_Tracker_Event($eventArr);
$tracker = new Wp_Sdtrk_Tracker_Ga();
$tracker->fireTracking_Server($event, 'Event', ['cid' => '123.456']);

$payload = json_decode($GLOBALS['captured'], true);
$ev = $payload['events'][0] ?? [];
$params = $ev['params'] ?? [];

echo "GA4 MP Purchase payload\n";
check('client_id passed',           ($payload['client_id'] ?? null) === '123.456');
check('event name purchase',        ($ev['name'] ?? null) === 'purchase');
check('currency from shop (USD)',   ($params['currency'] ?? null) === 'USD');
check('value = 2150',               abs(($params['value'] ?? 0) - 2150) < 0.0001);
check('transaction_id = order id',  ($params['transaction_id'] ?? null) === '4711');
check('two items',                  isset($params['items']) && count($params['items']) === 2);
check('item 0 id',                  ($params['items'][0]['id'] ?? null) === '24215');
check('item 1 quantity 2',          (int) ($params['items'][1]['quantity'] ?? 0) === 2);
check('item 1 name',                ($params['items'][1]['name'] ?? null) === 'Skript');

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
