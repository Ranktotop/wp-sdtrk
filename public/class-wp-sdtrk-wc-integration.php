<?php

/**
 * WooCommerce integration entry point.
 *
 * Central activation gate for WooCommerce conversion tracking. The whole
 * integration is inert unless WooCommerce is installed AND the `wc_integration`
 * Redux switch is enabled.
 */
class Wp_Sdtrk_WC_Integration
{
    /**
     * Pure activation predicate (unit-testable, no WP/WC dependencies).
     *
     * @param bool $wc_present       WooCommerce class is loaded.
     * @param bool $switch_enabled   The wc_integration Redux switch is on.
     * @return bool
     */
    public static function is_active_for(bool $wc_present, bool $switch_enabled): bool
    {
        return $wc_present && $switch_enabled;
    }

    /**
     * Whether WooCommerce itself is available.
     *
     * @return bool
     */
    public static function is_wc_active(): bool
    {
        return class_exists('WooCommerce');
    }

    /**
     * Whether the WooCommerce tracking integration is active
     * (WooCommerce present AND the Redux switch enabled).
     *
     * @return bool
     */
    public static function is_active(): bool
    {
        return self::is_active_for(
            self::is_wc_active(),
            WP_SDTRK_Helper_Options::get_bool_option('wc_integration', false)
        );
    }

    /**
     * Resolve the current order-received order, or null when not on that page.
     *
     * @return WC_Order|null
     */
    private function current_received_order()
    {
        if (!function_exists('is_order_received_page') || !is_order_received_page()) {
            return null;
        }
        $order_id = absint(get_query_var('order-received'));
        if (!$order_id) {
            return null;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }
        // Require a valid order key before exposing buyer PII (email/name/total/
        // items) to the page. Without this, the order-received endpoint resolves
        // by id alone and the localized data is harvestable by id enumeration.
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        if (!hash_equals((string) $order->get_order_key(), $key)) {
            return null;
        }
        return $order;
    }

    /**
     * Build the order data the engine consumes on the order-received page.
     *
     * Mirrors the field names the engine reads in collect_eventData(): the whole
     * cart (items), order total/currency and the buyer identifiers. The engine
     * turns this into a Purchase event that fires browser + server in one pass,
     * deduplicated via the order id.
     *
     * @param WC_Order $order
     * @return array
     */
    public function build_order_payload($order): array
    {
        $mapper = new Wp_Sdtrk_WC_Order_Mapper();

        return [
            'order' => [
                'orderId'   => (string) $order->get_id(),
                'value'     => (string) $order->get_total(),
                'currency'  => $order->get_currency(),
                'email'     => $order->get_billing_email(),
                'firstName' => $order->get_billing_first_name(),
                'lastName'  => $order->get_billing_last_name(),
                'items'     => $mapper->lineItems($order),
            ],
        ];
    }

    /**
     * Build the ViewItem data the engine consumes on a product page.
     *
     * Localized as wp_sdtrk_wc.viewItem; the engine seeds it as a view_item event
     * that fires browser + server in one pass over all active catchers. Mirrors
     * the order payload field names so the same engine + catcher paths apply.
     *
     * @param WC_Product $product
     * @return array
     */
    public function build_view_item_payload($product): array
    {
        $mapper = new Wp_Sdtrk_WC_Order_Mapper();
        $line   = $mapper->productLine($product, 1);

        return [
            'viewItem' => [
                'prodId'   => $line['id'],
                'name'     => $line['name'],
                'value'    => (string) $line['price'],
                'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
                'items'    => [$line],
            ],
        ];
    }

    /**
     * Pure precedence resolver: at most one commerce source seeds the engine per
     * page load, in the order order > addToCart > viewItem. Keeps the localize
     * method's branching unit-testable without WP/WC context.
     *
     * @param bool $order_received  On the order-received page for a resolvable order.
     * @param bool $has_pending_atc A pending add-to-cart sits in the WC session.
     * @param bool $is_product      On a single product page.
     * @return string 'order' | 'addToCart' | 'viewItem' | 'none'
     */
    public static function resolve_commerce_source(bool $order_received, bool $has_pending_atc, bool $is_product): string
    {
        if ($order_received) {
            return 'order';
        }
        if ($has_pending_atc) {
            return 'addToCart';
        }
        if ($is_product) {
            return 'viewItem';
        }
        return 'none';
    }

    /**
     * Localize the appropriate commerce data onto the engine script.
     *
     * Hooked to `wp_enqueue_scripts` at a priority that runs after the engine is
     * registered/enqueued. Inert unless the integration is active. Exactly one
     * source is localized per page load (order > addToCart > viewItem) so the
     * engine seeds a single commerce event that fires browser + server in one
     * pass.
     *
     * @return void
     */
    public function localize_commerce_data(): void
    {
        if (!self::is_active()) {
            return;
        }

        $order      = $this->current_received_order();
        $pending    = array();
        $is_product = function_exists('is_product') && is_product();

        $source = self::resolve_commerce_source($order !== null, count($pending) > 0, $is_product);

        switch ($source) {
            case 'order':
                wp_localize_script('wp_sdtrk-engine', 'wp_sdtrk_wc', $this->build_order_payload($order));
                break;
            case 'viewItem':
                $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
                if ($product) {
                    wp_localize_script('wp_sdtrk-engine', 'wp_sdtrk_wc', $this->build_view_item_payload($product));
                }
                break;
        }
    }
}
