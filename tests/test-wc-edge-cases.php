<?php
/**
 * Edge cases flagged in the ship review: value-0 purchase, string-typed qty/price
 * coercion, TikTok price<=0 omission, and empty-cart build_order_payload.
 *
 * Run:  php tests/test-wc-edge-cases.php
 */

require_once __DIR__ . '/_bootstrap.php';

$GLOBALS['captured'] = null;
if (!class_exists('WP_SDTRK_Helper_Event')) {
    class WP_SDTRK_Helper_Event
    {
        public static function do_post($url, $payload, $headers = array(), $debug = false) { $GLOBALS['captured'] = $payload; return array('ok' => true); }
        public static function getCurrentReferer($s = false) { return ''; }
        public static function getCurrentURL($s = false) { return 'https://shop/'; }
        public static function getClientIp() { return '0.0.0.0'; }
    }
}
if (!class_exists('WP_SDTRK_Helper_Options')) {
    class WP_SDTRK_Helper_Options
    {
        public static function get_string_option($k)
        {
            $m = ['ga_measurement_id' => 'G-X', 'ga_trk_server_token' => 'S', 'tt_pixelid' => 'TT', 'tt_trk_server_token' => 'T'];
            return $m[$k] ?? false;
        }
        public static function get_bool_option($k, $d = false) { return in_array($k, ['ga_trk_server', 'tt_trk_server'], true); }
    }
}
if (!function_exists('get_bloginfo')) { function get_bloginfo($k) { return 'Stub'; } }
if (!function_exists('get_site_url')) { function get_site_url() { return 'https://shop'; } }
$_SERVER['REQUEST_URI'] = '/checkout/order-received/4711/';

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-event.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-ga.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-tt.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-order-mapper.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-integration.php';

$fails = 0;
function check($label, $cond) { global $fails; if ($cond) { echo "  PASS: $label\n"; } else { echo "  FAIL: $label\n"; $fails++; } }

// --- value-0 purchase + string qty/price coercion (GA MP) ---
echo "GA: value-0 purchase + string-typed qty/price\n";
$ev = new Wp_Sdtrk_Tracker_Event([
    'eventName' => ['purchase'], 'value' => ['0'], 'orderId' => ['4711'], 'brandName' => 'B',
    'items' => [['id' => '24215', 'name' => 'A', 'qty' => '1', 'price' => '2000.00'],
                ['id' => '777', 'name' => 'B', 'qty' => '2', 'price' => '75.00']],
    'eventSource' => 'https://shop/', 'eventTime' => 1782741408,
]);
(new Wp_Sdtrk_Tracker_Ga())->fireTracking_Server($ev, 'Event', ['cid' => '1.2']);
$p = json_decode($GLOBALS['captured'], true)['events'][0]['params'] ?? [];
check('value present and = 0',        array_key_exists('value', $p) && abs($p['value'] - 0) < 0.0001);
check('currency EUR fallback',        ($p['currency'] ?? null) === 'EUR');
check('string qty coerced to int 2',  (int) ($p['items'][1]['quantity'] ?? 0) === 2 && $p['items'][1]['quantity'] === 2);
check('string price coerced to 75.0', abs(($p['items'][1]['price'] ?? 0) - 75.0) < 0.0001);

// --- TikTok price <= 0 omitted ---
echo "TikTok: price <= 0 is omitted from contents\n";
$ev2 = new Wp_Sdtrk_Tracker_Event([
    'eventName' => ['purchase'], 'value' => ['10'], 'orderId' => ['4711'],
    'items' => [['id' => 'free', 'name' => 'Freebie', 'qty' => 1, 'price' => 0]],
    'eventSource' => 'https://shop/', 'eventSourceAdress' => '0.0.0.0', 'eventSourceAgent' => 'UA', 'eventTime' => 1782741408,
]);
(new Wp_Sdtrk_Tracker_Tt())->fireTracking_Server($ev2, 'Event', ['hash' => 'H']);
$c = json_decode($GLOBALS['captured'], true)['data'][0]['properties']['contents'][0] ?? [];
check('content_id present',           ($c['content_id'] ?? null) === 'free');
check('price key omitted when 0',     !array_key_exists('price', $c));

// --- empty-cart build_order_payload ---
echo "build_order_payload: empty cart\n";
$emptyOrder = new class {
    public function get_id() { return 50; }
    public function get_total() { return '0.00'; }
    public function get_currency() { return 'EUR'; }
    public function get_billing_email() { return ''; }
    public function get_billing_first_name() { return ''; }
    public function get_billing_last_name() { return ''; }
    public function get_items() { return []; }
};
$payload = (new Wp_Sdtrk_WC_Integration())->build_order_payload($emptyOrder);
check('items is empty array',         ($payload['order']['items'] ?? null) === []);
check('orderId still present',         ($payload['order']['orderId'] ?? null) === '50');

if ($fails > 0) { echo "\n$fails assertion(s) failed.\n"; exit(1); }
echo "\nAll assertions passed.\n";
exit(0);
