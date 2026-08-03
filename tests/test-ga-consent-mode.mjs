/**
 * Regression test for the Consent-Mode-v2 signals of Wp_Sdtrk_Catcher_Ga.
 *
 * Without an ad_user_data signal Google stops exporting GA4 conversions to
 * Google Ads (the Ads conversion column stays at exactly 0), so the signals have
 * to reach the dataLayer - and they have to be queued *before* the config
 * command, otherwise gtag has already configured itself when they arrive.
 *
 * Run:  node tests/test-ga-consent-mode.mjs
 *
 * loadPixel touches window/dataLayer/document, so window is aliased to
 * globalThis and a minimal document stub swallows the injected <script>.
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const src = readFileSync(join(here, '..', 'public', 'js', 'wp-sdtrk-ga.js'), 'utf8');
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Catcher_Ga = new Function(src + '\nreturn Wp_Sdtrk_Catcher_Ga;')();

globalThis.window = globalThis;
globalThis.document = {
	createElement: () => ({}),
	getElementsByTagName: () => [{ parentNode: { insertBefore: () => {} } }],
};

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}

/** Build a catcher with stubbed helper/event and run loadPixel(). */
function loadWith(localized, preset = []) {
	globalThis.dataLayer = preset.slice();
	globalThis.gtag = undefined;

	const c = Object.create(Wp_Sdtrk_Catcher_Ga.prototype);
	c.b_enabled = true;
	c.pixelLoaded = false;
	c.localizedData = Object.assign({ pid: 'G-TEST', debug: false, dbg: false }, localized);
	c.helper = {
		get_Param: () => false,
		get_Cookie: () => false,
		save_cookie: () => {},
		debugLog: () => {},
	};
	c.event = {
		getEventUrl: () => 'https://shop.example/danke',
		getEventPath: () => '/danke',
		getEventDomain: () => 'shop.example',
		getEventSourceReferer: () => '',
		getForce: () => false,
	};
	c.loadPixel();
	return c;
}

/** Return the payload of the last consent command of the given kind. */
function consentPayload(kind) {
	const hit = globalThis.dataLayer.filter((a) => a[0] === 'consent' && a[1] === kind).pop();
	return hit ? hit[2] : null;
}

function indexOfCommand(name) {
	return globalThis.dataLayer.findIndex((a) => a[0] === name);
}

console.log('1) Tag loaded with consent (borlabs)');
loadWith({ b_cs: 'borlabs', b_ci: 'google-analytics' });
let def = consentPayload('default');
let upd = consentPayload('update');
check('a consent default was sent', !!def);
check('default denies ad_user_data', !!def && def.ad_user_data === 'denied');
check('default denies ad_storage', !!def && def.ad_storage === 'denied');
check('default denies ad_personalization', !!def && def.ad_personalization === 'denied');
check('default denies analytics_storage', !!def && def.analytics_storage === 'denied');
check('update grants ad_user_data', !!upd && upd.ad_user_data === 'granted');
check('update grants ad_storage', !!upd && upd.ad_storage === 'granted');
check('update grants ad_personalization', !!upd && upd.ad_personalization === 'granted');
check('update grants analytics_storage', !!upd && upd.analytics_storage === 'granted');
check('consent is queued before config', indexOfCommand('consent') < indexOfCommand('config'));
check('consent is queued before js', indexOfCommand('consent') < indexOfCommand('js'));
check('default is queued before update', globalThis.dataLayer.findIndex((a) => a[1] === 'default') < globalThis.dataLayer.findIndex((a) => a[1] === 'update'));

console.log('\n2) No consent service ("fire always")');
loadWith({ b_cs: 'none', b_ci: '' });
upd = consentPayload('update');
check('all four signals granted', !!upd && upd.ad_user_data === 'granted' && upd.ad_storage === 'granted' && upd.ad_personalization === 'granted' && upd.analytics_storage === 'granted');

console.log('\n3) Another tool already manages consent mode');
const external = [['consent', 'default', { ad_storage: 'denied' }]];
loadWith({ b_cs: 'borlabs', b_ci: 'google-analytics' }, external);
check('no second consent command was pushed', globalThis.dataLayer.filter((a) => a[0] === 'consent').length === 1);
check('the foreign default is left untouched', consentPayload('default').ad_storage === 'denied');
check('the tag still configures itself', indexOfCommand('config') > -1);

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
