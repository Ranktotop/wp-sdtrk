<?php
/**
 * Standalone unit test for the server-firing decision logic.
 *
 * Run: php tests/test-wc-server-decision.php
 *
 * The decision gates the consent-gated, idempotent server-side conversion API
 * firing on order-status transitions (T2.5):
 *   - never fire twice for the same order+platform (idempotency)
 *   - otherwise fire when consent was granted OR a bypass is set
 *   - fail closed: no consent + no bypass => do not fire
 */

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-integration.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

echo "Wp_Sdtrk_WC_Integration::should_fire_server(consented, bypass, already_sent)\n";

// consented, not yet sent => fire
check('consent + not sent => fire',          Wp_Sdtrk_WC_Integration::should_fire_server(true,  false, false) === true);
// no consent, no bypass => fail closed
check('no consent + no bypass => no fire',    Wp_Sdtrk_WC_Integration::should_fire_server(false, false, false) === false);
// bypass overrides missing consent
check('bypass overrides no consent => fire',  Wp_Sdtrk_WC_Integration::should_fire_server(false, true,  false) === true);
// idempotency wins over everything
check('already sent => no fire (consent)',    Wp_Sdtrk_WC_Integration::should_fire_server(true,  false, true)  === false);
check('already sent => no fire (bypass)',     Wp_Sdtrk_WC_Integration::should_fire_server(false, true,  true)  === false);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
