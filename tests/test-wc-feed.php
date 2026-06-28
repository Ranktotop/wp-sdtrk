<?php
/**
 * Standalone unit test for the product-feed generator (pure parts).
 *
 * Run: php tests/test-wc-feed.php
 *
 * Covers feed_items() (raw product rows -> normalized feed items) and
 * render_xml() (items -> RSS 2.0 / g: namespace XML), which are the
 * WordPress-independent core of Wp_Sdtrk_WC_Feed.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-feed.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$feed = new Wp_Sdtrk_WC_Feed();

echo "feed_items() normalization\n";
$rows = [
    [
        'id' => 10, 'sku' => 'SKU-10', 'title' => 'Tea & Coffee',
        'description' => '<p>Great <b>stuff</b></p>', 'link' => 'http://shop/p/10',
        'image' => 'http://shop/img/10.jpg', 'in_stock' => true,
        'price' => '19.99', 'currency' => 'EUR', 'brand' => 'Acme', 'group_id' => '',
    ],
    [
        'id' => 11, 'sku' => '', 'title' => 'Variant S',
        'description' => 'plain', 'link' => 'http://shop/p/11',
        'image' => 'http://shop/img/11.jpg', 'in_stock' => false,
        'price' => '5', 'currency' => 'USD', 'brand' => 'Acme', 'group_id' => '10',
    ],
];
$items = $feed->feed_items($rows);

check('two items',                       count($items) === 2);
check('id falls back to SKU',            $items[0]['id'] === 'SKU-10');
check('id falls back to product id',     $items[1]['id'] === '11');
check('description tags stripped',       $items[0]['description'] === 'Great stuff');
check('availability in_stock',           $items[0]['availability'] === 'in_stock');
check('availability out_of_stock',       $items[1]['availability'] === 'out_of_stock');
check('price = "amount currency"',       $items[0]['price'] === '19.99 EUR');
check('condition defaults to new',       $items[0]['condition'] === 'new');
check('no item_group_id for simple',     !isset($items[0]['item_group_id']));
check('item_group_id for variation',     ($items[1]['item_group_id'] ?? null) === '10');

echo "feed_items() omits empty optional fields\n";
$bare = $feed->feed_items([
    [
        'id' => 12, 'sku' => 'SKU-12', 'title' => 'No price/image',
        'description' => '', 'link' => 'http://shop/p/12',
        'image' => '', 'in_stock' => true,
        'price' => '', 'currency' => 'EUR', 'brand' => '', 'group_id' => '',
    ],
]);
check('no price key when amount empty',   !isset($bare[0]['price']));
check('no image key when image empty',    !isset($bare[0]['image']));

echo "render_xml() output\n";
$xml = $feed->render_xml($items, ['title' => 'My Shop', 'link' => 'http://shop', 'description' => 'Feed']);

check('xml declaration',                 strpos($xml, '<?xml version="1.0" encoding="UTF-8"?>') === 0);
check('g: namespace declared',           strpos($xml, 'xmlns:g="http://base.google.com/ns/1.0"') !== false);
check('rss 2.0',                         strpos($xml, '<rss version="2.0"') !== false);
check('channel title',                   strpos($xml, '<title>My Shop</title>') !== false);
check('g:id present',                     strpos($xml, '<g:id>SKU-10</g:id>') !== false);
check('g:price present',                  strpos($xml, '<g:price>19.99 EUR</g:price>') !== false);
check('g:availability present',           strpos($xml, '<g:availability>out_of_stock</g:availability>') !== false);
check('g:item_group_id present',          strpos($xml, '<g:item_group_id>10</g:item_group_id>') !== false);
check('ampersand escaped in title',       strpos($xml, 'Tea &amp; Coffee') !== false);
check('two <item> blocks',                substr_count($xml, '<item>') === 2);

$bareXml = $feed->render_xml($bare);
check('no <g:price> when amount empty',   strpos($bareXml, '<g:price>') === false);
check('no <g:image_link> when img empty', strpos($bareXml, '<g:image_link>') === false);

echo "render_xml() is well-formed XML\n";
// Parse via DOMDocument so a regression that breaks well-formedness fails loudly.
$wellFormed = function (string $doc): bool {
    $prev = libxml_use_internal_errors(true);
    $dom  = new DOMDocument();
    $ok   = $dom->loadXML($doc);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    return $ok !== false;
};
check('populated feed parses as XML',     $wellFormed($xml));
check('bare feed parses as XML',          $wellFormed($bareXml));

echo "render_xml() empty catalog\n";
$emptyXml = $feed->render_xml([]);
check('empty catalog is well-formed',     $wellFormed($emptyXml));
check('empty catalog has zero <item>',    substr_count($emptyXml, '<item>') === 0);
check('empty catalog keeps <channel>',    strpos($emptyXml, '<channel>') !== false);

echo "esc() escapes special chars across all fields\n";
$special = $feed->feed_items([
    [
        'id' => 'a<b>&"\'', 'sku' => 'a<b>&"\'', 'title' => 'A < B > "C" & \'D\'',
        'description' => 'x & y < z', 'link' => 'http://shop/?a=1&b=2',
        'image' => 'http://shop/img.jpg?x=1&y=2', 'in_stock' => true,
        'price' => '9.99', 'currency' => 'EUR', 'brand' => 'Tea & Co', 'group_id' => '',
    ],
]);
$specialXml = $feed->render_xml($special);
check('special-char feed is well-formed', $wellFormed($specialXml));
check('< escaped everywhere',             strpos($specialXml, '<b>') === false);
check('& escaped in link query',          strpos($specialXml, 'a=1&b=2') === false);
check('&amp; present',                    strpos($specialXml, '&amp;') !== false);
check('&lt; present',                     strpos($specialXml, '&lt;') !== false);
check('&gt; present',                     strpos($specialXml, '&gt;') !== false);
check('&quot; present',                   strpos($specialXml, '&quot;') !== false);

echo "esc() strips XML-illegal control chars and survives bad UTF-8\n";
$dirty = $feed->feed_items([
    [
        'id' => "SKU\x0013", 'sku' => "SKU\x0013", 'title' => "A\x00B\x0BC\x1FD",
        'description' => "tab\tkept\nnewline\rkept", 'link' => 'http://shop/p/13',
        'image' => '', 'in_stock' => true,
        'price' => '', 'currency' => 'EUR', 'brand' => "Bad\xC3\x28UTF8", 'group_id' => '',
    ],
]);
$dirtyXml = $feed->render_xml($dirty);
check('control chars stripped (title)',   strpos($dirtyXml, '<title>ABCD</title>') !== false);
check('NUL stripped from g:id',           strpos($dirtyXml, '<g:id>SKU13</g:id>') !== false);
check('tab/newline/CR preserved',         strpos($dirtyXml, "tab\tkept") !== false);
check('dirty feed is well-formed',        $wellFormed($dirtyXml));
check('bad UTF-8 did not drop the field', strpos($dirtyXml, '<g:brand>') !== false);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
