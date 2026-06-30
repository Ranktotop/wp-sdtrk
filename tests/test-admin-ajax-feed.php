<?php
/**
 * Standalone unit test for the product-feed management AJAX handlers
 * (Wp_Sdtrk_Admin_Ajax_Handler::list_feed_products / save_feed_exclusion).
 *
 * Run: php tests/test-admin-ajax-feed.php
 *
 * The handlers are private (dispatched via $_POST['func'] behind the nonce +
 * manage_options gate, covered by test-admin-ajax-auth.php); here we call them
 * directly via reflection to verify their data contract:
 *  - list_feed_products: forwards search/page/per_page to wc_get_products,
 *    translates the status filter into include/exclude, marks each row's
 *    excluded flag, and returns pagination + counter fields.
 *  - save_feed_exclusion: applies add/remove deltas to the exclusion option
 *    (idempotent), invalidates the cache, and returns refreshed counters.
 */

require_once __DIR__ . '/_bootstrap.php';

$GLOBALS['__opts']    = [];
$GLOBALS['__deleted'] = [];
$GLOBALS['__wc_args'] = null;

if (!function_exists('get_option'))    { function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['__opts']) ? $GLOBALS['__opts'][$k] : $d; } }
if (!function_exists('update_option')) { function update_option($k, $v, $a = null) { $GLOBALS['__opts'][$k] = $v; return true; } }
if (!function_exists('delete_option')) { function delete_option($k) { unset($GLOBALS['__opts'][$k]); $GLOBALS['__deleted'][] = $k; return true; } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); } }
// Mimic WooCommerce: HTML wrapper with &nbsp; + &euro; entities. The handler
// must strip the tags AND decode the entities, else they render literally.
if (!function_exists('wc_price'))      { function wc_price($amount) { return '<span class="amount"><bdi>' . number_format((float) $amount, 2) . '&nbsp;<span class="currency">&euro;</span></bdi></span>'; } }
if (!function_exists('wp_get_attachment_image_url')) { function wp_get_attachment_image_url($id, $size = 'thumbnail') { return $id ? 'http://shop/img/' . $id . '.jpg' : ''; } }
if (!function_exists('wp_count_posts')) { function wp_count_posts($type = 'post') { $o = new stdClass(); $o->publish = $GLOBALS['__publish_count'] ?? 0; return $o; } }

class FakeAjaxProduct
{
    public function __construct(private int $id, private string $name, private string $sku, private string $price) {}
    public function get_id() { return $this->id; }
    public function get_name() { return $this->name; }
    public function get_sku() { return $this->sku; }
    public function get_price() { return $this->price; }
    public function get_image_id() { return $this->id; }
}

// Paginate-shaped return; honours include/exclude so status filtering is real.
if (!function_exists('wc_get_products')) {
    function wc_get_products($args) {
        $GLOBALS['__wc_args'] = $args;
        $all = $GLOBALS['__catalog'];
        if (!empty($args['include'])) {
            $all = array_filter($all, static fn($p) => in_array($p->get_id(), (array) $args['include'], false));
        }
        if (!empty($args['exclude'])) {
            $all = array_filter($all, static fn($p) => !in_array($p->get_id(), (array) $args['exclude'], false));
        }
        $all = array_values($all);
        $per = (int) ($args['limit'] ?? 50);
        $page = (int) ($args['page'] ?? 1);
        $slice = array_slice($all, ($page - 1) * $per, $per);
        $o = new stdClass();
        $o->products = $slice;
        $o->total = count($all);
        $o->max_num_pages = (int) max(1, ceil(count($all) / max(1, $per)));
        return $o;
    }
}

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-feed.php';
require_once dirname(__DIR__) . '/admin/class-wp-sdtrk-admin-ajax.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$handler = new Wp_Sdtrk_Admin_Ajax_Handler();
$ref = new ReflectionClass($handler);
function call_priv($handler, $ref, $name, $data) {
    $m = $ref->getMethod($name);
    $m->setAccessible(true);
    return $m->invoke($handler, $data, []);
}

$GLOBALS['__catalog'] = [
    new FakeAjaxProduct(1, 'Alpha', 'SKU-1', '10.00'),
    new FakeAjaxProduct(2, 'Beta',  'SKU-2', '20.00'),
    new FakeAjaxProduct(3, 'Gamma', 'SKU-3', '30.00'),
    new FakeAjaxProduct(4, 'Delta', 'SKU-4', '40.00'),
];
$GLOBALS['__publish_count'] = 4;

echo "list_feed_products() — base + excluded flag\n";
$GLOBALS['__opts'][Wp_Sdtrk_WC_Feed::EXCLUDED_OPTION] = [2];
$r = call_priv($handler, $ref, 'list_feed_products', ['page' => 1, 'per_page' => 50, 'status' => 'all']);
check('state true',                    ($r['state'] ?? null) === true);
check('returns all 4 rows',            count($r['rows']) === 4);
$byId = [];
foreach ($r['rows'] as $row) { $byId[$row['id']] = $row; }
check('row carries name/sku/price',    $byId[1]['name'] === 'Alpha' && $byId[1]['sku'] === 'SKU-1' && isset($byId[1]['price']));
check('price tags stripped',           strpos($byId[1]['price'], '<') === false);
check('price entities decoded',        strpos($byId[1]['price'], '&euro;') === false && strpos($byId[1]['price'], '&nbsp;') === false);
check('price shows currency symbol',   strpos($byId[1]['price'], "\xE2\x82\xAC") !== false); // €
check('row 2 flagged excluded',        $byId[2]['excluded'] === true);
check('row 1 flagged included',        $byId[1]['excluded'] === false);
check('counter: totalProducts = 4',    (int) $r['totalProducts'] === 4);
check('counter: excludedCount = 1',    (int) $r['excludedCount'] === 1);

echo "list_feed_products() — search + pagination forwarded\n";
$r = call_priv($handler, $ref, 'list_feed_products', ['search' => 'alph', 'page' => 2, 'per_page' => 2, 'status' => 'all']);
check('search forwarded as s',         ($GLOBALS['__wc_args']['s'] ?? null) === 'alph');
check('page forwarded',                (int) $GLOBALS['__wc_args']['page'] === 2);
check('per_page forwarded as limit',   (int) $GLOBALS['__wc_args']['limit'] === 2);
check('status=publish enforced',       ($GLOBALS['__wc_args']['status'] ?? null) === 'publish');

echo "list_feed_products() — status filter => excluded only\n";
$GLOBALS['__opts'][Wp_Sdtrk_WC_Feed::EXCLUDED_OPTION] = [2, 4];
$r = call_priv($handler, $ref, 'list_feed_products', ['status' => 'excluded', 'per_page' => 50]);
$ids = array_map(static fn($row) => $row['id'], $r['rows']);
sort($ids);
check('excluded filter => only 2 & 4', $ids === [2, 4]);

echo "list_feed_products() — status filter => in_feed only\n";
$r = call_priv($handler, $ref, 'list_feed_products', ['status' => 'in_feed', 'per_page' => 50]);
$ids = array_map(static fn($row) => $row['id'], $r['rows']);
sort($ids);
check('in_feed filter => only 1 & 3',  $ids === [1, 3]);

echo "save_feed_exclusion() — apply add/remove deltas\n";
$GLOBALS['__opts'][Wp_Sdtrk_WC_Feed::EXCLUDED_OPTION] = [2];
$GLOBALS['__deleted'] = [];
$r = call_priv($handler, $ref, 'save_feed_exclusion', ['changes' => [
    ['id' => 3, 'excluded' => true],   // add
    ['id' => 2, 'excluded' => false],  // remove
]]);
$saved = $GLOBALS['__opts'][Wp_Sdtrk_WC_Feed::EXCLUDED_OPTION];
sort($saved);
check('state true',                    ($r['state'] ?? null) === true);
check('delta applied (2 out, 3 in)',   $saved === [3]);
check('cache invalidated',             in_array(Wp_Sdtrk_WC_Feed::CACHE_OPTION, $GLOBALS['__deleted'], true));
check('excludedCount refreshed',       (int) $r['excludedCount'] === 1);
check('totalProducts refreshed',       (int) $r['totalProducts'] === 4);

echo "save_feed_exclusion() — string booleans from \$_POST\n";
$GLOBALS['__opts'][Wp_Sdtrk_WC_Feed::EXCLUDED_OPTION] = [];
$r = call_priv($handler, $ref, 'save_feed_exclusion', ['changes' => [
    ['id' => '1', 'excluded' => 'true'],
    ['id' => '4', 'excluded' => 'false'],
]]);
$saved = $GLOBALS['__opts'][Wp_Sdtrk_WC_Feed::EXCLUDED_OPTION];
check('string "true" excludes',        in_array(1, $saved, true));
check('string "false" does not',       !in_array(4, $saved, true));

echo "save_feed_exclusion() — idempotent + ignores junk\n";
$GLOBALS['__opts'][Wp_Sdtrk_WC_Feed::EXCLUDED_OPTION] = [5];
$r = call_priv($handler, $ref, 'save_feed_exclusion', ['changes' => [
    ['id' => 5, 'excluded' => true],   // already excluded
    ['id' => 0, 'excluded' => true],   // junk id
    ['excluded' => true],              // missing id
    'not-an-array',
]]);
$saved = $GLOBALS['__opts'][Wp_Sdtrk_WC_Feed::EXCLUDED_OPTION];
check('idempotent + junk ignored',     $saved === [5]);

echo "save_feed_exclusion() — no changes is a no-op success\n";
$GLOBALS['__opts'][Wp_Sdtrk_WC_Feed::EXCLUDED_OPTION] = [7];
$r = call_priv($handler, $ref, 'save_feed_exclusion', []);
check('empty changes => state true',   ($r['state'] ?? null) === true);
check('list unchanged',                $GLOBALS['__opts'][Wp_Sdtrk_WC_Feed::EXCLUDED_OPTION] === [7]);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
