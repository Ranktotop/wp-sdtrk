/**
 * Unit test for Wp_Sdtrk_Catcher_Meta.get_fbc() — Meta's 90-day click-window.
 *
 * Covers the three states a visitor can be in: arriving on a fresh ad click,
 * returning within the window, and returning after it has passed. The stored
 * value must never be refreshed without a new click, otherwise a long-expired
 * fbclid stays alive forever for recurring visitors.
 *
 * The method only reads this.helper, so the catcher is instantiated via
 * Object.create to bypass the pixel-loading constructor.
 *
 * Run:  node tests/test-meta-fbc.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const js = (f) => readFileSync(join(here, '..', 'public', 'js', f), 'utf8');
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Catcher_Meta = new Function(js('wp-sdtrk-meta.js') + '\nreturn Wp_Sdtrk_Catcher_Meta;')();

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}

const DAY = 24 * 60 * 60 * 1000;
const fbc = (ageDays, clid) => 'fb.1.' + (Date.now() - ageDays * DAY) + '.' + clid;

/**
 * Builds a catcher with a stubbed helper. `saved` records every cookie write so
 * the test can assert that the lifetime is not silently extended.
 */
function catcher(fbclidParam, storedCookie) {
	const saved = [];
	const c = Object.create(Wp_Sdtrk_Catcher_Meta.prototype);
	c.helper = {
		get_Param: (n) => (n === 'fbclid' ? (fbclidParam || null) : null),
		get_Cookie: (n) => (n === '_fbc' ? (storedCookie || null) : null),
		save_cookie: (n, v, d) => { saved.push({ name: n, value: v, days: d }); },
	};
	return { c, saved };
}

console.log('get_fbc — fresh ad click');
{
	const { c, saved } = catcher('NewClickId', null);
	const value = c.get_fbc();
	const parts = value.split('.');
	check('value built from fbclid', parts[3] === 'NewClickId');
	check('prefix fb.1', parts[0] === 'fb' && parts[1] === '1');
	check('timestamp is current', Math.abs(Date.now() - parseInt(parts[2], 10)) < 5000);
	check('cookie stored for 90 days', saved.length === 1 && saved[0].name === '_fbc' && saved[0].days === 90);
}

console.log('get_fbc — returning visitor clicks a NEW ad (stale value stored)');
{
	const { c, saved } = catcher('SecondClickId', fbc(120, 'FirstClickId'));
	const value = c.get_fbc();
	check('new click-id replaces stored one', value.split('.')[3] === 'SecondClickId');
	check('stale click-id gone', value.indexOf('FirstClickId') === -1);
	check('timestamp refreshed with the click', Math.abs(Date.now() - parseInt(value.split('.')[2], 10)) < 5000);
	check('new value persisted', saved.length === 1 && saved[0].value === value);
}

console.log('get_fbc — returning visitor, no new click');
{
	const stored = fbc(30, 'StillValidClickId');
	const { c, saved } = catcher(null, stored);
	check('value inside window is reused', c.get_fbc() === stored);
	check('lifetime NOT extended', saved.length === 0);
}
{
	const { c, saved } = catcher(null, fbc(120, 'ExpiredClickId'));
	check('value past the window is dropped', c.get_fbc() === '');
	check('expired value not re-saved', saved.length === 0);
}
{
	check('no cookie at all yields empty', catcher(null, null).c.get_fbc() === '');
}

console.log('get_fbc — malformed stored values');
for (const bad of ['fb.1.y', 'fb.1..ClickId', 'not-an-fbc', 'fb.1.1700000000000']) {
	check('dropped: ' + bad, catcher(null, bad).c.get_fbc() === '');
}

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
