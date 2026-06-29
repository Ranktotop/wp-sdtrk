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
        return $order ? $order : null;
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
     * Localize the order data onto the engine script on the order-received page.
     *
     * Hooked to `wp_enqueue_scripts` at a priority that runs after the engine is
     * registered/enqueued. Inert unless the integration is active and we are on
     * the order-received page for a resolvable order.
     *
     * @return void
     */
    public function localize_order_data(): void
    {
        if (!self::is_active()) {
            return;
        }
        $order = $this->current_received_order();
        if ($order === null) {
            return;
        }
        wp_localize_script('wp_sdtrk-engine', 'wp_sdtrk_wc', $this->build_order_payload($order));
    }
}
