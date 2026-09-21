/**
 * Unit test for the Wp_Sdtrk_Catcher_Oai conversion events: event-name mapping,
 * data shapes (contents / customer_action), integer minor-unit amounts and the
 * pixel-safe contents[] fields.
 *
 * Run:  node tests/test-oai-custom-data.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const js = (f) => readFileSync(join(here, '..', 'public', 'js', f), 'utf8');
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Event = new Function(js('wp-sdtrk-event.js') + '\nreturn Wp_Sdtrk_Event;')();
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Catcher_Oai = new Function(js('wp-sdtrk-oai.js') + '\nreturn Wp_Sdtrk_Catcher_Oai;')();

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}

const queue = [];
globalThis.oaiq = (...args) => queue.push(args);

function catcher(ev) {
	const c = Object.create(Wp_Sdtrk_Catcher_Oai.prototype);
	c.event = ev;
	c.b_enabled = true;
	c.pixelLoaded = true;
	c.localizedData = { pid: 'PID', dbg: '' };
	c.helper = { debugLog: () => {}, get_EventName: (t, d) => ({ Time: 'time_spent_%', Scroll: 'scroll_depth_%', Click: 'button_click', Visibility: 'item_visit' }[t] || t).replace('%', d) };
	return c;
}

console.log('OpenAI event-name mapping');
const c0 = catcher(new Wp_Sdtrk_Event());
check('view_item -> contents_viewed', c0.convert_eventname('view_item') === 'contents_viewed');
check('generate_lead -> lead_created', c0.convert_eventname('generate_lead') === 'lead_created');
check('sign_up -> registration_completed', c0.convert_eventname('sign_up') === 'registration_completed');
check('add_to_cart -> items_added', c0.convert_eventname('add_to_cart') === 'items_added');
check('begin_checkout -> checkout_started', c0.convert_eventname('begin_checkout') === 'checkout_started');
check('purchase -> order_created', c0.convert_eventname('purchase') === 'order_created');
check('unknown -> false', c0.convert_eventname('foo') === false);

console.log('OpenAI minor units (ISO 4217 exponent)');
check('EUR 25.99 -> 2599', c0.toMinorUnits(25.99, 'EUR') === 2599);
check('USD 0.1+0.2 rounds -> 30', c0.toMinorUnits(0.1 + 0.2, 'USD') === 30);
check('JPY 1500 -> 1500', c0.toMinorUnits(1500, 'JPY') === 1500);
check('KWD 1.5 -> 1500', c0.toMinorUnits(1.5, 'KWD') === 1500);
check('lowercase code accepted', c0.toMinorUnits(2, 'jpy') === 2);
check('CLF 1.2345 -> 12345', c0.toMinorUnits(1.2345, 'CLF') === 12345);

console.log('OpenAI purchase — multi-product contents[]');
const ev = new Wp_Sdtrk_Event();
ev.setEventId('999');
ev.setEventName({ wc: 'purchase' });
ev.setOrderId({ wc: '4711' });
ev.setValue({ wc: '2150.5' });
ev.setCurrency('USD');
ev.setItems([
	{ id: '24215', name: 'Hybridlehrgang', qty: 1, price: 2000 },
	{ id: '777', name: 'Skript', qty: '2', price: 75.25 },
]);
ev.setProdId({ wc: '24215' });
ev.setProdName({ wc: 'Hybridlehrgang' });
ev.setUtm({ utm_source: 'chatgpt' });
const cp = catcher(ev);
const d = cp.get_data_event();
check('type contents', d.type === 'contents');
check('amount 215050 (integer minor units)', d.amount === 215050);
check('currency USD', d.currency === 'USD');
check('two contents', Array.isArray(d.contents) && d.contents.length === 2);
check('content id string', d.contents[0].id === '24215');
check('content name', d.contents[1].name === 'Skript');
check('content_type product', d.contents[0].content_type === 'product');
check('quantity integer 2', d.contents[1].quantity === 2);
check('content amount 7525', d.contents[1].amount === 7525);
check('content currency USD', d.contents[1].currency === 'USD');
const allowed = ['id', 'name', 'content_type', 'quantity', 'amount', 'currency'];
check('only pixel-supported content fields', d.contents.every((x) => Object.keys(x).every((k) => allowed.includes(k))));
check('no UTM or other stray top-level fields', Object.keys(d).every((k) => ['type', 'amount', 'currency', 'contents'].includes(k)));
cp.fireData('Event', { state: true });
const m = queue.find((a) => a[0] === 'measure' && a[1] === 'order_created');
check('measure order_created queued', !!m);
check('event_id = order id', !!m && m[3].event_id === '4711');

console.log('OpenAI view_item — single product, EUR fallback, no amount without value');
const single = new Wp_Sdtrk_Event();
single.setEventName({ p: 'view_item' });
single.setProdId({ p: '999' });
single.setProdName({ p: 'Solo' });
single.setValue({ p: '' });
const ds = catcher(single).get_data_event();
check('contents_viewed data type contents', ds.type === 'contents');
check('single content', ds.contents.length === 1 && ds.contents[0].id === '999' && ds.contents[0].quantity === 1);
check('no amount without value', !('amount' in ds) && !('currency' in ds));
single.setValue({ p: '50' });
const ds2 = catcher(single).get_data_event();
check('EUR fallback with value', ds2.currency === 'EUR' && ds2.amount === 5000);

console.log('OpenAI generate_lead — customer_action');
const lead = new Wp_Sdtrk_Event();
lead.setEventName({ p: 'generate_lead' });
lead.setProdId({ p: '999' });
lead.setValue({ p: '' });
const dl = catcher(lead).get_data_event();
check('type customer_action', dl.type === 'customer_action');
check('no contents on customer_action', !('contents' in dl));
lead.setValue({ p: '19.9' });
const dl2 = catcher(lead).get_data_event();
check('lead value -> amount 1990 EUR', dl2.amount === 1990 && dl2.currency === 'EUR');

console.log('OpenAI unknown event — nothing measured');
const unknown = new Wp_Sdtrk_Event();
unknown.setEventName({ p: 'foo_bar' });
const before = queue.length;
catcher(unknown).fireData('Event', { state: true });
check('no measure call', queue.length === before);

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
