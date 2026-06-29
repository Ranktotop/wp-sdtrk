<?php
/**
 * Regression guard (server side): with no WooCommerce items and no currency, the
 * Meta CAPI payload must keep the legacy single-product shape and EUR fallback.
 *
 * Run:  php tests/test-nowc-server-regression.php
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
            $map = ['meta_pixelid' => 'PID', 'meta_trk_server_token' => 'TOKEN'];
            return $map[$k] ?? false;
        }
        public static function get_bool_option($k, $default = false)
        {
            return $k === 'meta_trk_server' ? true : false;
        }
    }
}
if (!function_exists('get_bloginfo')) { function get_bloginfo($k) { return 'StubSite'; } }

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-event.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-meta.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

// Legacy single-product purchase: prodId only, no items, no currency.
$eventArr = [
    'eventName'   => ['purchase'],
    'value'       => ['50'],
    'orderId'     => ['4711'],
    'prodId'            => ['999'],
    'prodName'          => ['Solo'],
    'eventSource'       => 'https://shop/',
    'eventSourceAdress' => '0.0.0.0',
    'eventSourceAgent'  => 'Mozilla/5.0 (Test)',
    'eventTime'         => 1782741408,
];
$tracker = new Wp_Sdtrk_Tracker_Meta();
$tracker->fireTracking_Server(new Wp_Sdtrk_Tracker_Event($eventArr), 'Event', []);
$custom = json_decode($GLOBALS['captured'], true)['data'][0]['custom_data'] ?? [];

echo "Meta CAPI single-product fallback\n";
check('content_ids single', ($custom['content_ids'] ?? null) === '["999"]');
check('one content entry', isset($custom['contents']) && count($custom['contents']) === 1);
check('content id 999', ($custom['contents'][0]['id'] ?? null) === '999');
check('EUR fallback (no shop currency)', ($custom['currency'] ?? null) === 'EUR');

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
