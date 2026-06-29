/**
 * Unit test for Wp_Sdtrk_Catcher_Meta.get_data_custom() — multi-product contents
 * and shop currency (with single-product + EUR fallbacks).
 *
 * The method only reads this.event and the prototype convert_eventname (no DOM,
 * no globals), so the catcher is instantiated via Object.create to bypass the
 * pixel-loading constructor.
 *
 * Run:  node tests/test-meta-custom-data.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const js = (f) => readFileSync(join(here, '..', 'public', 'js', f), 'utf8');
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Event = new Function(js('wp-sdtrk-event.js') + '\nreturn Wp_Sdtrk_Event;')();
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Catcher_Meta = new Function(js('wp-sdtrk-meta.js') + '\nreturn Wp_Sdtrk_Catcher_Meta;')();

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}

function purchaseEvent(items, currency) {
	const ev = new Wp_Sdtrk_Event();
	ev.setEventName({ wc: 'purchase' });
	ev.setOrderId({ wc: '4711' });
	ev.setValue({ wc: '2150' });
	ev.setCurrency(currency || '');
	ev.setItems(items || []);
	if (items && items.length) {
		ev.setProdId({ wc: String(items[0].id) });
		ev.setProdName({ wc: String(items[0].name) });
	}
	return ev;
}

function catcher(ev) {
	const c = Object.create(Wp_Sdtrk_Catcher_Meta.prototype);
	c.event = ev;
	return c;
}

console.log('Meta get_data_custom — multi-product + currency');
const items = [
	{ id: '24215', name: 'Hybridlehrgang', qty: 1, price: 2000 },
	{ id: '777', name: 'Skript', qty: 2, price: 75 },
];
const cd = catcher(purchaseEvent(items, 'USD')).get_data_custom();
check('currency from shop (USD)', cd.currency === 'USD');
check('value carried', cd.value === 2150);
check('content_ids all items', cd.content_ids === '["24215","777"]');
check('content_type product', cd.content_type === 'product');
check('contents all items w/ qty', cd.contents === '[{"id":"24215","quantity":1},{"id":"777","quantity":2}]');

console.log('Meta get_data_custom — single-product fallback + EUR fallback');
const single = new Wp_Sdtrk_Event();
single.setEventName({ p: 'view_item' });
single.setProdId({ p: '999' });
single.setProdName({ p: 'Solo' });
single.setValue({ p: '50' });
const cd2 = catcher(single).get_data_custom();
check('EUR fallback when no currency', cd2.currency === 'EUR');
check('single content_ids', cd2.content_ids === '["999"]');
check('single contents', cd2.contents === '[{"id":"999","quantity":1}]');
check('single content_name', cd2.content_name === 'Solo');

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
