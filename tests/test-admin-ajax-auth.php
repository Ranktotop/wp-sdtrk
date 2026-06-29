<?php
/**
 * Standalone unit test for the admin AJAX authorization gate (C1 fix)
 * in Wp_Sdtrk_Admin_Ajax_Handler::handle_admin_ajax_callback.
 *
 * Run: php tests/test-admin-ajax-auth.php
 *
 * Pins the security property so a regression back to is_admin() (which is true
 * for any admin-ajax request) is caught:
 *  - the nonce is checked first (capability not evaluated on a bad nonce),
 *  - an authenticated-but-uncapable request is rejected BEFORE any func runs,
 *  - capability is checked with 'manage_options',
 *  - an authorized request proceeds to dispatch.
 *
 * Note: is_admin() is intentionally NOT stubbed — if the gate ever regresses to
 * is_admin(), this test fatals loudly instead of passing silently.
 */

require_once __DIR__ . '/_bootstrap.php';

class AdminRejected extends Exception {}
class DispatchReached extends Exception {}

$GLOBALS['__nonce_ok'] = false;
$GLOBALS['__cap_ok']   = false;
$GLOBALS['__cap_arg']  = null;
$GLOBALS['__opts']     = [];

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) { return $GLOBALS['__nonce_ok']; }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) { throw new AdminRejected(); }
}
if (!function_exists('current_user_can')) {
    function current_user_can($cap) { $GLOBALS['__cap_arg'] = $cap; return $GLOBALS['__cap_ok']; }
}
if (!function_exists('get_option')) {
    function get_option($k, $d = false) { return $GLOBALS['__opts'][$k] ?? $d; }
}
if (!function_exists('update_option')) {
    function update_option($k, $v, $a = null) {
        $GLOBALS['__opts'][$k] = $v;
        // Sentinel: prove dispatch ran without hitting the terminal die(json_encode()).
        if (!empty($GLOBALS['__throw_on_token_write']) && $k === 'wp_sdtrk_feed_token') {
            throw new DispatchReached();
        }
        return true;
    }
}
if (!function_exists('wp_generate_password')) {
    function wp_generate_password($l = 12, $s = true) { return str_repeat('t', $l); }
}

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-feed.php';
require_once dirname(__DIR__) . '/admin/class-wp-sdtrk-admin-ajax.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$h = new Wp_Sdtrk_Admin_Ajax_Handler();
function run_callback($h) {
    try { $h->handle_admin_ajax_callback(); return 'completed'; }
    catch (AdminRejected $e) { return 'rejected'; }
    catch (DispatchReached $e) { return 'dispatched'; }
}

echo "nonce checked first\n";
$GLOBALS['__nonce_ok'] = false; $GLOBALS['__cap_ok'] = true; $GLOBALS['__cap_arg'] = null;
$_POST = ['_nonce' => 'x', 'func' => 'regenerate_feed_token'];
check('bad nonce => rejected',                run_callback($h) === 'rejected');
check('capability not evaluated on bad nonce', $GLOBALS['__cap_arg'] === null);

echo "unauthorized blocked before dispatch\n";
$GLOBALS['__nonce_ok'] = true; $GLOBALS['__cap_ok'] = false; $GLOBALS['__cap_arg'] = null;
$GLOBALS['__opts'] = []; $GLOBALS['__throw_on_token_write'] = true;
$_POST = ['_nonce' => 'x', 'func' => 'regenerate_feed_token'];
check('no capability => rejected',            run_callback($h) === 'rejected');
check('capability checked with manage_options', $GLOBALS['__cap_arg'] === 'manage_options');
check('func did NOT run (no token written)',  !isset($GLOBALS['__opts']['wp_sdtrk_feed_token']));

echo "authorized reaches dispatch\n";
$GLOBALS['__nonce_ok'] = true; $GLOBALS['__cap_ok'] = true;
$GLOBALS['__opts'] = []; $GLOBALS['__throw_on_token_write'] = true;
$_POST = ['_nonce' => 'x', 'func' => 'regenerate_feed_token'];
check('authorized => dispatch runs',          run_callback($h) === 'dispatched');

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
