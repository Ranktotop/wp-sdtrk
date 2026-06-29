<?php
/**
 * Standalone unit test for the public AJAX dispatch hardening
 * (Wp_Sdtrk_Public_Ajax_Handler::validateTracker).
 *
 * Run: php tests/test-ajax-dispatch.php
 *
 * Covers the untrusted-input guards added in #1:
 *  - base checks (event/type/handler/data must be set),
 *  - handler allow-list (Page|Event|Scroll|Time|Click|Visibility),
 *  - type reduced to [a-z] before building the tracker class name
 *    (so a crafted type can't resolve to an arbitrary class),
 *  - sanitize_side_data() (strings sanitized, bool/int kept, recursive).
 */

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-tracker-event.php';

// Capturing stub tracker: type 'mock' -> Wp_Sdtrk_Tracker_Mock.
class Wp_Sdtrk_Tracker_Mock
{
    public static $captured = null;
    public function setAndGetDebugMode_frontend($d) { return $d; }
    public function fireTracking_Server($event, $handler, $data)
    {
        self::$captured = ['handler' => $handler, 'data' => $data];
        return true;
    }
}

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-public-ajax.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$h = new Wp_Sdtrk_Public_Ajax_Handler();

echo "base checks\n";
check('missing fields => state false',
    ($h->validateTracker(['event' => [], 'type' => 'mock']))['state'] === false);

echo "handler allow-list\n";
Wp_Sdtrk_Tracker_Mock::$captured = null;
$r = $h->validateTracker(['event' => [], 'type' => 'mock', 'handler' => 'EvilHandler', 'data' => []]);
check('unknown handler => state false',     $r['state'] === false);
check('unknown handler => tracker not run', Wp_Sdtrk_Tracker_Mock::$captured === null);

echo "type filter (class-injection guard)\n";
$r = $h->validateTracker(['event' => [], 'type' => 'm0ck!', 'handler' => 'Event', 'data' => []]);
check('non-alpha type filtered => no class => state false', $r['state'] === false);

Wp_Sdtrk_Tracker_Mock::$captured = null;
$h->validateTracker(['event' => [], 'type' => 'MoCk', 'handler' => 'Page', 'data' => []]);
check('clean type (case-folded) resolves + dispatches', Wp_Sdtrk_Tracker_Mock::$captured !== null);

echo "side-channel sanitization\n";
Wp_Sdtrk_Tracker_Mock::$captured = null;
$h->validateTracker([
    'event'   => [],
    'type'    => 'mock',
    'handler' => 'Event',
    'data'    => ['fbp' => '<b>fb.1.x</b>', 'flag' => true, 'n' => 5, 'nested' => ['t' => '<i>y</i>']],
]);
$d = Wp_Sdtrk_Tracker_Mock::$captured['data'];
check('handler passed through',     Wp_Sdtrk_Tracker_Mock::$captured['handler'] === 'Event');
check('string side-data sanitized', $d['fbp'] === 'fb.1.x');
check('bool side-data preserved',   $d['flag'] === true);
check('int side-data preserved',    $d['n'] === 5);
check('nested string sanitized',    $d['nested']['t'] === 'y');

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
