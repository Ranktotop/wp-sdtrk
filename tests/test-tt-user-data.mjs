/**
 * Regression test for Wp_Sdtrk_Catcher_Tt.get_data_user(): it must not reference
 * an undefined `email` variable. With a buyer email present (as on the WC
 * order-received page) the old code threw a ReferenceError inside
 * ttq.identify(get_data_user()), breaking engine init for TikTok-browser sites.
 *
 * Run:  node tests/test-tt-user-data.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const src = readFileSync(join(here, '..', 'public', 'js', 'wp-sdtrk-tt.js'), 'utf8');
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Catcher_Tt = new Function(src + '\nreturn Wp_Sdtrk_Catcher_Tt;')();

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}

const c = Object.create(Wp_Sdtrk_Catcher_Tt.prototype);
c.helper = { get_Cookie: () => false, save_cookie: () => {} };
c.event = {
	getUserEmail: () => 'buyer@example.com',
	getEventSourceAgent: () => 'Mozilla/5.0 (Test)',
	getEventSourceAdress: () => '203.0.113.7',
};

console.log('TikTok get_data_user with a buyer email present');
let user, threw = false;
try { user = c.get_data_user(); } catch (e) { threw = true; }
check('does not throw (no undefined `email`)', !threw);
check('email passed through', user && user.email === 'buyer@example.com');
check('external_id set from hashId', user && typeof user.external_id !== 'undefined');

// No email => no email key, still no throw
const c2 = Object.create(Wp_Sdtrk_Catcher_Tt.prototype);
c2.helper = { get_Cookie: () => false, save_cookie: () => {} };
c2.event = { getUserEmail: () => '', getEventSourceAgent: () => 'ua', getEventSourceAdress: () => '1.2.3.4' };
const u2 = c2.get_data_user();
check('no email => email key absent', !('email' in u2));

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
