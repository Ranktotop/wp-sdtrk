/**
 * Standalone unit test for the multi-product + currency additions to the
 * browser-side event model (Wp_Sdtrk_Event).
 *
 * Run:  node tests/test-event-items-currency.mjs
 *
 * The class file has no module exports and no external deps, so it is evaluated
 * in a fresh function scope and the class is returned.
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

console.log('Wp_Sdtrk_Event items/currency');

const empty = new Wp_Sdtrk_Event();
check('getItems() default []', Array.isArray(empty.getItems()) && empty.getItems().length === 0);
check('getCurrency() default ""', empty.getCurrency() === '');

const ev = new Wp_Sdtrk_Event();
const items = [{ id: '101', name: 'A', qty: 1, price: 99.9 }];
ev.setItems(items);
ev.setCurrency('USD');
check('setItems/getItems', ev.getItems() === items && ev.getItems().length === 1);
check('setCurrency/getCurrency', ev.getCurrency() === 'USD');

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
