/**
 * Contract test for the WooCommerce ViewItem seeding the engine performs in
 * collect_eventData() (see wp-sdtrk-engine.js). Two layers:
 *   1. Source assertion: the engine actually contains the wp_sdtrk_wc.viewItem
 *      seeding branch (guards against the branch being dropped/renamed).
 *   2. Behavior: applying the exact field shapes to a real Wp_Sdtrk_Event yields
 *      the getter outcomes the downstream catchers + server dedup rely on.
 *
 * Run:  node tests/test-wc-view-item-seeding.mjs
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

console.log('engine source contains the ViewItem seeding branch');
check('reads wp_sdtrk_wc.viewItem', engineSrc.includes('wp_sdtrk_wc.viewItem'));
check("seeds 'view_item' event name", engineSrc.includes("'view_item'"));

// Mirror of the localized wp_sdtrk_wc.viewItem payload.
const vi = {
	prodId: '24215',
	name: 'Hybridlehrgang',
	value: '49',
	currency: 'USD',
	items: [{ id: '24215', name: 'Hybridlehrgang', qty: 1, price: 49 }],
};

// Exact seeding block from collect_eventData() (viewItem branch).
const ev = new Wp_Sdtrk_Event();
ev.setProdId({});
ev.setEventId('77' + '1700000000000');
ev.setEventName({ wc: 'view_item' });
ev.setValue({ wc: String(vi.value || '') });
ev.setCurrency(vi.currency || '');
ev.setItems(Array.isArray(vi.items) ? vi.items : []);
if (Array.isArray(vi.items) && vi.items.length > 0) {
	ev.setProdId({ wc: String(vi.items[0].id || '') });
	ev.setProdName({ wc: String(vi.items[0].name || '') });
}

console.log('ViewItem engine seeding -> event getters');
check('grabEventName() === view_item', ev.grabEventName() === 'view_item');
check('grabValue() === 49', ev.grabValue() === 49);
check('getCurrency() === USD', ev.getCurrency() === 'USD');
check('getItems().length === 1', ev.getItems().length === 1);
check('grabProdId() === 24215', ev.grabProdId() === '24215');
check('grabProdName() === Hybridlehrgang', ev.grabProdName() === 'Hybridlehrgang');
// No order id -> browser/server dedup falls back to the shared engine eventId.
check('grabOrderId() falls back to eventId', ev.grabOrderId() === '771700000000000');

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
