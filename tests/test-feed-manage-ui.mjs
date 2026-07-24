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

const cfg = { ajaxUrl: '', nonce: 'n', perPage: 50, i18n: {
    excluded: 'Ausgeschlossen', inFeed: 'Im Feed',
    imgNoImage: 'Kein Bild', imgTooSmallTip: 'Bild %s px', imgTooLargeTip: 'Bild %s',
    priceZero: 'Preis ist 0',
    descMissing: 'Beschreibung fehlt', descTooLongTip: 'Beschreibung zu lang (%s Zeichen)'
} };

// eslint-disable-next-line no-new-func
const api = new Function('$', 'SDTRK_FeedManage', 'window',
    body + '\n return { esc: esc, escAttr: escAttr, rowHtml: rowHtml, applyRowState: applyRowState, fieldIcon: fieldIcon, imageIssues: imageIssues };'
)($, cfg, { wpsdtrk_show_notice: function () {} });

const { esc, escAttr, rowHtml, applyRowState, fieldIcon, imageIssues } = api;

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
const inFeed = rowHtml({ id: 7, name: 'Alpha', price: '10,00 €', image: '', excluded: false });
check('in-feed row class',             inFeed.includes('class="is-in-feed"'));
check('in-feed toggle checked',        /class="wpsdtrk-feed-status" checked/.test(inFeed));
check('in-feed label',                 inFeed.includes('>Im Feed<'));
check('no <img> when image empty',     !inFeed.includes('<img'));
check('id cell shows the product id',  inFeed.includes('<td>7</td>'));

const excluded = rowHtml({ id: 8, name: 'Beta', price: '20,00 €', image: 'http://s/i.jpg', excluded: true });
check('excluded row class',            excluded.includes('class="is-excluded"'));
check('excluded toggle NOT checked',   !/class="wpsdtrk-feed-status" checked/.test(excluded));
check('excluded label',                excluded.includes('>Ausgeschlossen<'));
check('renders <img> when image set',  excluded.includes('<img src="http://s/i.jpg"'));

console.log('rowHtml() — no separate quality column');
check('row has 7 cells',               (inFeed.match(/<td/g) || []).length === 7);
check('clean row: no cell-error',      !inFeed.includes('wpsdtrk-cell-error'));
check('clean row: no error tint',      !inFeed.includes('wpsdtrk-has-error'));

console.log('rowHtml() — product name edit link');
const linked = rowHtml({ id: 5, name: 'Gamma', sku: 'S', price: '5', image: '', excluded: false,
    edit_url: 'http://s/wp-admin/post.php?post=5&action=edit' });
check('name is a link',                linked.includes('class="wpsdtrk-feed-name-link"'));
check('link opens in a new tab',       linked.includes('target="_blank"') && linked.includes('rel="noopener noreferrer"'));
check('link href is the edit url',     linked.includes('href="http://s/wp-admin/post.php?post=5&amp;action=edit"'));
check('link text is the product name', linked.includes('>Gamma</a>'));
check('no link without edit_url',      !inFeed.includes('wpsdtrk-feed-name-link'));

console.log('rowHtml() — field highlighting for hard feed errors');
const imgBad = rowHtml({ id: 10, name: 'Img', sku: 'S', price: '5', image: '', excluded: false,
    image_status: { ok: false, issues: ['no_image'], width: 0, height: 0, size: 0 } });
check('no_image => cell-error',        imgBad.includes('wpsdtrk-cell-error'));
check('no_image => row tint',          imgBad.includes('wpsdtrk-has-error'));
check('no_image => tooltip reason',    imgBad.includes('Kein Bild'));

const smallBad = rowHtml({ id: 11, name: 'S', sku: 'S', price: '5', image: 'http://x', excluded: false,
    image_status: { ok: false, issues: ['too_small'], width: 300, height: 400, size: 1000 } });
check('too_small => cell-error',       smallBad.includes('wpsdtrk-cell-error'));
check('too_small tooltip has dims',    smallBad.includes('300×400'));

const largeBad = rowHtml({ id: 12, name: 'S', sku: 'S', price: '5', image: 'http://x', excluded: false,
    image_status: { ok: false, issues: ['too_large'], width: 600, height: 600, size: 9 * 1024 * 1024 } });
check('too_large tooltip has MB',      largeBad.includes('9 MB'));

// An empty SKU is no longer a feed problem — the <g:id> is the numeric product
// id, so a missing SKU must NOT flag the row.
const noSku = rowHtml({ id: 13, name: 'S', price: '5', image: 'http://x', excluded: false, sku_missing: true });
check('empty sku => no cell-error',    !noSku.includes('wpsdtrk-cell-error'));
check('empty sku => no row tint',      !noSku.includes('wpsdtrk-has-error'));

const priceBad = rowHtml({ id: 14, name: 'S', sku: 'S', price: '0,00 €', image: 'http://x', excluded: false, price_missing: true });
check('price zero => cell-error',      priceBad.includes('wpsdtrk-cell-error'));
check('price zero => tooltip reason',  priceBad.includes('Preis ist 0'));

const descEmpty = rowHtml({ id: 17, name: 'S', sku: 'S', price: '5', image: 'http://x', excluded: false,
    description_status: { ok: false, issues: ['no_description'], length: 0 }, description_preview: '' });
check('empty desc => cell-error',      descEmpty.includes('wpsdtrk-cell-error'));
check('empty desc => row tint',        descEmpty.includes('wpsdtrk-has-error'));
check('empty desc => tooltip reason',  descEmpty.includes('Beschreibung fehlt'));

const descLong = rowHtml({ id: 18, name: 'S', sku: 'S', price: '5', image: 'http://x', excluded: false,
    description_status: { ok: false, issues: ['too_long'], length: 5001 }, description_preview: 'xxxx…' });
check('too_long desc => cell-error',   descLong.includes('wpsdtrk-cell-error'));
check('too_long tooltip has length',   descLong.includes('5001'));
check('desc preview rendered',         descLong.includes('wpsdtrk-feed-desc-text') && descLong.includes('xxxx…'));

const descOk = rowHtml({ id: 19, name: 'S', sku: 'S', price: '5', image: 'http://x', excluded: false,
    description_status: { ok: true, issues: [], length: 20 }, description_preview: 'A good description' });
check('ok desc => no cell-error',      !descOk.includes('wpsdtrk-cell-error'));
check('ok desc => no row tint',        !descOk.includes('wpsdtrk-has-error'));

// A missing Google category is NOT flagged in the product list — it is surfaced
// in the mapping panel instead (unmapped categories tinted there).
const gpcOnly = rowHtml({ id: 15, name: 'S', sku: 'S', price: '5', image: 'http://x', excluded: false, gpc_missing: true });
check('gpc missing => no field icon',  !gpcOnly.includes('wpsdtrk-field-icon'));
check('gpc missing => no red cell',    !gpcOnly.includes('wpsdtrk-cell-error'));
check('gpc missing => no row tint',    !gpcOnly.includes('wpsdtrk-has-error'));

const multi = rowHtml({ id: 16, name: 'S', price: '0', image: '', excluded: false,
    image_status: { ok: false, issues: ['no_image'] }, sku_missing: true, price_missing: true });
// sku_missing is ignored now: only the image and price problems flag cells.
check('two problems => 2 red cells',   (multi.match(/wpsdtrk-cell-error/g) || []).length === 2);

console.log('rowHtml() — XSS safety');
const xss = rowHtml({
    id: 9,
    name: '<script>alert(1)</script>',
    price: '1',
    image: 'x" onerror="alert(1)',
    excluded: false
});
check('script tag in name escaped',    !xss.includes('<script>') && xss.includes('&lt;script&gt;'));
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
