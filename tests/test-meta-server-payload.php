<?php
/**
 * Unit test for the Meta Conversions API payload (server-side Purchase):
 * multi-product contents, shop currency, hashed user data.
 *
 * Run:  php tests/test-meta-server-payload.php
 *
 * The real fireTracking_Server() path is exercised by enabling server tracking
 * through option stubs and capturing the JSON payload in a do_post stub.
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
    }
}
if (!class_exists('WP_SDTRK_Helper_Options')) {
    class WP_SDTRK_Helper_Options
    {
        public static function get_string_option($k)
        {
            $map = ['meta_pixelid' => 'PID123', 'meta_trk_server_token' => 'TOKEN'];
            return $map[$k] ?? false;
        }
        public static function get_bool_option($k, $default = false)
        {
            return $k === 'meta_trk_server' ? true : false;
        }
    }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($k) { return 'StubSite'; }
}

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-event.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-meta.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$items = [
    ['id' => '24215', 'name' => 'Hybridlehrgang', 'qty' => 1, 'price' => 2000.0],
    ['id' => '777',   'name' => 'Skript',         'qty' => 2, 'price' => 75.0],
];
$eventArr = [
    'eventName'         => ['purchase'],
    'value'             => ['2150'],
    'currency'          => 'USD',
    'orderId'           => ['4711'],
    'items'             => $items,
    'prodId'            => ['24215'],
    'prodName'          => ['Hybridlehrgang'],
    'userEmail'         => ['buyer@example.com'],
    'userFirstName'     => ['Ada'],
    'userLastName'      => ['Lovelace'],
    'eventSource'       => 'https://shop/checkout/order-received/4711/',
    'eventSourceAdress' => '203.0.113.7',
    'eventSourceAgent'  => 'Mozilla/5.0 (Test)',
    'eventTime'         => 1782741408,
];

$event   = new Wp_Sdtrk_Tracker_Event($eventArr);
$tracker = new Wp_Sdtrk_Tracker_Meta();
$tracker->fireTracking_Server($event, 'Event', ['fbp' => 'fb.1.x', 'fbc' => 'fb.1.y']);

$payload = json_decode($GLOBALS['captured'], true);
$d = $payload['data'][0] ?? [];
$custom = $d['custom_data'] ?? [];
$user = $d['user_data'] ?? [];

echo "Meta CAPI Purchase payload\n";
check('event_name = Purchase',          ($d['event_name'] ?? null) === 'Purchase');
check('event_id = order id (dedup)',     ($d['event_id'] ?? null) === '4711');
check('currency from shop (USD)',        ($custom['currency'] ?? null) === 'USD');
check('value = 2150',                    abs(($custom['value'] ?? 0) - 2150) < 0.0001);
check('content_ids has both products',   ($custom['content_ids'] ?? null) === '["24215","777"]');
check('content_type product',            ($custom['content_type'] ?? null) === 'product');
check('contents has both w/ quantity',   isset($custom['contents']) && count($custom['contents']) === 2
                                          && $custom['contents'][1]['id'] === '777'
                                          && (int) $custom['contents'][1]['quantity'] === 2);
check('email hashed (sha256, normalized)', ($user['em'] ?? null) === hash('sha256', 'buyer@example.com'));
check('first name hashed (normalized)',  ($user['fn'] ?? null) === hash('sha256', 'ada'));
check('last name hashed (normalized)',   ($user['ln'] ?? null) === hash('sha256', 'lovelace'));
check('fbp/fbc passed through',          ($user['fbp'] ?? null) === 'fb.1.x' && ($user['fbc'] ?? null) === 'fb.1.y');

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
