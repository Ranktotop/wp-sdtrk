<?php
/**
 * Standalone unit test for Wp_Sdtrk_WC_Feed::collect() exclusion handling.
 *
 * Run: php tests/test-wc-feed-collect.php
 *
 * collect() must pass the exclusion list (get_excluded_ids()) to
 * wc_get_products() as the 'exclude' arg, so excluded products never reach the
 * raw rows — and therefore never reach the generated feed XML. Excluding a
 * variable parent drops its variations too (they are only gathered via the
 * parent's get_children() loop).
 */

require_once __DIR__ . '/_bootstrap.php';

$GLOBALS['__opts']       = [];
$GLOBALS['__wc_args']    = null; // captured wc_get_products() args

if (!function_exists('get_option')) {
    function get_option($k, $default = false) { return array_key_exists($k, $GLOBALS['__opts']) ? $GLOBALS['__opts'][$k] : $default; }
}
if (!function_exists('update_option')) {
    function update_option($k, $v, $autoload = null) { $GLOBALS['__opts'][$k] = $v; return true; }
}
if (!function_exists('delete_option')) {
    function delete_option($k) { unset($GLOBALS['__opts'][$k]); return true; }
}
if (!class_exists('WP_SDTRK_Helper_Options')) {
    class WP_SDTRK_Helper_Options
    {
        public static function get_string_option($k) { return ''; }
    }
}
if (!function_exists('get_bloginfo'))         { function get_bloginfo($k = '') { return 'StubSite'; } }
if (!function_exists('get_site_url'))         { function get_site_url() { return 'http://shop'; } }
if (!function_exists('get_woocommerce_currency')) { function get_woocommerce_currency() { return 'EUR'; } }
if (!function_exists('get_permalink'))        { function get_permalink($id) { return 'http://shop/p/' . $id; } }
if (!function_exists('wp_get_attachment_url')) { function wp_get_attachment_url($id) { return 'http://shop/img/' . $id . '.jpg'; } }

// ---- Fake WooCommerce product (duck-typed) ----
class FakeFeedProduct
{
    public function __construct(
        private int $id,
        private string $name,
        private string $sku = '',
        private string $price = '9.99',
        private bool $variable = false,
        private array $children = []
    ) {}
    public function get_id() { return $this->id; }
    public function get_name() { return $this->name; }
    public function get_sku() { return $this->sku; }
    public function get_price() { return $this->price; }
    public function get_short_description() { return 'short ' . $this->name; }
    public function get_description() { return 'long ' . $this->name; }
    public function get_image_id() { return $this->id; }
    public function is_in_stock() { return true; }
    public function is_type($t) { return $t === 'variable' ? $this->variable : false; }
    public function get_children() { return $this->children; }
}

// Whole catalog, keyed by id, resolved by wc_get_product().
$GLOBALS['__catalog'] = [
    1 => new FakeFeedProduct(1, 'Simple A', 'SKU-1'),
    2 => new FakeFeedProduct(2, 'Simple B', 'SKU-2'),
    3 => new FakeFeedProduct(3, 'Variable C', 'SKU-3', '0', true, [31, 32]),
    31 => new FakeFeedProduct(31, 'Variation C-1', 'SKU-3-1', '11.00'),
    32 => new FakeFeedProduct(32, 'Variation C-2', 'SKU-3-2', '12.00'),
];

if (!function_exists('wc_get_product')) {
    function wc_get_product($id) { return $GLOBALS['__catalog'][$id] ?? null; }
}
// Top-level products only (parents + simples). Honour the 'exclude' arg the
// same way WooCommerce does, so the test exercises real filtering end-to-end.
if (!function_exists('wc_get_products')) {
    function wc_get_products($args) {
        $GLOBALS['__wc_args'] = $args;
        $exclude = isset($args['exclude']) ? (array) $args['exclude'] : [];
        $out = [];
        foreach ([1, 2, 3] as $id) {
            if (in_array($id, $exclude, false)) { continue; }
            $out[] = $GLOBALS['__catalog'][$id];
        }
        return $out;
    }
}

require_once dirname(__DIR__) . '/public/class-wp-sdtrk-wc-feed.php';

$fails = 0;
function check($label, $cond) {
    global $fails;
    if ($cond) { echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; $fails++; }
}

$feed = new Wp_Sdtrk_WC_Feed();

echo "collect() passes the exclusion list to wc_get_products()\n";
$GLOBALS['__opts'][Wp_Sdtrk_WC_Feed::EXCLUDED_OPTION] = [2];
$rows = $feed->collect();
check('exclude arg forwarded',          ($GLOBALS['__wc_args']['exclude'] ?? null) === [2]);
$ids = array_map(static fn($r) => (int) $r['id'], $rows);
check('excluded simple absent',         !in_array(2, $ids, true));
check('included simple present',        in_array(1, $ids, true));

echo "collect() with no exclusions includes everything\n";
$GLOBALS['__opts'] = [];
$rows = $feed->collect();
$ids  = array_map(static fn($r) => (int) $r['id'], $rows);
check('empty exclude arg',              ($GLOBALS['__wc_args']['exclude'] ?? null) === []);
check('simple A present',               in_array(1, $ids, true));
check('simple B present',               in_array(2, $ids, true));
check('both variations present',        in_array(31, $ids, true) && in_array(32, $ids, true));

echo "excluding a variable parent drops its variations\n";
$GLOBALS['__opts'][Wp_Sdtrk_WC_Feed::EXCLUDED_OPTION] = [3];
$rows = $feed->collect();
$ids  = array_map(static fn($r) => (int) $r['id'], $rows);
check('parent 3 absent',                !in_array(3, $ids, true));
check('variation 31 absent',            !in_array(31, $ids, true));
check('variation 32 absent',            !in_array(32, $ids, true));
check('unrelated simple still present', in_array(1, $ids, true));

echo "generate() XML omits the excluded product\n";
$GLOBALS['__opts'][Wp_Sdtrk_WC_Feed::EXCLUDED_OPTION] = [2];
$xml = $feed->generate();
check('excluded SKU not in XML',        strpos($xml, '<g:id>SKU-2</g:id>') === false);
check('included SKU in XML',            strpos($xml, '<g:id>SKU-1</g:id>') !== false);

if ($fails > 0) {
    echo "\n$fails assertion(s) failed.\n";
    exit(1);
}
echo "\nAll assertions passed.\n";
exit(0);
