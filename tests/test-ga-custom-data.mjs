/**
 * Unit test for Wp_Sdtrk_Catcher_Ga.get_data_custom() — multi-product items[]
 * and shop currency (single-product + EUR fallbacks).
 *
 * Run:  node tests/test-ga-custom-data.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const js = (f) => readFileSync(join(here, '..', 'public', 'js', f), 'utf8');
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Event = new Function(js('wp-sdtrk-event.js') + '\nreturn Wp_Sdtrk_Event;')();
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Catcher_Ga = new Function(js('wp-sdtrk-ga.js') + '\nreturn Wp_Sdtrk_Catcher_Ga;')();

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}

function catcher(ev) {
	const c = Object.create(Wp_Sdtrk_Catcher_Ga.prototype);
	c.event = ev;
	c.localizedData = { pid: 'G-XYZ' };
	return c;
}

console.log('GA get_data_custom — multi-product + currency');
const ev = new Wp_Sdtrk_Event();
ev.setEventName({ wc: 'purchase' });
ev.setOrderId({ wc: '4711' });
ev.setValue({ wc: '2150' });
ev.setCurrency('USD');
ev.setBrandName('EIA');
ev.setItems([
	{ id: '24215', name: 'Hybridlehrgang', qty: 1, price: 2000 },
	{ id: '777', name: 'Skript', qty: 2, price: 75 },
]);
ev.setProdId({ wc: '24215' });
ev.setProdName({ wc: 'Hybridlehrgang' });
const cd = catcher(ev).get_data_custom();
check('currency USD', cd.currency === 'USD');
check('value 2150', cd.value === 2150);
check('transaction_id = order id', cd.transaction_id === '4711');
check('two items', Array.isArray(cd.items) && cd.items.length === 2);
check('item 0 id', cd.items[0].id === '24215');
check('item 1 quantity 2', cd.items[1].quantity === 2);
check('item 1 price 75', cd.items[1].price === 75);
check('item 1 name', cd.items[1].name === 'Skript');

console.log('GA get_data_custom — single-product fallback + EUR fallback');
const single = new Wp_Sdtrk_Event();
single.setEventName({ p: 'view_item' });
single.setProdId({ p: '999' });
single.setProdName({ p: 'Solo' });
single.setValue({ p: '50' });
single.setBrandName('EIA');
const cd2 = catcher(single).get_data_custom();
check('EUR fallback', cd2.currency === 'EUR');
check('single item', Array.isArray(cd2.items) && cd2.items.length === 1 && cd2.items[0].id === '999');

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
