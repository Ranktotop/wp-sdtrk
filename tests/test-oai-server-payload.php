<?php
/**
 * Unit test for the ChatGPT Ads (OpenAI) Conversions API payload: endpoint
 * with ?pid=, Bearer auth, events[] envelope, integer minor-unit amounts,
 * contents[], page_viewed, the server gates and the AJAX dispatch of type=oai.
 *
 * Run:  php tests/test-oai-server-payload.php
 */

require_once __DIR__ . '/_bootstrap.php';

$GLOBALS['captured'] = null;
$GLOBALS['opts'] = [
    'oai_pixelid'          => 'PIXEL123',
    'oai_trk_server_token' => 'SECRET',
];
$GLOBALS['bools'] = ['oai_trk_server' => true];

if (!class_exists('WP_SDTRK_Helper_Event')) {
    class WP_SDTRK_Helper_Event
    {
        public static function do_post($url, $payload, $headers = array(), $debug = false)
        {
            $GLOBALS['captured'] = ['url' => $url, 'payload' => $payload, 'headers' => $headers];
            return array('state' => true);
        }
        public static function getCurrentReferer($strip = false) { return ''; }
        public static function getCurrentURL($strip = false) { return 'https://shop/'; }
        public static function getClientIp() { return '0.0.0.0'; }
        public static function getGlobalEventMap()
        {
            return array('Time' => 'time_spent_%', 'Scroll' => 'scroll_depth_%', 'Click' => 'button_click', 'Visibility' => 'item_visit');
        }
    }
}
if (!class_exists('WP_SDTRK_Helper_Options')) {
    class WP_SDTRK_Helper_Options
    {
        public static function get_string_option($k)
        {
            return $GLOBALS['opts'][$k] ?? false;
        }
        public static function get_bool_option($k, $default = false)
        {
            return $GLOBALS['bools'][$k] ?? $default;
        }
    }
}
if (!function_exists('get_site_url')) {
    function get_site_url() { return 'https://shop'; }
}
$_SERVER['REQUEST_URI'] = '/checkout/order-received/4711/';

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-event.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-oai.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-public-ajax.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}
function fire($eventArr, $handler, $data = []) {
    $GLOBALS['captured'] = null;
    $tracker = new Wp_Sdtrk_Tracker_Oai();
    $tracker->fireTracking_Server(new Wp_Sdtrk_Tracker_Event($eventArr), $handler, $data);
    return $GLOBALS['captured'];
}

$now = time();
$purchase = [
    'eventName'         => ['purchase'],
    'value'             => ['2150.5'],
    'currency'          => 'USD',
    'orderId'           => ['4711'],
    'eventId'           => '999',
    'items'             => [
        ['id' => '24215', 'name' => 'Hybridlehrgang', 'qty' => 1, 'price' => 2000.0],
        ['id' => '777',   'name' => 'Skript',         'qty' => 2, 'price' => 75.25],
    ],
    'prodId'            => ['24215'],
    'prodName'          => ['Hybridlehrgang'],
    'eventSource'       => 'https://shop/checkout/order-received/4711/',
    'eventSourceAdress' => '203.0.113.7',
    'eventSourceAgent'  => 'Mozilla/5.0 (Test)',
    'eventTime'         => $now,
];

echo "OpenAI CAPI request envelope\n";
$req = fire($purchase, 'Event');
check('request sent', $req !== null);
check('endpoint with pid', ($req['url'] ?? null) === 'https://bzr.openai.com/v1/events?pid=PIXEL123');
check('bearer auth header', in_array('Authorization: Bearer SECRET', $req['headers'] ?? [], true));
$body = json_decode($req['payload'] ?? '', true);
check('validate_only false by default', ($body['validate_only'] ?? null) === false);
check('no integration_source', !array_key_exists('integration_source', $body ?? []));
check('exactly one event', isset($body['events']) && count($body['events']) === 1);
$e = $body['events'][0] ?? [];

echo "OpenAI CAPI order_created event\n";
check('type order_created', ($e['type'] ?? null) === 'order_created');
check('id = order id', ($e['id'] ?? null) === '4711');
check('timestamp_ms integer ms', ($e['timestamp_ms'] ?? null) === $now * 1000);
check('source_url', ($e['source_url'] ?? null) === 'https://shop/checkout/order-received/4711/');
check('action_source web', ($e['action_source'] ?? null) === 'web');
$d = $e['data'] ?? [];
check('data type contents', ($d['type'] ?? null) === 'contents');
check('amount 215050', ($d['amount'] ?? null) === 215050);
check('currency USD', ($d['currency'] ?? null) === 'USD');
check('two contents', isset($d['contents']) && count($d['contents']) === 2);
check('content id/name/type', ($d['contents'][0]['id'] ?? null) === '24215' && ($d['contents'][0]['name'] ?? null) === 'Hybridlehrgang' && ($d['contents'][0]['content_type'] ?? null) === 'product');
check('content quantity int 2', ($d['contents'][1]['quantity'] ?? null) === 2);
check('content amount 7525', ($d['contents'][1]['amount'] ?? null) === 7525);

echo "OpenAI CAPI attribution + user data\n";
$withUser = $purchase;
$withUser['userEmail'] = ['Buyer@Example.com'];
$req = fire($withUser, 'Event', ['oppref' => 'oppref_abc', 'obref' => '123e4567-e89b-42d3-a456-426614174000']);
$e = json_decode($req['payload'], true)['events'][0];
$u = $e['user'] ?? [];
check('oppref on event level', ($e['oppref'] ?? null) === 'oppref_abc');
check('obref inside user, unhashed', ($u['obref'] ?? null) === '123e4567-e89b-42d3-a456-426614174000');
check('email normalized + sha256 list', ($u['emails_sha256'] ?? null) === [hash('sha256', 'buyer@example.com')]);
check('ip_address', ($u['ip_address'] ?? null) === '203.0.113.7');
check('user_agent', ($u['user_agent'] ?? null) === 'Mozilla/5.0 (Test)');
check('no plaintext email anywhere', strpos($req['payload'], 'xample.com') === false);
check('no other identifiers', count(array_diff(array_keys($u), ['obref', 'emails_sha256', 'ip_address', 'user_agent'])) === 0);
$req = fire($purchase, 'Event', []);
$e = json_decode($req['payload'], true)['events'][0];
check('no oppref when absent', !array_key_exists('oppref', $e));
check('no obref/email when absent', !isset($e['user']['obref']) && !isset($e['user']['emails_sha256']));

echo "OpenAI CAPI lead_created (customer_action)\n";
$req = fire(['eventName' => ['generate_lead'], 'eventId' => '321', 'value' => ['19.9'], 'eventSource' => 'https://shop/danke/', 'eventTime' => $now], 'Event');
$e = json_decode($req['payload'], true)['events'][0];
check('type lead_created', $e['type'] === 'lead_created');
check('customer_action without contents', $e['data']['type'] === 'customer_action' && !isset($e['data']['contents']));
check('amount 1990 EUR fallback', $e['data']['amount'] === 1990 && $e['data']['currency'] === 'EUR');
check('id = event id', $e['id'] === '321');

echo "OpenAI CAPI page_viewed\n";
$req = fire(['eventId' => '555', 'pageId' => '17', 'pageName' => 'Preise', 'eventSource' => 'https://shop/preise/', 'eventTime' => $now], 'Page');
$e = json_decode($req['payload'], true)['events'][0];
check('type page_viewed', $e['type'] === 'page_viewed');
check('id = event id', $e['id'] === '555');
check('page content', $e['data']['type'] === 'contents' && $e['data']['contents'][0]['id'] === '17' && $e['data']['contents'][0]['content_type'] === 'page');

echo "OpenAI CAPI timestamp guard\n";
$req = fire(['eventId' => '1', 'eventSource' => 'https://shop/', 'eventTime' => $now - 8 * 86400], 'Page');
$ts = json_decode($req['payload'], true)['events'][0]['timestamp_ms'];
check('stale client time replaced by server time', abs($ts - time() * 1000) < 5000);

echo "OpenAI CAPI unsupported event\n";
$req = fire(['eventName' => ['foo_bar'], 'eventId' => '1', 'eventSource' => 'https://shop/', 'eventTime' => $now], 'Event');
check('no request for unmapped event', $req === null);

echo "OpenAI CAPI signal events (same fixtures + expectations as test-oai-custom-data.mjs)\n";
$sig = ['eventId' => '555', 'eventSource' => 'https://shop/', 'eventTime' => $now];
$cases = [
    ['Scroll', ['percent' => '50'], 'scroll_depth_50', '555-s50'],
    ['Time', ['time' => '30'], 'time_spent_30', '555-t30'],
    ['Click', ['tag' => 'cta-top'], 'button_click', '555-bcta-top'],
    ['Visibility', ['tag' => 'pricing'], 'item_visit', '555-vpricing'],
];
foreach ($cases as $case) {
    $req = fire($sig, $case[0], $case[1]);
    $e = $req ? json_decode($req['payload'], true)['events'][0] : [];
    check(strtolower($case[0]) . ' => custom ' . $case[2] . ' / ' . $case[3],
        ($e['type'] ?? null) === 'custom' && ($e['custom_event_name'] ?? null) === $case[2]
        && ($e['id'] ?? null) === $case[3] && ($e['data'] ?? null) === ['type' => 'custom']);
}
check('valid custom name', Wp_Sdtrk_Tracker_Oai::isValidCustomEventName('scroll_depth_50') === true);
check('rejects specials', Wp_Sdtrk_Tracker_Oai::isValidCustomEventName('bad name!') === false);
check('rejects edge underscore/dash', !Wp_Sdtrk_Tracker_Oai::isValidCustomEventName('_x') && !Wp_Sdtrk_Tracker_Oai::isValidCustomEventName('x-'));
check('64 ok, 65 rejected', Wp_Sdtrk_Tracker_Oai::isValidCustomEventName(str_repeat('a', 64)) && !Wp_Sdtrk_Tracker_Oai::isValidCustomEventName(str_repeat('a', 65)));
check('rejects standard name', Wp_Sdtrk_Tracker_Oai::isValidCustomEventName('Order_Created') === false);
check('rejects empty', Wp_Sdtrk_Tracker_Oai::isValidCustomEventName('') === false);

echo "OpenAI CAPI validate_only switch\n";
$GLOBALS['bools']['oai_trk_server_validate_only'] = true;
$req = fire($purchase, 'Event');
check('validate_only true when switched on', json_decode($req['payload'], true)['validate_only'] === true);
$GLOBALS['bools']['oai_trk_debug'] = true;
$GLOBALS['bools']['oai_trk_server_validate_only'] = false;
$req = fire($purchase, 'Event');
check('debug alone does not validate-only', json_decode($req['payload'], true)['validate_only'] === false);
$GLOBALS['bools']['oai_trk_debug'] = false;

echo "OpenAI CAPI gates\n";
$GLOBALS['bools']['oai_trk_server'] = false;
check('server switch off => no request', fire($purchase, 'Event') === null);
$GLOBALS['bools']['oai_trk_server'] = true;
$GLOBALS['opts']['oai_trk_server_token'] = '';
check('no token => no request', fire($purchase, 'Event') === null);
$GLOBALS['opts']['oai_trk_server_token'] = 'SECRET';

echo "OpenAI AJAX dispatch (type=oai)\n";
$GLOBALS['captured'] = null;
$h = new Wp_Sdtrk_Public_Ajax_Handler();
$r = $h->validateTracker(['event' => $purchase, 'type' => 'oai', 'handler' => 'Event', 'data' => ['state' => true]]);
check('dispatch reaches Wp_Sdtrk_Tracker_Oai', $GLOBALS['captured'] !== null && $r['state'] !== false);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
