/**
 * Contract test for the WooCommerce AddToCart seeding the engine performs in
 * collect_eventData() (see wp-sdtrk-engine.js). Two layers:
 *   1. Source assertion: the engine contains the wp_sdtrk_wc.addToCart branch,
 *      and it precedes the viewItem branch (precedence order > addToCart > viewItem).
 *   2. Behavior: applying the exact field shapes to a real Wp_Sdtrk_Event yields
 *      the add_to_cart getter outcomes catchers + server dedup rely on.
 *
 * Run:  node tests/test-wc-add-to-cart-seeding.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const eventSrc = readFileSync(join(here, '..', 'public', 'js', 'wp-sdtrk-event.js'), 'utf8');
const engineSrc = readFileSync(join(here, '..', 'public', 'js', 'wp-sdtrk-engine.js'), 'utf8');
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Event = new Function(eventSrc + '\nreturn Wp_Sdtrk_Event;')();

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}

console.log('engine source contains the AddToCart branch with correct precedence');
check('reads wp_sdtrk_wc.addToCart', engineSrc.includes('wp_sdtrk_wc.addToCart'));
check("seeds 'add_to_cart' event name", engineSrc.includes("'add_to_cart'"));
check('addToCart branch precedes viewItem branch',
	engineSrc.indexOf('wp_sdtrk_wc.addToCart') < engineSrc.indexOf('wp_sdtrk_wc.viewItem'));

// Mirror of the localized wp_sdtrk_wc.addToCart payload (two merged adds).
const atc = {
	value: '25.5',
	currency: 'USD',
	items: [
		{ id: '1', name: 'A', qty: 2, price: 10 },
		{ id: '2', name: 'B', qty: 1, price: 5.5 },
	],
};

const ev = new Wp_Sdtrk_Event();
ev.setProdId({});
ev.setOrderId({});
ev.setEventId('77' + '1700000000000');
ev.setEventName({ wc: 'add_to_cart' });
ev.setValue({ wc: String(atc.value || '') });
ev.setCurrency(atc.currency || '');
ev.setItems(Array.isArray(atc.items) ? atc.items : []);
if (Array.isArray(atc.items) && atc.items.length > 0) {
	ev.setProdId({ wc: String(atc.items[0].id || '') });
	ev.setProdName({ wc: String(atc.items[0].name || '') });
}

console.log('AddToCart engine seeding -> event getters');
check('grabEventName() === add_to_cart', ev.grabEventName() === 'add_to_cart');
check('grabValue() === 25.5', ev.grabValue() === 25.5);
check('getCurrency() === USD', ev.getCurrency() === 'USD');
check('getItems().length === 2 (merged adds)', ev.getItems().length === 2);
check('grabProdId() === first item', ev.grabProdId() === '1');
check('grabOrderId() falls back to eventId', ev.grabOrderId() === '771700000000000');

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
