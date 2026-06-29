/**
 * Regression guard: with no WooCommerce data (empty items, no currency), the
 * browser catchers must behave exactly as before the multi-product/currency
 * change — single-product content from prodId, EUR currency fallback, and no
 * value/currency on non-value events.
 *
 * Run:  node tests/test-nowc-regression.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const js = (f) => readFileSync(join(here, '..', 'public', 'js', f), 'utf8');
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Event = new Function(js('wp-sdtrk-event.js') + '\nreturn Wp_Sdtrk_Event;')();
const load = (f, cls) => new Function(js(f) + '\nreturn ' + cls + ';')();
const Meta = load('wp-sdtrk-meta.js', 'Wp_Sdtrk_Catcher_Meta');
const Ga = load('wp-sdtrk-ga.js', 'Wp_Sdtrk_Catcher_Ga');
const Tt = load('wp-sdtrk-tt.js', 'Wp_Sdtrk_Catcher_Tt');

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}
function make(Cls, ev) { const c = Object.create(Cls.prototype); c.event = ev; c.localizedData = { pid: 'X' }; return c; }

// --- Scenario A: view_item, single product, value 0 (no items, no currency) ---
console.log('Scenario A — view_item single product');
const a = new Wp_Sdtrk_Event();
a.setEventName({ p: 'view_item' });
a.setProdId({ p: '999' });
a.setProdName({ p: 'Solo' });
a.setValue({ p: '' });
a.setBrandName('B');
const am = make(Meta, a).get_data_custom();
check('meta content_ids single', am.content_ids === '["999"]');
check('meta no value (not purchase)', !('value' in am));
check('meta no currency (not purchase)', !('currency' in am));
const ag = make(Ga, a).get_data_custom();
check('ga single item', Array.isArray(ag.items) && ag.items.length === 1 && ag.items[0].id === '999');
check('ga no currency', !('currency' in ag));
const at = make(Tt, a).get_data_custom();
check('tt single content_id', at.content_id === '999');
check('tt no contents array', !('contents' in at));

// --- Scenario B: generate_lead with value, no product ---
console.log('Scenario B — lead with value, no product');
const b = new Wp_Sdtrk_Event();
b.setEventName({ p: 'generate_lead' });
b.setProdId({ p: '' });
b.setValue({ p: '50' });
b.setBrandName('B');
const bm = make(Meta, b).get_data_custom();
check('meta EUR fallback', bm.currency === 'EUR');
check('meta value 50', bm.value === 50);
check('meta no content_ids (no product)', !('content_ids' in bm));
const bg = make(Ga, b).get_data_custom();
check('ga EUR fallback', bg.currency === 'EUR');
check('ga no items (no product)', !('items' in bg));
const bt = make(Tt, b).get_data_custom();
check('tt EUR fallback', bt.currency === 'EUR');

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
