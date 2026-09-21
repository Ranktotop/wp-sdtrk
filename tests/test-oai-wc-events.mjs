/**
 * WooCommerce -> ChatGPT Ads (OpenAI) pixel: seeds the event with the REAL
 * engine seedWcCommerce() for each WC source (order / beginCheckout /
 * addToCart / viewItem) and checks the measure call the oai catcher queues:
 * event name, order id as event_id, integer minor-unit amounts and the
 * numeric WC product id in contents[].id.
 *
 * Run:  node tests/test-oai-wc-events.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const js = (f) => readFileSync(join(here, '..', 'public', 'js', f), 'utf8');

const preamble = `
	var __ls = {};
	var window = {
		localStorage: {
			getItem: function (k) { return Object.prototype.hasOwnProperty.call(__ls, k) ? __ls[k] : null; },
			setItem: function (k, v) { __ls[k] = String(v); },
			removeItem: function (k) { delete __ls[k]; }
		},
		history: { replaceState: function () {} }
	};
	function Wp_Sdtrk_Decrypter() {}
	Wp_Sdtrk_Decrypter.prototype.decrypt = function () {};
`;
// eslint-disable-next-line no-new-func
const mod = new Function(preamble + '\n' + js('wp-sdtrk-event.js') + '\n' + js('wp-sdtrk-engine.js') + '\n' + js('wp-sdtrk-oai.js') +
	'\nreturn { Engine: Wp_Sdtrk_Engine, Event: Wp_Sdtrk_Event, Oai: Wp_Sdtrk_Catcher_Oai };')();

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}

const queue = [];
globalThis.oaiq = (...args) => queue.push(args);

function measureFor(wc) {
	const engine = Object.create(mod.Engine.prototype);
	engine.event = new mod.Event();
	engine.event.setEventId('777000');
	engine.seedWcCommerce(wc);
	const c = Object.create(mod.Oai.prototype);
	c.event = engine.event;
	c.b_enabled = true;
	c.pixelLoaded = true;
	c.localizedData = { dbg: '' };
	c.helper = { debugLog: () => {} };
	const before = queue.length;
	c.catchEventHit(0);
	return queue.slice(before);
}

const cart = [
	{ id: '24215', name: 'Hybridlehrgang', qty: 1, price: 2000 },
	{ id: '777', name: 'Skript', qty: 2, price: 75.5 },
];

console.log('WC order -> order_created');
let calls = measureFor({ order: { orderId: '4711', value: '2151', currency: 'EUR', items: cart, email: 'a@b.de' } });
check('one measure call', calls.length === 1);
check('order_created', calls[0][1] === 'order_created');
check('event_id = order id', calls[0][3].event_id === '4711');
check('amount 215100 EUR', calls[0][2].amount === 215100 && calls[0][2].currency === 'EUR');
check('two contents with numeric product ids', calls[0][2].contents.map((x) => x.id).join(',') === '24215,777');
check('content amount 7550', calls[0][2].contents[1].amount === 7550);
check('no email in pixel data', JSON.stringify(calls[0]).indexOf('a@b.de') === -1);

console.log('WC beginCheckout -> checkout_started');
calls = measureFor({ beginCheckout: { value: '2151', currency: 'EUR', items: cart } });
check('checkout_started', calls.length === 1 && calls[0][1] === 'checkout_started');
check('event_id = engine event id', calls[0][3].event_id === '777000');
check('amount 215100', calls[0][2].amount === 215100);

console.log('WC addToCart -> items_added');
calls = measureFor({ addToCart: { value: '75.5', currency: 'USD', items: [cart[1]] } });
check('items_added', calls.length === 1 && calls[0][1] === 'items_added');
check('amount 7550 USD', calls[0][2].amount === 7550 && calls[0][2].currency === 'USD');
check('quantity 2', calls[0][2].contents[0].quantity === 2);

console.log('WC viewItem -> contents_viewed');
calls = measureFor({ viewItem: { value: '49', currency: 'JPY', items: [{ id: '24215', name: 'Hybridlehrgang', qty: 1, price: 49 }] } });
check('contents_viewed', calls.length === 1 && calls[0][1] === 'contents_viewed');
check('JPY has no minor unit: amount 49', calls[0][2].amount === 49 && calls[0][2].currency === 'JPY');

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
