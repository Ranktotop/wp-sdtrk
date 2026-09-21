<?php
/**
 * Integration test for WP_SDTRK_Helper_Event::do_post(): a non-2xx HTTP status
 * must be reported as failure even when the body is JSON without an "error"
 * key (OpenAI's Conversions API documents no error body format).
 *
 * Starts PHP's built-in web server on localhost for real status codes.
 *
 * Run:  php tests/test-do-post-http-status.php
 */

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers/class-wp-sdtrk-helper-event.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

// Router: /status/<code>/<json|empty|error> answers with that status and body
$router = tempnam(sys_get_temp_dir(), 'sdtrk_router') . '.php';
file_put_contents($router, '<?php
$p = explode("/", trim(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), "/"));
http_response_code((int) $p[1]);
header("Content-Type: application/json");
if ($p[2] === "json")  { echo json_encode(["detail" => "invalid event"]); }
if ($p[2] === "error") { echo json_encode(["error" => "bad"]); }
if ($p[2] === "ok")    { echo json_encode(["events_received" => 1]); }
');
$port = 18000 + random_int(0, 999);
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", $router], [1 => ['file', 'php://temp', 'w'], 2 => ['file', 'php://temp', 'w']], $pipes);
$base = "http://127.0.0.1:$port";
for ($i = 0; $i < 50; $i++) {
    $s = @fsockopen('127.0.0.1', $port);
    if ($s) { fclose($s); break; }
    usleep(100000);
}

echo "do_post HTTP status handling\n";
$r = WP_SDTRK_Helper_Event::do_post("$base/status/200/ok", '{}', array(), false);
check('200 JSON => success', $r['state'] === true);
$r = WP_SDTRK_Helper_Event::do_post("$base/status/204/empty", '{}', array(), false);
check('204 no body => success', $r['state'] === true);
$r = WP_SDTRK_Helper_Event::do_post("$base/status/400/json", '{}', array(), false);
check('400 JSON without error key => failure', $r['state'] === false);
check('400 => code is the HTTP status', ($r['code'] ?? null) === 400);
$r = WP_SDTRK_Helper_Event::do_post("$base/status/401/json", '{}', array(), false);
check('401 JSON => failure', $r['state'] === false);
$r = WP_SDTRK_Helper_Event::do_post("$base/status/200/error", '{}', array(), false);
check('200 JSON with error key => failure (unchanged)', $r['state'] === false);

proc_terminate($proc);
proc_close($proc);
@unlink($router);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
