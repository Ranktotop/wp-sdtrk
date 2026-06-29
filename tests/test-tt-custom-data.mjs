/**
 * Unit test for Wp_Sdtrk_Catcher_Tt.get_data_custom() — multi-product contents[]
 * and shop currency (single-product + EUR fallbacks).
 *
 * Run:  node tests/test-tt-custom-data.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const js = (f) => readFileSync(join(here, '..', 'public', 'js', f), 'utf8');
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Event = new Function(js('wp-sdtrk-event.js') + '\nreturn Wp_Sdtrk_Event;')();
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Catcher_Tt = new Function(js('wp-sdtrk-tt.js') + '\nreturn Wp_Sdtrk_Catcher_Tt;')();

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}

function catcher(ev) {
	const c = Object.create(Wp_Sdtrk_Catcher_Tt.prototype);
	c.event = ev;
	return c;
}

console.log('TikTok get_data_custom — multi-product + currency');
const ev = new Wp_Sdtrk_Event();
ev.setEventName({ wc: 'purchase' });
ev.setOrderId({ wc: '4711' });
ev.setValue({ wc: '2150' });
ev.setCurrency('USD');
ev.setItems([
	{ id: '24215', name: 'Hybridlehrgang', qty: 1, price: 2000 },
	{ id: '777', name: 'Skript', qty: 2, price: 75 },
]);
ev.setProdId({ wc: '24215' });
ev.setProdName({ wc: 'Hybridlehrgang' });
const cd = catcher(ev).get_data_custom();
check('currency USD', cd.currency === 'USD');
check('value 2150', cd.value === 2150);
check('contents has two', Array.isArray(cd.contents) && cd.contents.length === 2);
check('content 0 id', cd.contents[0].content_id === '24215');
check('content 1 quantity 2', cd.contents[1].quantity === 2);
check('content 1 price 75', cd.contents[1].price === 75);
check('content_type product', cd.content_type === 'product');

console.log('TikTok get_data_custom — single-product fallback + EUR fallback');
const single = new Wp_Sdtrk_Event();
single.setEventName({ p: 'view_item' });
single.setProdId({ p: '999' });
single.setProdName({ p: 'Solo' });
single.setValue({ p: '50' });
const cd2 = catcher(single).get_data_custom();
check('EUR fallback', cd2.currency === 'EUR');
check('single content_id', cd2.content_id === '999');
check('single content_name', cd2.content_name === 'Solo');

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
