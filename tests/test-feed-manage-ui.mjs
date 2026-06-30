/**
 * Real-code test for the pure render/escape + optimistic-state logic in
 * admin/js/wp-sdtrk-admin-feed-manage.js.
 *
 * The file is a browser IIFE `(function ($) { ... })(jQuery)`, so its helpers
 * live in a closure. Rather than touch production code, we extract the IIFE
 * BODY and re-run it through new Function(), appending a `return` that exposes
 * the helpers — the same "load the real source" approach as the engine .mjs
 * tests. A tiny $ shim provides only what the pure helpers touch:
 * `$('<div>').text(x).html()` (faithful &<> encoder) and a no-op ready
 * callback so the DOM-wiring half never runs.
 *
 * Covered: esc()/escAttr() escaping (the XSS-relevant path), rowHtml() row
 * rendering incl. the status toggle + attribute safety, and applyRowState()
 * (the core of the optimistic-toggle + rollback). NOT covered here: the
 * event-driven toggle/bulk wiring, $.post, and the in-flight disable guard —
 * those are integration-level (jQuery + DOM) and remain consistent with the
 * other untested admin JS files; the server contract behind them is fully
 * unit-tested (test-admin-ajax-feed.php).
 *
 * Run:  node tests/test-feed-manage-ui.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const src = readFileSync(join(here, '..', 'admin', 'js', 'wp-sdtrk-admin-feed-manage.js'), 'utf8');

let fails = 0;
function check(label, cond) {
    if (cond) { console.log('  PASS: ' + label); }
    else { console.log('  FAIL: ' + label); fails++; }
}

// --- Extract the IIFE body so the closure helpers become returnable ---
const OPEN = '(function ($) {';
const CLOSE = '})(jQuery);';
if (!src.includes(OPEN) || !src.includes(CLOSE)) {
    console.log('  FAIL: could not locate the IIFE wrapper (file shape changed)');
    process.exit(1);
}
let body = src.slice(src.indexOf(OPEN) + OPEN.length);
body = body.slice(0, body.lastIndexOf(CLOSE));

// --- Minimal jQuery shim: only what the pure helpers use ---
function htmlEncode(s) {
    // Faithful to a browser's $('<div>').text(x).html(): encodes & < > (not quotes).
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function textNode() {
    let t = '';
    return {
        text(v) { t = (v == null) ? '' : String(v); return this; },
        html() { return htmlEncode(t); }
    };
}
const $ = function (arg) {
    if (typeof arg === 'function') { return undefined; } // skip the DOM-ready callback
    return textNode();                                   // $('<div>') in esc()
};
$.post = function () { return { then() { return { always() {} }; } }; };

const cfg = { ajaxUrl: '', nonce: 'n', perPage: 50, i18n: { excluded: 'Ausgeschlossen', inFeed: 'Im Feed' } };

// eslint-disable-next-line no-new-func
const api = new Function('$', 'SDTRK_FeedManage', 'window',
    body + '\n return { esc: esc, escAttr: escAttr, rowHtml: rowHtml, applyRowState: applyRowState };'
)($, cfg, { wpsdtrk_show_notice: function () {} });

const { esc, escAttr, rowHtml, applyRowState } = api;

console.log('esc() — text-context escaping');
check('encodes < > &',                 esc('a<b>&c') === 'a&lt;b&gt;&amp;c');
check('leaves quotes (text ctx)',      esc('he said "hi" it\'s') === 'he said "hi" it\'s');
check('null => empty string',          esc(null) === '');

console.log('escAttr() — attribute-context escaping');
const ea = escAttr('x"y\'z<w&v');
check('encodes double quote',          ea.includes('&quot;') && !ea.includes('"'));
check('encodes single quote',          ea.includes('&#39;') && !ea.includes("'"));
check('still encodes <',               ea.includes('&lt;') && !ea.includes('<'));

console.log('rowHtml() — rendering + status toggle');
const inFeed = rowHtml({ id: 7, name: 'Alpha', sku: 'SKU-7', price: '10,00 €', image: '', excluded: false });
check('in-feed row class',             inFeed.includes('class="is-in-feed"'));
check('in-feed toggle checked',        /class="wpsdtrk-feed-status" checked/.test(inFeed));
check('in-feed label',                 inFeed.includes('>Im Feed<'));
check('no <img> when image empty',     !inFeed.includes('<img'));

const excluded = rowHtml({ id: 8, name: 'Beta', sku: 'SKU-8', price: '20,00 €', image: 'http://s/i.jpg', excluded: true });
check('excluded row class',            excluded.includes('class="is-excluded"'));
check('excluded toggle NOT checked',   !/class="wpsdtrk-feed-status" checked/.test(excluded));
check('excluded label',                excluded.includes('>Ausgeschlossen<'));
check('renders <img> when image set',  excluded.includes('<img src="http://s/i.jpg"'));

console.log('rowHtml() — XSS safety');
const xss = rowHtml({
    id: 9,
    name: '<script>alert(1)</script>',
    sku: 'a&b',
    price: '1',
    image: 'x" onerror="alert(1)',
    excluded: false
});
check('script tag in name escaped',    !xss.includes('<script>') && xss.includes('&lt;script&gt;'));
check('ampersand in sku escaped',      xss.includes('a&amp;b'));
check('image attr cannot break out',   !xss.includes('onerror="') && xss.includes('onerror=&quot;'));

console.log('applyRowState() — optimistic state application');
function fakeTr() {
    const calls = { toggle: [], checked: undefined, label: undefined };
    const node = {
        toggleClass(cls, on) { calls.toggle.push([cls, on]); return node; },
        find(sel) {
            return {
                prop(name, val) { if (sel.includes('status')) { calls.checked = val; } return this; },
                text(v) { if (sel.includes('label')) { calls.label = v; } return this; }
            };
        },
        _calls: calls
    };
    return node;
}

const trEx = fakeTr();
applyRowState(trEx, true); // mark excluded
check('excluded: adds is-excluded',    trEx._calls.toggle.some(([c, on]) => c === 'is-excluded' && on === true));
check('excluded: removes is-in-feed',  trEx._calls.toggle.some(([c, on]) => c === 'is-in-feed' && on === false));
check('excluded: toggle unchecked',    trEx._calls.checked === false);
check('excluded: label = Ausgeschlossen', trEx._calls.label === 'Ausgeschlossen');

const trIn = fakeTr();
applyRowState(trIn, false); // back to in-feed (rollback direction)
check('in-feed: adds is-in-feed',      trIn._calls.toggle.some(([c, on]) => c === 'is-in-feed' && on === true));
check('in-feed: toggle checked',       trIn._calls.checked === true);
check('in-feed: label = Im Feed',      trIn._calls.label === 'Im Feed');

if (fails > 0) {
    console.log('\n' + fails + ' assertion(s) failed.');
    process.exit(1);
}
console.log('\nAll assertions passed.');
process.exit(0);
