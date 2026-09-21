/**
 * Unit test for the Wp_Sdtrk_Catcher_Oai attribution side-channel: oppref is
 * captured from the landing URL into an own 30-day cookie (so server-only
 * setups can attribute without the pixel), falls back to the pixel's
 * __oppref cookie, and both oppref and the pixel's __obref are forwarded to
 * the server with every AJAX hit.
 *
 * Run:  node tests/test-oai-user-data.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const js = (f) => readFileSync(join(here, '..', 'public', 'js', f), 'utf8');
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Catcher_Oai = new Function(js('wp-sdtrk-oai.js') + '\nreturn Wp_Sdtrk_Catcher_Oai;')();

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}

function helper(params, cookies) {
	return {
		saved: [],
		sent: [],
		debugLog: () => {},
		get_Param(k) { return (k in params) ? params[k] : null; },
		get_Cookie(k) { return (k in cookies) ? cookies[k] : null; },
		save_cookie(name, value, days, firstparty) { this.saved.push({ name, value, days, firstparty }); },
		send_ajax(payload) { this.sent.push(payload); },
	};
}
function catcher(h) {
	const c = Object.create(Wp_Sdtrk_Catcher_Oai.prototype);
	c.helper = h;
	c.event = {};
	c.s_enabled = true;
	c.localizedData = { dbg: '' };
	return c;
}

console.log('oppref from the landing URL');
const h1 = helper({ oppref: 'OPP_URL' }, { _oai_oppref: 'OLD', __oppref: 'PIX' });
const c1 = catcher(h1);
check('URL param wins', c1.get_Oppref() === 'OPP_URL');
const s1 = h1.saved.find((s) => s.name === '_oai_oppref');
check('stored in _oai_oppref for 30 days', !!s1 && s1.value === 'OPP_URL' && s1.days === 30 && s1.firstparty === false);

console.log('oppref from the own cookie (no refresh without a new param)');
const h2 = helper({}, { _oai_oppref: 'OLD', __oppref: 'PIX' });
check('own cookie before pixel cookie', catcher(h2).get_Oppref() === 'OLD');
check('reading does not extend the cookie', h2.saved.length === 0);

console.log('oppref falls back to the pixel cookie');
check('__oppref used', catcher(helper({}, { __oppref: 'PIX' })).get_Oppref() === 'PIX');
check('nothing -> empty string', catcher(helper({}, {})).get_Oppref() === '');

console.log('obref from the pixel cookie');
check('__obref read', catcher(helper({}, { __obref: '123e4567-e89b-42d3-a456-426614174000' })).get_Obref() === '123e4567-e89b-42d3-a456-426614174000');
check('missing -> empty string', catcher(helper({}, {})).get_Obref() === '');

console.log('sendData forwards oppref + obref');
const h3 = helper({}, { _oai_oppref: 'OPP', __obref: 'OBR' });
const c3 = catcher(h3);
c3.oppref = c3.get_Oppref();
c3.obref = c3.get_Obref();
c3.sendData('Event', { state: true });
const p = h3.sent[0];
check('ajax sent with type oai', !!p && p.type === 'oai' && p.handler === 'Event');
check('oppref forwarded', !!p && p.data.oppref === 'OPP');
check('obref forwarded', !!p && p.data.obref === 'OBR');
const h4 = helper({}, {});
const c4 = catcher(h4);
c4.oppref = c4.get_Oppref();
c4.obref = c4.get_Obref();
c4.sendData('Page', { state: true });
check('empty values are not forwarded', !('oppref' in h4.sent[0].data) && !('obref' in h4.sent[0].data));

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
