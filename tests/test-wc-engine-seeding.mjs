/**
 * Contract test for the WooCommerce seeding the engine performs in
 * collect_eventData() (see wp-sdtrk-engine.js). It does not boot the full engine
 * (which needs the DOM + all catcher globals); instead it applies the exact same
 * field shapes to a real Wp_Sdtrk_Event and asserts the getter outcomes the
 * downstream catchers + server dedup rely on.
 *
 * Run:  node tests/test-wc-engine-seeding.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const src = readFileSync(join(here, '..', 'public', 'js', 'wp-sdtrk-event.js'), 'utf8');
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Event = new Function(src + '\nreturn Wp_Sdtrk_Event;')();

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}

// Mirror of the localized wp_sdtrk_wc.order payload.
const wc = {
	orderId: '32548',
	value: '2150',
	currency: 'EUR',
	email: 'buyer@example.com',
	firstName: 'Ada',
	lastName: 'Lovelace',
	items: [
		{ id: '24215', name: 'Hybridlehrgang', qty: 1, price: 2000 },
		{ id: '777', name: 'Skript', qty: 2, price: 75 },
	],
};

// Exact seeding block from collect_eventData().
const ev = new Wp_Sdtrk_Event();
ev.setOrderId({ wc: String(wc.orderId || '') });
ev.setEventName({ wc: 'purchase' });
ev.setValue({ wc: String(wc.value || '') });
ev.setCurrency(wc.currency || '');
ev.setUserEmail({ wc: String(wc.email || '') });
ev.setUserFirstName({ wc: String(wc.firstName || '') });
ev.setUserLastName({ wc: String(wc.lastName || '') });
ev.setItems(Array.isArray(wc.items) ? wc.items : []);
if (Array.isArray(wc.items) && wc.items.length > 0) {
	ev.setProdId({ wc: String(wc.items[0].id || '') });
	ev.setProdName({ wc: String(wc.items[0].name || '') });
}

console.log('WooCommerce engine seeding -> event getters');
check('grabEventName() === purchase', ev.grabEventName() === 'purchase');
check('grabOrderId() === order id (dedup)', ev.grabOrderId() === '32548');
check('grabValue() === 2150', ev.grabValue() === 2150);
check('getCurrency() === EUR', ev.getCurrency() === 'EUR');
check('getUserEmail() === buyer', ev.getUserEmail() === 'buyer@example.com');
check('getUserFirstName() === Ada', ev.getUserFirstName() === 'Ada');
check('getUserLastName() === Lovelace', ev.getUserLastName() === 'Lovelace');
check('getItems().length === 2', ev.getItems().length === 2);
check('first item id 24215', ev.grabProdId() === '24215');

// Auto-purchase: even without an explicit eventName, an order id implies purchase.
const auto = new Wp_Sdtrk_Event();
auto.setOrderId({ wc: '32548' });
auto.setEventName({ wc: '' });
auto.setProdId({ wc: '' });
check('auto purchase from orderId', auto.grabEventName() === 'purchase');

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
