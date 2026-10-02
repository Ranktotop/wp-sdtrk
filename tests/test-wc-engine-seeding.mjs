/**
 * Real-engine test for the WooCommerce seeding in wp-sdtrk-engine.js. Unlike a
 * mirror test, this loads the ACTUAL Wp_Sdtrk_Engine class and invokes its real
 * seedWcCommerce()/seedCommerceEvent() methods against a Wp_Sdtrk_Event, so the
 * precedence chain (order > addToCart > viewItem), the per-branch field wiring and
 * the order-branch localStorage once-guard are genuinely covered — a dropped
 * setCurrency, a wrong String() cast or a reordered branch would fail here.
 *
 * The engine class is eval'd with a minimal stub scope (window.localStorage +
 * a no-op Wp_Sdtrk_Decrypter for the file's bootstrap tail). collect_eventData()
 * itself (DOM/helper/fp) is NOT booted; only the pure seeding methods are.
 *
 * Run:  node tests/test-wc-engine-seeding.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const eventSrc = readFileSync(join(here, '..', 'public', 'js', 'wp-sdtrk-event.js'), 'utf8');
const engineSrc = readFileSync(join(here, '..', 'public', 'js', 'wp-sdtrk-engine.js'), 'utf8');

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
const mod = new Function(preamble + '\n' + eventSrc + '\n' + engineSrc +
	'\nreturn { Engine: Wp_Sdtrk_Engine, Event: Wp_Sdtrk_Event, ls: __ls };')();

const { Engine, Event } = mod;

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}

// Build an engine instance without running the heavy constructor; only the
// seeding methods (which use this.event) are exercised.
function seed(wc) {
	const engine = Object.create(Engine.prototype);
	engine.event = new Event();
	engine.seedWcCommerce(wc);
	return engine.event;
}

console.log('view_item seeding (real seedWcCommerce/seedCommerceEvent)');
const vi = seed({ viewItem: { value: '49', currency: 'USD', items: [{ id: '24215', name: 'Hybridlehrgang', qty: 1, price: 49 }] } });
check('eventName view_item', vi.grabEventName() === 'view_item');
check('value 49', vi.grabValue() === 49);
check('currency USD', vi.getCurrency() === 'USD');
check('items length 1', vi.getItems().length === 1);
check('prodId 24215', vi.grabProdId() === '24215');
check('prodName Hybridlehrgang', vi.grabProdName() === 'Hybridlehrgang');

console.log('add_to_cart seeding (merged multi-add)');
const atc = seed({ addToCart: { value: '25.5', currency: 'USD', items: [{ id: '1', name: 'A', qty: 2, price: 10 }, { id: '2', name: 'B', qty: 1, price: 5.5 }] } });
check('eventName add_to_cart', atc.grabEventName() === 'add_to_cart');
check('value 25.5', atc.grabValue() === 25.5);
check('items length 2', atc.getItems().length === 2);
check('prodId first item', atc.grabProdId() === '1');

console.log('purchase seeding (order branch: buyer data + order id)');
const pur = seed({ order: { orderId: '4711', value: '59', currency: 'EUR', email: 'a@b.c', firstName: 'Ada', lastName: 'L', items: [{ id: '808', name: 'T', qty: 1, price: 59 }] } });
check('eventName purchase', pur.grabEventName() === 'purchase');
check('orderId 4711 (dedup)', pur.grabOrderId() === '4711');
check('email seeded', pur.getUserEmail() === 'a@b.c');
check('firstName seeded', pur.getUserFirstName() === 'Ada');
check('currency EUR', pur.getCurrency() === 'EUR');
check('prodId 808', pur.grabProdId() === '808');

console.log('begin_checkout seeding (checkout page, whole cart)');
const bc = seed({ beginCheckout: { value: '30', currency: 'USD', items: [{ id: '501', name: 'A', qty: 2, price: 15 }] } });
check('eventName begin_checkout', bc.grabEventName() === 'begin_checkout');
check('value 30', bc.grabValue() === 30);
check('currency USD', bc.getCurrency() === 'USD');
check('items length 1', bc.getItems().length === 1);
check('prodId 501', bc.grabProdId() === '501');

console.log('precedence order > beginCheckout > addToCart > viewItem (real else-if chain)');
const all = seed({ order: { orderId: '1', value: '1', items: [] }, beginCheckout: { value: '3', items: [] }, addToCart: { value: '5', items: [] }, viewItem: { value: '9', items: [] } });
check('order wins over all', all.grabEventName() === 'purchase');
const bcVsAtc = seed({ beginCheckout: { value: '3', items: [] }, addToCart: { value: '5', items: [] }, viewItem: { value: '9', items: [] } });
check('beginCheckout wins over addToCart', bcVsAtc.grabEventName() === 'begin_checkout');
const atcVsVi = seed({ addToCart: { value: '5', items: [] }, viewItem: { value: '9', items: [] } });
check('addToCart wins over viewItem', atcVsVi.grabEventName() === 'add_to_cart');

console.log('no commerce source => nothing seeded');
const none = seed({});
check('eventName false (nothing set)', none.grabEventName() === false);

// Seed an order and run the consent step with one catcher in the given state.
const order = (id) => ({ order: { orderId: id, value: '5', items: [{ id: '5', name: 'x', qty: 1, price: 5 }] } });
function seedOrder(id, consent) {
	const engine = Object.create(Engine.prototype);
	engine.event = new Event();
	engine.helper = { persist: () => {} };
	engine.utmParams = {};
	engine.catcher_meta = { isEnabled: () => consent };
	engine.seedWcCommerce(order(id));
	engine.persist_onConsent();
	return engine;
}

console.log('order localStorage once-guard (reload does not re-seed)');
const first = seedOrder('999', true);
check('first load seeds purchase', first.event.grabEventName() === 'purchase');
check('guard written with consent', mod.ls['wp_sdtrk_wc_999'] === '1');
const second = seedOrder('999', true);
check('reload does not re-seed (guard)', second.event.grabEventName() === false && second.event.getCurrency() === '');

console.log('order once-guard needs consent');
const noConsent = seedOrder('777', false);
check('purchase seeded', noConsent.event.grabEventName() === 'purchase');
check('no guard written without consent', !('wp_sdtrk_wc_777' in mod.ls));
noConsent.catcher_meta.isEnabled = () => true;
noConsent.persist_onConsent();
check('guard written on later opt-in (backload)', mod.ls['wp_sdtrk_wc_777'] === '1');

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
