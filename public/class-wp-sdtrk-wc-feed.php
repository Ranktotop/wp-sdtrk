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
    public const CRON_HOOK     = 'wp_sdtrk_cron_generate_feed';
    public const LOCK_TRANSIENT = 'wp_sdtrk_feed_lock';
    public const LOCK_TTL       = 300; // seconds — short-lived stampede guard

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
            if ($product->is_type('variable')) {
                foreach ($product->get_children() as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        $rows[] = $this->product_row($variation, $brand, $currency, (string) $product->get_id());
                    }
                }
                continue;
            }
            $rows[] = $this->product_row($product, $brand, $currency, '');
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
     * @return array
     */
    private function product_row($product, string $brand, string $currency, string $group_id): array
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
