<?php

/**
 * WooCommerce product feed (RSS 2.0 with the Google `g:` namespace).
 *
 * Readable by Google Merchant Center and Meta Commerce Manager. Available only
 * when the WooCommerce integration is active and the wc_feed_enabled switch is
 * on. Served from a token-protected query-var endpoint and refreshed daily by
 * WP-Cron (cached output).
 *
 * Spec: spec/07-woocommerce/product-feed.md
 */
class Wp_Sdtrk_WC_Feed
{
    public const QUERY_VAR     = 'wp_sdtrk_feed';
    public const TOKEN_OPTION  = 'wp_sdtrk_feed_token';
    public const CACHE_OPTION  = 'wp_sdtrk_feed_cache';
    public const EXCLUDED_OPTION = 'wp_sdtrk_feed_excluded';
    public const GPC_MAP_OPTION = 'wp_sdtrk_feed_gpc_map';
    public const CRON_HOOK     = 'wp_sdtrk_cron_generate_feed';
    public const LOCK_TRANSIENT = 'wp_sdtrk_feed_lock';
    public const LOCK_TTL       = 300; // seconds — short-lived stampede guard

    // Facebook/Meta catalog image constraints. Images below the minimum edge or
    // above the size cap are rejected by Commerce Manager; we surface both in the
    // Manage Feed page so the shop owner sees the problem before Meta does.
    public const IMAGE_MIN_DIMENSION = 500;          // px, min width AND height
    public const IMAGE_MAX_BYTES     = 8388608;      // 8 * 1024 * 1024

    // Google/Meta accept a product description up to 5000 characters; an empty
    // one is a missing required field. Surfaced in the Manage Feed page.
    public const DESCRIPTION_MAX_LENGTH = 5000;

    /* ---------------------------------------------------------------------
     * Pure core (no WordPress/WooCommerce dependencies) — unit-tested
     * ------------------------------------------------------------------- */

    /**
     * Normalise raw product rows into feed items.
     *
     * @param array<int, array> $rows Raw rows from collect().
     * @return array<int, array>
     */
    public function feed_items(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $sku   = isset($row['sku']) ? trim((string) $row['sku']) : '';
            $price = isset($row['price']) ? trim((string) $row['price']) : '';

            $item = [
                'id'           => $sku !== '' ? $sku : (string) ($row['id'] ?? ''),
                'title'        => (string) ($row['title'] ?? ''),
                'description'  => trim(strip_tags((string) ($row['description'] ?? ''))),
                'link'         => (string) ($row['link'] ?? ''),
                'availability' => !empty($row['in_stock']) ? 'in_stock' : 'out_of_stock',
                'condition'    => 'new',
                'brand'        => (string) ($row['brand'] ?? ''),
            ];

            // Optional g: fields are omitted entirely when empty, so a product
            // without a price/image yields an absent element rather than a
            // malformed one (e.g. "<g:price>EUR</g:price>").
            $image = isset($row['image']) ? trim((string) $row['image']) : '';
            if ($image !== '') {
                $item['image'] = $image;
            }
            if ($price !== '') {
                $item['price'] = trim($price . ' ' . (string) ($row['currency'] ?? ''));
            }

            $gpc = isset($row['google_product_category']) ? trim((string) $row['google_product_category']) : '';
            if ($gpc !== '') {
                $item['google_product_category'] = $gpc;
            }

            $group = isset($row['group_id']) ? trim((string) $row['group_id']) : '';
            if ($group !== '') {
                $item['item_group_id'] = $group;
            }

            $items[] = $item;
        }
        return $items;
    }

    /**
     * Render feed items as an RSS 2.0 / g: XML document.
     *
     * @param array<int, array> $items
     * @param array{title?:string, link?:string, description?:string} $channel
     * @return string
     */
    public function render_xml(array $items, array $channel = []): string
    {
        $title = $channel['title'] ?? 'Product Feed';
        $link  = $channel['link'] ?? '';
        $desc  = $channel['description'] ?? '';

        $out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
        $out .= "<channel>\n";
        $out .= '<title>' . $this->esc($title) . "</title>\n";
        $out .= '<link>' . $this->esc($link) . "</link>\n";
        $out .= '<description>' . $this->esc($desc) . "</description>\n";

        foreach ($items as $item) {
            $out .= "<item>\n";
            $out .= '<g:id>' . $this->esc($item['id'] ?? '') . "</g:id>\n";
            $out .= '<title>' . $this->esc($item['title'] ?? '') . "</title>\n";
            $out .= '<description>' . $this->esc($item['description'] ?? '') . "</description>\n";
            $out .= '<link>' . $this->esc($item['link'] ?? '') . "</link>\n";
            if (!empty($item['image'])) {
                $out .= '<g:image_link>' . $this->esc($item['image']) . "</g:image_link>\n";
            }
            $out .= '<g:availability>' . $this->esc($item['availability'] ?? '') . "</g:availability>\n";
            if (!empty($item['price'])) {
                $out .= '<g:price>' . $this->esc($item['price']) . "</g:price>\n";
            }
            $out .= '<g:condition>' . $this->esc($item['condition'] ?? 'new') . "</g:condition>\n";
            if (!empty($item['brand'])) {
                $out .= '<g:brand>' . $this->esc($item['brand']) . "</g:brand>\n";
            }
            if (!empty($item['google_product_category'])) {
                $out .= '<g:google_product_category>' . $this->esc($item['google_product_category']) . "</g:google_product_category>\n";
            }
            if (!empty($item['item_group_id'])) {
                $out .= '<g:item_group_id>' . $this->esc($item['item_group_id']) . "</g:item_group_id>\n";
            }
            $out .= "</item>\n";
        }

        $out .= "</channel>\n</rss>\n";
        return $out;
    }

    /**
     * XML-escape a scalar value.
     *
     * @param mixed $value
     * @return string
     */
    private function esc($value): string
    {
        // Strip bytes illegal in XML 1.0 (C0 control chars except tab/LF/CR).
        // They are all < 0x80, so a byte-wise strip (no /u) never corrupts a
        // multi-byte UTF-8 sequence; leaving them in would make the feed
        // non-well-formed and get it rejected by Merchant Center / Meta.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value);

        // ENT_SUBSTITUTE: replace any invalid UTF-8 with U+FFFD instead of
        // letting htmlspecialchars() return '' and silently drop the field.
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /* ---------------------------------------------------------------------
     * Image quality evaluation — pure core (no WordPress dependency)
     * ------------------------------------------------------------------- */

    /**
     * Evaluate a product image against the Facebook/Meta catalog constraints.
     *
     * Pure and side-effect free: the caller supplies the already-gathered
     * dimensions/size (see image_health() for the WP wrapper). Issue codes are
     * stable identifiers the UI maps to translated messages.
     *
     * @param int  $width     Image width in px.
     * @param int  $height    Image height in px.
     * @param int  $bytes     File size in bytes (0 when unknown).
     * @param bool $has_image Whether the product has a featured image at all.
     * @return array{ok:bool, issues:string[]} issues ⊆ {no_image, too_small, too_large}
     */
    public function evaluate_image(int $width, int $height, int $bytes, bool $has_image): array
    {
        $issues = [];
        if (!$has_image) {
            return ['ok' => false, 'issues' => ['no_image']];
        }
        // Dimensions can be 0 when metadata is missing; treat a known-too-small
        // edge as a problem, but don't flag an unknown (0) dimension as too_small.
        if (($width > 0 && $width < self::IMAGE_MIN_DIMENSION)
            || ($height > 0 && $height < self::IMAGE_MIN_DIMENSION)) {
            $issues[] = 'too_small';
        }
        if ($bytes > self::IMAGE_MAX_BYTES) {
            $issues[] = 'too_large';
        }
        return ['ok' => empty($issues), 'issues' => $issues];
    }

    /**
     * Evaluate a product's (plain-text) feed description.
     *
     * Pure and side-effect free. The caller passes the already-resolved plain
     * text (short description, else long description, tags stripped) — the same
     * value the feed emits as <description>. Issue codes are stable identifiers
     * the UI maps to translated messages.
     *
     * @param string $description Plain-text description.
     * @return array{ok:bool, issues:string[], length:int} issues ⊆ {no_description, too_long}
     */
    public function evaluate_description(string $description): array
    {
        $length = function_exists('mb_strlen') ? mb_strlen($description) : strlen($description);
        $issues = [];
        if ($description === '') {
            $issues[] = 'no_description';
        } elseif ($length > self::DESCRIPTION_MAX_LENGTH) {
            $issues[] = 'too_long';
        }
        return ['ok' => empty($issues), 'issues' => $issues, 'length' => $length];
    }

    /**
     * Gather an attachment's dimensions/size and evaluate it. Read-only and
     * cheap: width/height come from the stored attachment metadata (a DB row,
     * no file I/O); the file size is taken from metadata when present (WP 6.0+)
     * and only stat()'d as a fallback. Results are memoised per request so the
     * same image shared across rows is measured once.
     *
     * @param int $attachment_id Featured image id, 0 when the product has none.
     * @return array{ok:bool, issues:string[], width:int, height:int, size:int}
     */
    public function image_health(int $attachment_id): array
    {
        static $cache = [];
        if (isset($cache[$attachment_id])) {
            return $cache[$attachment_id];
        }

        if ($attachment_id <= 0) {
            return $cache[$attachment_id] = [
                'ok' => false, 'issues' => ['no_image'], 'width' => 0, 'height' => 0, 'size' => 0,
            ];
        }

        $meta   = wp_get_attachment_metadata($attachment_id);
        $width  = is_array($meta) && isset($meta['width'])  ? (int) $meta['width']  : 0;
        $height = is_array($meta) && isset($meta['height']) ? (int) $meta['height'] : 0;

        // filesize is stored in metadata since WP 6.0; fall back to a single
        // stat() on the original file only when it's absent.
        $size = is_array($meta) && isset($meta['filesize']) ? (int) $meta['filesize'] : 0;
        if ($size <= 0) {
            $path = get_attached_file($attachment_id);
            if ($path && function_exists('wp_filesize')) {
                $size = (int) wp_filesize($path);
            } elseif ($path && @is_file($path)) {
                $size = (int) @filesize($path);
            }
        }

        $result = $this->evaluate_image($width, $height, $size, true);
        $result['width']  = $width;
        $result['height'] = $height;
        $result['size']   = $size;
        return $cache[$attachment_id] = $result;
    }

    /* ---------------------------------------------------------------------
     * Exclusion list — which published products are kept out of the feed
     * ------------------------------------------------------------------- */

    /**
     * The product IDs excluded from the feed.
     *
     * Stored in the standalone wp_sdtrk_feed_excluded option (not Redux). The
     * list holds exclusions only — anything not listed is included, so newly
     * published products are in the feed by default. Tolerates a missing or
     * corrupt (non-array) stored value by returning [].
     *
     * @return int[] Unique, positive product IDs.
     */
    public function get_excluded_ids(): array
    {
        $raw = get_option(self::EXCLUDED_OPTION, []);
        if (!is_array($raw)) {
            return [];
        }
        $ids = array_filter(array_map('intval', $raw), static function ($id) {
            return $id > 0;
        });
        return array_values(array_unique($ids));
    }

    /**
     * Persist the exclusion list and invalidate the cached feed.
     *
     * Sanitizes the input to a unique, positive int list. Deleting the cache
     * forces a cold rebuild on the next feed request (under the existing
     * stampede lock), so the change is reflected without waiting for the cron.
     *
     * @param int[] $ids
     * @return void
     */
    public function set_excluded_ids(array $ids): void
    {
        $clean = array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        });
        $clean = array_values(array_unique($clean));
        update_option(self::EXCLUDED_OPTION, $clean, false);
        delete_option(self::CACHE_OPTION);
    }

    /**
     * Whether a product ID is excluded from the feed.
     *
     * @param int $id
     * @return bool
     */
    public function is_excluded(int $id): bool
    {
        return in_array($id, $this->get_excluded_ids(), true);
    }

    /* ---------------------------------------------------------------------
     * Google product category mapping — WooCommerce category → Google taxonomy
     * ------------------------------------------------------------------- */

    /**
     * The WooCommerce-category → Google-product-category map.
     *
     * Stored in the standalone wp_sdtrk_feed_gpc_map option (not Redux) as
     * { term_id (int) => google category (string) }. Google needs
     * g:google_product_category (Meta ignores it); WooCommerce has no native
     * field for it, so the shop owner maps each product category once and every
     * product in it inherits the value. Tolerates a missing/corrupt value.
     *
     * @return array<int, string> term_id => non-empty Google category string.
     */
    public function get_gpc_map(): array
    {
        $raw = get_option(self::GPC_MAP_OPTION, []);
        if (!is_array($raw)) {
            return [];
        }
        $map = [];
        foreach ($raw as $term_id => $category) {
            $term_id  = (int) $term_id;
            $category = trim((string) $category);
            if ($term_id > 0 && $category !== '') {
                $map[$term_id] = $category;
            }
        }
        return $map;
    }

    /**
     * Persist the category map and invalidate the cached feed, so a mapping
     * change is reflected on the next feed request (cold rebuild under the
     * existing stampede lock) without waiting for the cron. Empty values remove
     * the mapping for that term.
     *
     * @param array<int|string, string> $map term_id => Google category string.
     * @return void
     */
    public function set_gpc_map(array $map): void
    {
        $clean = [];
        foreach ($map as $term_id => $category) {
            $term_id  = (int) $term_id;
            $category = trim((string) $category);
            if ($term_id > 0 && $category !== '') {
                $clean[$term_id] = $category;
            }
        }
        update_option(self::GPC_MAP_OPTION, $clean, false);
        delete_option(self::CACHE_OPTION);
    }

    /**
     * Resolve a product's Google product category from the map.
     *
     * Checks each assigned product_cat term and, if unmapped, walks up its
     * ancestors — so mapping a parent category covers its sub-categories. The
     * first match wins; returns '' when nothing maps (the feed then omits the
     * element and the admin flags it).
     *
     * @param WC_Product $product
     * @return string
     */
    public function resolve_gpc($product): string
    {
        $map = $this->get_gpc_map();
        if (empty($map) || !method_exists($product, 'get_category_ids')) {
            return '';
        }
        foreach ($product->get_category_ids() as $term_id) {
            $term_id = (int) $term_id;
            if (isset($map[$term_id])) {
                return $map[$term_id];
            }
            foreach (get_ancestors($term_id, 'product_cat') as $ancestor_id) {
                if (isset($map[(int) $ancestor_id])) {
                    return $map[(int) $ancestor_id];
                }
            }
        }
        return '';
    }

    /* ---------------------------------------------------------------------
     * WooCommerce data collection
     * ------------------------------------------------------------------- */

    /**
     * Collect raw product rows from WooCommerce (published products + variations).
     *
     * @return array<int, array>
     */
    public function collect(): array
    {
        if (!function_exists('wc_get_products')) {
            return [];
        }
        $brand    = WP_SDTRK_Helper_Options::get_string_option('brandname');
        $brand    = $brand ? $brand : get_bloginfo('name');
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '';
        // When off, variable products are listed as a single parent row instead
        // of one row per variation.
        $include_variants = WP_SDTRK_Helper_Options::get_bool_option('wc_feed_include_variants', true);

        // Exclude products the admin has kept out of the feed. A variable
        // parent's variations are only gathered via its get_children() loop
        // below, so excluding the parent drops its variations transitively.
        $products = wc_get_products([
            'status'  => 'publish',
            'limit'   => -1,
            'exclude' => $this->get_excluded_ids(),
        ]);
        $rows     = [];
        foreach ($products as $product) {
            // Resolve the Google category once per parent; variations have no
            // categories of their own and inherit the parent's value.
            $gpc = $this->resolve_gpc($product);
            if ($product->is_type('variable') && $include_variants) {
                foreach ($product->get_children() as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        $rows[] = $this->product_row($variation, $brand, $currency, (string) $product->get_id(), $gpc);
                    }
                }
                continue;
            }
            $rows[] = $this->product_row($product, $brand, $currency, '', $gpc);
        }
        return $rows;
    }

    /**
     * Build a raw row from a WC product/variation.
     *
     * @param WC_Product $product
     * @param string $brand
     * @param string $currency
     * @param string $group_id Parent id for variations, '' for simple products.
     * @param string $gpc Resolved Google product category ('' when unmapped).
     * @return array
     */
    private function product_row($product, string $brand, string $currency, string $group_id, string $gpc = ''): array
    {
        $description = $product->get_short_description();
        if ($description === '') {
            $description = $product->get_description();
        }
        return [
            'id'          => $product->get_id(),
            'sku'         => $product->get_sku(),
            'title'       => $product->get_name(),
            'description' => $description,
            'link'        => get_permalink($product->get_id()),
            'image'       => (string) wp_get_attachment_url($product->get_image_id()),
            'in_stock'    => $product->is_in_stock(),
            'price'       => $product->get_price(),
            'currency'    => $currency,
            'brand'       => $brand,
            'group_id'    => $group_id,
            'google_product_category' => $gpc,
        ];
    }

    /**
     * Generate the full feed XML live.
     *
     * @return string
     */
    public function generate(): string
    {
        $channel = [
            'title'       => get_bloginfo('name') . ' — Product Feed',
            'link'        => rtrim(get_site_url(), '/') . '/',
            'description' => get_bloginfo('description'),
        ];
        return $this->render_xml($this->feed_items($this->collect()), $channel);
    }

    /* ---------------------------------------------------------------------
     * Token, cache, endpoint, cron
     * ------------------------------------------------------------------- */

    /**
     * Get the feed token, generating + persisting one on first use.
     *
     * @return string
     */
    public function get_token(): string
    {
        $token = get_option(self::TOKEN_OPTION, '');
        if (!is_string($token) || $token === '') {
            $token = wp_generate_password(32, false);
            update_option(self::TOKEN_OPTION, $token, false);
        }
        return $token;
    }

    /**
     * Mint a fresh token, persist it, and return it. Invalidates the old
     * feed URL — used by the admin "regenerate token" action.
     *
     * @return string
     */
    public function rotate_token(): string
    {
        $token = wp_generate_password(32, false);
        update_option(self::TOKEN_OPTION, $token, false);
        return $token;
    }

    /**
     * Constant-time token check.
     *
     * @param string $token
     * @return bool
     */
    public function verify_token(string $token): bool
    {
        return $token !== '' && hash_equals($this->get_token(), $token);
    }

    /**
     * The public feed URL (incl. token).
     *
     * @return string
     */
    public function get_feed_url(): string
    {
        return add_query_arg(
            [self::QUERY_VAR => '1', 'token' => $this->get_token()],
            rtrim(get_site_url(), '/') . '/'
        );
    }

    /**
     * Whether the feed is enabled (integration active + switch on).
     *
     * @return bool
     */
    public static function is_enabled(): bool
    {
        return Wp_Sdtrk_WC_Integration::is_active()
            && WP_SDTRK_Helper_Options::get_bool_option('wc_feed_enabled', false);
    }

    /**
     * Regenerate and cache the feed XML.
     *
     * @return void
     */
    public function regenerate_cache(): void
    {
        update_option(self::CACHE_OPTION, $this->generate(), false);
    }

    /**
     * Pure cache getter — returns the cached XML, or '' on a cold cache.
     * Never builds (use get_or_build_cached() in the request path).
     *
     * @return string
     */
    public function get_cached(): string
    {
        $cached = get_option(self::CACHE_OPTION, '');
        return is_string($cached) ? $cached : '';
    }

    /**
     * Serve the cached feed, building it under a short-lived transient lock on
     * a cold cache so concurrent requests don't all run the full live
     * generation (collect() over all products) at once — stampede guard.
     *
     * @return array{code:int, body:string} 200 + XML, or 503 (with empty body)
     *         when another request is already rebuilding the cache.
     */
    public function get_or_build_cached(): array
    {
        $cached = get_option(self::CACHE_OPTION, '');
        if (is_string($cached) && $cached !== '') {
            return ['code' => 200, 'body' => $cached];
        }
        // Cold cache: only the lock holder builds; everyone else backs off.
        if (get_transient(self::LOCK_TRANSIENT)) {
            return ['code' => 503, 'body' => ''];
        }
        set_transient(self::LOCK_TRANSIENT, 1, self::LOCK_TTL);
        try {
            $this->regenerate_cache();
            $fresh = get_option(self::CACHE_OPTION, '');
        } finally {
            delete_transient(self::LOCK_TRANSIENT);
        }
        return ['code' => 200, 'body' => is_string($fresh) ? $fresh : ''];
    }

    /**
     * template_redirect handler: serve the feed when requested with a valid token.
     *
     * @return void
     */
    public function handle_feed_request(): void
    {
        if (!isset($_GET[self::QUERY_VAR])) {
            return;
        }
        if (!self::is_enabled()) {
            status_header(404);
            exit;
        }
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        if (!$this->verify_token($token)) {
            status_header(403);
            exit;
        }
        $result = $this->get_or_build_cached();
        if ($result['code'] !== 200) {
            status_header(503);
            if (!headers_sent()) {
                header('Retry-After: 120');
            }
            exit;
        }
        if (!headers_sent()) {
            header('Content-Type: application/xml; charset=UTF-8');
        }
        echo $result['body'];
        exit;
    }

    /**
     * Cron callback: refresh the cached feed when enabled.
     *
     * @return void
     */
    public static function cron_regenerate(): void
    {
        if (!self::is_enabled()) {
            return;
        }
        (new self())->regenerate_cache();
    }
}
