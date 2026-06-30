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
     * Buffer an added product in the WC session for the next page load.
     *
     * Hooked to `woocommerce_add_to_cart` (fires for both AJAX and form add-to-cart).
     * Single product pages add via a form submit that navigates away, so there is
     * no reliable client-side event; instead the added line is stored server-side
     * and seeded as an add_to_cart event by localize_commerce_data() on the next
     * page render (see view-item-and-add-to-cart.md).
     *
     * @param string $cart_item_key
     * @param int    $product_id
     * @param int    $quantity
     * @param int    $variation_id
     * @param array  $variation
     * @param array  $cart_item_data
     * @return void
     */
    public function capture_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id = 0, $variation = array(), $cart_item_data = array()): void
    {
        if (!self::is_active()) {
            return;
        }
        if (!function_exists('WC') || !WC()->session) {
            return;
        }
        $pid = $variation_id ? $variation_id : $product_id;
        $product = function_exists('wc_get_product') ? wc_get_product($pid) : null;
        if (!$product) {
            return;
        }

        $mapper = new Wp_Sdtrk_WC_Order_Mapper();
        $line   = $mapper->productLine($product, (int) $quantity);

        $pending = WC()->session->get('wp_sdtrk_atc', array());
        if (!is_array($pending)) {
            $pending = array();
        }
        $pending[] = $line;
        WC()->session->set('wp_sdtrk_atc', $pending);
    }

    /**
     * Build the AddToCart data the engine consumes from the session buffer.
     *
     * Localized as wp_sdtrk_wc.addToCart; the engine seeds it as an add_to_cart
     * event. `value` is the summed line total (price*qty) across all buffered
     * lines; `items` carries the whole buffer — multiple adds before a page load
     * merge into a single add_to_cart event (a consequence of the one-event-per-
     * load seed model; see view-item-and-add-to-cart.md).
     *
     * @param array $pending Buffered productLine entries.
     * @return array
     */
    public function build_add_to_cart_payload(array $pending): array
    {
        $value = 0.0;
        foreach ($pending as $line) {
            $qty   = isset($line['qty']) ? (float) $line['qty'] : 0.0;
            $price = isset($line['price']) ? (float) $line['price'] : 0.0;
            $value += $price * $qty;
        }

        return [
            'addToCart' => [
                'value'    => (string) $value,
                'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
                'items'    => array_values($pending),
            ],
        ];
    }

    /**
     * The add-to-cart buffer pending in the WC session (empty when none/no session).
     *
     * @return array
     */
    private function pending_add_to_cart(): array
    {
        if (!function_exists('WC') || !WC()->session) {
            return array();
        }
        $pending = WC()->session->get('wp_sdtrk_atc', array());
        return is_array($pending) ? $pending : array();
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
        $pending    = $this->pending_add_to_cart();
        $is_product = function_exists('is_product') && is_product();

        $source = self::resolve_commerce_source($order !== null, count($pending) > 0, $is_product);

        switch ($source) {
            case 'order':
                // A purchase supersedes any pending add-to-cart: drop the buffer so
                // it cannot fire a phantom add_to_cart for already-bought items on a
                // later page (WooCommerce empties its own cart but not our session key).
                if (count($pending) > 0) {
                    WC()->session->set('wp_sdtrk_atc', array());
                }
                wp_localize_script('wp_sdtrk-engine', 'wp_sdtrk_wc', $this->build_order_payload($order));
                break;
            case 'addToCart':
                // Consume the buffer once: clear it before localizing so a reload
                // of this page does not seed a second add_to_cart event.
                WC()->session->set('wp_sdtrk_atc', array());
                wp_localize_script('wp_sdtrk-engine', 'wp_sdtrk_wc', $this->build_add_to_cart_payload($pending));
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
