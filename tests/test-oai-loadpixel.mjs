/**
 * Unit test for Wp_Sdtrk_Catcher_Oai.loadPixel() + the browser Page hit:
 * the official oaiq snippet must inject oaiq.min.js exactly once, queue
 * init({pixelId, debug}) and measure page_viewed with the shared event_id.
 *
 * Run:  node tests/test-oai-loadpixel.mjs
 *
 * loadPixel touches window/document, so window is aliased to globalThis
 * (the snippet assigns w.oaiq and the catcher calls the bare oaiq) and a
 * minimal document stub captures inserted <script> elements.
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

const inserted = [];
globalThis.window = globalThis;
globalThis.document = {
	cookie: '',
	createElement: () => ({}),
	getElementsByTagName: () => [{ parentNode: { insertBefore: (s) => inserted.push(s) } }],
};

let fails = 0;
function check(label, cond) {
	if (cond) { console.log('  PASS: ' + label); }
	else { console.log('  FAIL: ' + label); fails++; }
}

const ev = new Wp_Sdtrk_Event();
ev.setEventId('42170000');
ev.setPageId('17');
ev.setPageName('Preise');

const c = Object.create(Wp_Sdtrk_Catcher_Oai.prototype);
c.event = ev;
c.b_enabled = true;
c.pixelLoaded = false;
c.localizedData = { pid: '4J4brGr1XUDTEKZhUQjeaa', dbg: '1' };
c.helper = { debugLog: () => {}, get_Cookie: () => null };

console.log('OpenAI loadPixel injects oaiq.min.js once and inits the pixel');
c.loadPixel();
c.loadPixel();
const sdk = inserted.filter((s) => s.src === 'https://bzrcdn.openai.com/sdk/oaiq.min.js');
check('exactly one oaiq.min.js script inserted', sdk.length === 1);
check('script is async', sdk.length === 1 && sdk[0].async === true);
check('pixelLoaded set', c.pixelLoaded === true);
check('oaiq queue exists', typeof globalThis.oaiq === 'function' && Array.isArray(globalThis.oaiq.q));
const init = globalThis.oaiq.q.find((a) => a[0] === 'init');
check('init carries the pixel id', !!init && init[1].pixelId === '4J4brGr1XUDTEKZhUQjeaa');
check('init debug follows dbg', !!init && init[1].debug === true);
check('init has no user object', !!init && !('user' in init[1]));

console.log('OpenAI Page hit measures page_viewed');
c.fireData('Page', { state: true });
const pv = globalThis.oaiq.q.find((a) => a[0] === 'measure' && a[1] === 'page_viewed');
check('page_viewed queued', !!pv);
check('data type contents', !!pv && pv[2].type === 'contents');
check('page content id/name/type', !!pv && pv[2].contents[0].id === '17' && pv[2].contents[0].name === 'Preise' && pv[2].contents[0].content_type === 'page');
check('event_id = engine event id', !!pv && pv[3].event_id === '42170000');

console.log('OpenAI does nothing when the browser path is disabled');
const before = globalThis.oaiq.q.length;
const off = Object.create(Wp_Sdtrk_Catcher_Oai.prototype);
off.event = ev;
off.b_enabled = false;
off.pixelLoaded = false;
off.localizedData = { pid: 'X', dbg: '' };
off.helper = c.helper;
off.loadPixel();
off.fireData('Page', { state: true });
check('no additional queue entries', globalThis.oaiq.q.length === before);

if (fails > 0) {
	console.log('\n' + fails + ' assertion(s) failed.');
	process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
