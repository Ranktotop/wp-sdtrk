<?php
/**
 * Standalone unit test for the WooCommerce-integration activation gate.
 *
 * No WordPress bootstrap required. Run:  php tests/test-wc-integration-gate.php
 *
 * The gate is the single source of truth deciding whether any WooCommerce
 * hook/firing runs: WooCommerce must be present AND the Redux switch enabled.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-integration.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) {
        echo "  PASS: $label\n";
    } else {
        echo "  FAIL: $label\n";
        $fails++;
    }
}

echo "Wp_Sdtrk_WC_Integration::is_active_for()\n";

check('WC present + switch on  => active',  Wp_Sdtrk_WC_Integration::is_active_for(true,  true)  === true);
check('WC present + switch off => inactive', Wp_Sdtrk_WC_Integration::is_active_for(true,  false) === false);
check('WC absent + switch on   => inactive', Wp_Sdtrk_WC_Integration::is_active_for(false, true)  === false);
check('WC absent + switch off  => inactive', Wp_Sdtrk_WC_Integration::is_active_for(false, false) === false);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
