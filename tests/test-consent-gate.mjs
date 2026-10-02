/**
 * Unit test for the consent gate in front of every cookie the catchers write.
 *
 * Without consent (Borlabs configured, visitor has not opted in) no catcher may
 * write _fbp/_fbc/_ga/_ttc/_ttp or compute the fingerprint. Consent is only
 * skipped when it is bypassed explicitly: per page (force) or per settings
 * (cookie service "none"). A Borlabs that has not loaded yet counts as "no
 * consent", not as "no statement".
 *
 * Run:  node tests/test-consent-gate.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const js = (f) => readFileSync(join(here, '..', 'public', 'js', f), 'utf8');
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Event = new Function(js('wp-sdtrk-event.js') + '\nreturn Wp_Sdtrk_Event;')();
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Helper = new Function(js('wp-sdtrk-helper.js') + '\nreturn Wp_Sdtrk_Helper;')();
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Catcher_Meta = new Function(js('wp-sdtrk-meta.js') + '\nreturn Wp_Sdtrk_Catcher_Meta;')();
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Catcher_Ga = new Function(js('wp-sdtrk-ga.js') + '\nreturn Wp_Sdtrk_Catcher_Ga;')();
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Catcher_Tt = new Function(js('wp-sdtrk-tt.js') + '\nreturn Wp_Sdtrk_Catcher_Tt;')();

globalThis.window = globalThis;

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}

const helperProto = Wp_Sdtrk_Helper.prototype;
const event = (force) => {
	const ev = new Wp_Sdtrk_Event();
	if (force) { ev.enableForce(); } else { ev.disableForce(); }
	return ev;
};

console.log('has_consent');
{
	delete globalThis.BorlabsCookie;
	check('borlabs not loaded => false', helperProto.has_consent('facebook', 'borlabs', event(false)) === false);
	check('service none => -1 (settings bypass)', helperProto.has_consent('facebook', 'none', event(false)) === -1);
	check('force => -1 (page bypass)', helperProto.has_consent('facebook', 'borlabs', event(true)) === -1);

	globalThis.BorlabsCookie = { Consents: { hasConsent: (id) => id === 'facebook' } };
	check('borlabs v3 consent => true', helperProto.has_consent('facebook', 'borlabs', event(false)) === true);
	check('borlabs v3 no consent => false', helperProto.has_consent('google', 'borlabs', event(false)) === false);

	globalThis.BorlabsCookie = { checkCookieConsent: () => undefined };
	check('borlabs v2 non-boolean => false', helperProto.has_consent('facebook', 'borlabs', event(false)) === false);
	delete globalThis.BorlabsCookie;
}

/**
 * Builds a catcher with a stubbed helper whose consent answer is fixed. Every
 * cookie write is recorded; loadPixel is stubbed so no DOM is needed.
 */
function catcher(Cls, consent, params = {}) {
	const saved = [];
	const c = Object.create(Cls.prototype);
	c.event = event(false);
	c.s_enabled = false;
	c.b_enabled = false;
	c.pixelLoaded = false;
	c.fbc = false; c.fbp = false; c.cid = false; c.ttc = false; c.ttp = false;
	c.localizedData = { pid: '123', b_e: '1', s_e: '1', b_cs: 'borlabs', s_cs: 'borlabs', b_ci: 'x', s_ci: 'x', dbg: '' };
	c.helper = {
		isAdmin: () => false,
		debugLog: () => {},
		has_consent: (id, service) => (typeof consent === 'function' ? consent(service) : consent),
		get_Param: (n) => params[n] || null,
		get_Cookie: () => null,
		save_cookie: (n) => { saved.push(n); },
	};
	c.loadPixel = () => { c.pixelLoaded = true; };
	return { c, saved };
}

const platforms = [
	['meta', Wp_Sdtrk_Catcher_Meta, { fbclid: 'abc' }, ['_fbc', '_fbp']],
	['ga', Wp_Sdtrk_Catcher_Ga, {}, ['_ga']],
	['tt', Wp_Sdtrk_Catcher_Tt, { ttclid: 'abc' }, ['_ttc']],
];

for (const [name, Cls, params, cookies] of platforms) {
	console.log(name + ' — no consent');
	{
		const { c, saved } = catcher(Cls, false, params);
		let fpComputed = false;
		c.event.setUserFPSource(() => { fpComputed = true; return 646907098; });
		c.validate();
		check('no cookie written', saved.length === 0);
		check('pixel not loaded', c.pixelLoaded === false);
		check('fingerprint not computed', fpComputed === false);
	}
	console.log(name + ' — consent');
	{
		const { c, saved } = catcher(Cls, true, params);
		c.validate();
		check('cookies written: ' + cookies.join(','), cookies.every((k) => saved.includes(k)));
		check('pixel loaded', c.pixelLoaded === true);
	}
	console.log(name + ' — bypass (page force or cookie service none => -1)');
	{
		const { c, saved } = catcher(Cls, -1, params);
		c.validate();
		check('browser + server enabled', c.b_enabled === true && c.s_enabled === true);
		check('cookies written: ' + cookies.join(','), cookies.every((k) => saved.includes(k)));
	}
	console.log(name + ' — server consent only');
	{
		const { c, saved } = catcher(Cls, false, params);
		c.localizedData.s_cs = 'server';
		c.helper.has_consent = (id, service) => service === 'server';
		c.validate();
		check('server enabled, browser not', c.s_enabled === true && c.b_enabled === false);
		check('pixel not loaded', c.pixelLoaded === false);
		check('cookies written for server payload', cookies.every((k) => saved.includes(k)));
	}
	console.log(name + ' — opt-in later (backload)');
	{
		const { c, saved } = catcher(Cls, false, params);
		c.validate();
		c.helper.has_consent = () => true;
		check('backload reports ongoing', c.isOngoingBackload('b') === true);
		check('cookies written on backload', cookies.every((k) => saved.includes(k)));
	}
}

console.log('ga — fingerprint used only with consent');
{
	const { c } = catcher(Wp_Sdtrk_Catcher_Ga, true);
	c.event.setUserFPSource(() => 646907098);
	c.validate();
	check('cid built from fingerprint', c.cid === '646907098.646907098');
}

console.log('event — fingerprint source stays out of the payload');
{
	const ev = new Wp_Sdtrk_Event();
	ev.setUserFPSource(() => 1);
	check('source not enumerable', !Object.keys(ev).includes('userFPSource'));
	check('userFP absent until requested', !('userFP' in ev));
	check('getUserFP computes lazily', ev.getUserFP() === 1 && Object.keys(ev).includes('userFP'));
}

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
