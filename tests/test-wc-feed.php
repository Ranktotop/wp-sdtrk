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

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
