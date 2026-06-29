<?php
/**
 * Unit test for the TikTok Events API 2.0 payload (server-side Purchase):
 * multi-product contents[], shop currency, event_id = "<orderId>_<hash>".
 *
 * Run:  php tests/test-tt-server-payload.php
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
            $map = ['tt_pixelid' => 'TTPID', 'tt_trk_server_token' => 'TOKEN'];
            return $map[$k] ?? false;
        }
        public static function get_bool_option($k, $default = false)
        {
            return $k === 'tt_trk_server' ? true : false;
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
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-tt.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$eventArr = [
    'eventName'         => ['purchase'],
    'value'             => ['2150'],
    'currency'          => 'USD',
    'orderId'           => ['4711'],
    'items'             => [
        ['id' => '24215', 'name' => 'Hybridlehrgang', 'qty' => 1, 'price' => 2000.0],
        ['id' => '777',   'name' => 'Skript',         'qty' => 2, 'price' => 75.0],
    ],
    'prodId'            => ['24215'],
    'prodName'          => ['Hybridlehrgang'],
    'userEmail'         => ['buyer@example.com'],
    'eventSource'       => 'https://shop/checkout/order-received/4711/',
    'eventSourceAdress' => '203.0.113.7',
    'eventSourceAgent'  => 'Mozilla/5.0 (Test)',
    'eventTime'         => 1782741408,
];

$event   = new Wp_Sdtrk_Tracker_Event($eventArr);
$tracker = new Wp_Sdtrk_Tracker_Tt();
$tracker->fireTracking_Server($event, 'Event', ['hash' => 'H9', 'ttc' => 'CLID', 'ttp' => 'TTP']);

$payload = json_decode($GLOBALS['captured'], true);
$d = $payload['data'][0] ?? [];
$props = $d['properties'] ?? [];
$user = $d['user'] ?? [];

echo "TikTok Events API Purchase payload\n";
check('event = PlaceAnOrder',        ($d['event'] ?? null) === 'PlaceAnOrder');
check('event_id = order_hash',       ($d['event_id'] ?? null) === '4711_H9');
check('currency from shop (USD)',    ($props['currency'] ?? null) === 'USD');
check('value = 2150',                abs(($props['value'] ?? 0) - 2150) < 0.0001);
check('contents has both products',  isset($props['contents']) && count($props['contents']) === 2);
check('content 0 id',                ($props['contents'][0]['content_id'] ?? null) === '24215');
check('content 1 quantity 2',        (int) ($props['contents'][1]['quantity'] ?? 0) === 2);
check('content 1 name',              ($props['contents'][1]['content_name'] ?? null) === 'Skript');
check('email hashed',                ($user['email'] ?? null) === hash('sha256', 'buyer@example.com'));

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
