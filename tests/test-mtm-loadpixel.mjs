/**
 * Regression test for Wp_Sdtrk_Catcher_Mtm.loadPixel(): the Matomo tracker
 * script (matomo.js) must be injected, otherwise the _paq queue never flushes
 * and nothing is sent to Matomo (official embed requires the script injection).
 *
 * Run:  node tests/test-mtm-loadpixel.mjs
 *
 * loadPixel touches window/_paq/document, so window is aliased to globalThis
 * (so `window._paq = …` and the bare `_paq.push` resolve to the same object)
 * and a minimal document stub captures inserted <script> elements.
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const src = readFileSync(join(here, '..', 'public', 'js', 'wp-sdtrk-mtm.js'), 'utf8');
// eslint-disable-next-line no-new-func
const Wp_Sdtrk_Catcher_Mtm = new Function(src + '\nreturn Wp_Sdtrk_Catcher_Mtm;')();

const inserted = [];
globalThis.window = globalThis;
globalThis._paq = undefined;
globalThis.document = {
	domain: 'shop.example',
	createElement: () => ({}),
	getElementsByTagName: () => [{ parentNode: { insertBefore: (g) => inserted.push(g) } }],
};

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}

const c = Object.create(Wp_Sdtrk_Catcher_Mtm.prototype);
c.b_enabled = true;
c.pixelLoaded = false;
c.localizedData = { pid: 'https://matomo.example', sid: '7', debug: false };
c.helper = { get_Cookie: () => false, debugLog: () => {} };
c.event = { getPageName: () => 'Kasse' };

c.loadPixel();

console.log('Matomo loadPixel injects matomo.js');
const matomoScript = inserted.find((g) => typeof g.src === 'string' && g.src.endsWith('matomo.js'));
check('a <script> with src ending matomo.js was inserted', !!matomoScript);
check('matomo.js loaded from the configured base url', !!matomoScript && matomoScript.src === 'https://matomo.example/matomo.js');
check('script is async', !!matomoScript && matomoScript.async === true);
check('_paq queue was populated', Array.isArray(globalThis._paq) && globalThis._paq.length > 0);
check('pixelLoaded set', c.pixelLoaded === true);

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
