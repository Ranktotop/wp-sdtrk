<?php

/**
 * WooCommerce integration entry point.
 *
 * Central activation gate and hook registration for WooCommerce conversion
 * tracking. The whole integration is inert unless WooCommerce is installed
 * AND the `wc_integration` Redux switch is enabled.
 *
 * Design: tasks/wc-design.md
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
     * Build the localized purchase payload for the browser tracker from an order.
     *
     * @param WC_Order $order
     * @return array
     */
    public function build_browser_payload($order): array
    {
        $mapper = new Wp_Sdtrk_WC_Order_Mapper();
        $arr    = $mapper->toEventArray($order);

        return [
            'order' => [
                'orderId'   => (string) $order->get_id(),
                'key'       => $order->get_order_key(),
                'value'     => (string) $order->get_total(),
                'currency'  => $order->get_currency(),
                'prodId'    => $arr['prodId'][0] ?? '',
                'prodName'  => $arr['prodName'][0] ?? '',
                'email'     => $order->get_billing_email(),
                'firstName' => $order->get_billing_first_name(),
                'lastName'  => $order->get_billing_last_name(),
                'contents'  => $mapper->lineItems($order),
                'source'    => $order->get_checkout_order_received_url(),
                'ip'        => $order->get_customer_ip_address(),
                'agent'     => $order->get_customer_user_agent(),
                'utm'       => $arr['utm'],
            ],
        ];
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
     * Enqueue + localize the browser purchase tracker on the order-received page.
     *
     * Hooked to `wp_enqueue_scripts`. Inert unless the integration is active and
     * we are on the WooCommerce order-received page for a resolvable order.
     *
     * @return void
     */
    public function enqueue_purchase_assets(): void
    {
        if (!self::is_active()) {
            return;
        }
        $order = $this->current_received_order();
        if ($order === null) {
            return;
        }

        $version = defined('WP_SDTRK_VERSION') ? WP_SDTRK_VERSION : false;
        wp_enqueue_script(
            'wp_sdtrk-wc',
            plugin_dir_url(__FILE__) . 'js/wp-sdtrk-wc.js',
            ['wp_sdtrk-engine'],
            $version,
            true
        );
        wp_localize_script('wp_sdtrk-wc', 'wp_sdtrk_wc', $this->build_browser_payload($order));
    }

    /**
     * Pure server-firing decision (unit-testable, no WP/WC dependencies).
     *
     * @param bool $consented    Server consent was granted for the platform.
     * @param bool $bypass       Consent bypass is active for the order.
     * @param bool $already_sent The platform's server event was already sent.
     * @return bool
     */
    public static function should_fire_server(bool $consented, bool $bypass, bool $already_sent): bool
    {
        if ($already_sent) {
            return false;
        }
        return $bypass || $consented;
    }

    /**
     * Per-platform server tracker class + the identifier keys it consumes.
     *
     * @return array<string, array{class:string, ids:array<int,string>}>
     */
    private function server_platforms(): array
    {
        return [
            'meta' => ['class' => 'Wp_Sdtrk_Tracker_Meta', 'ids' => ['fbp', 'fbc']],
            'ga'   => ['class' => 'Wp_Sdtrk_Tracker_Ga',   'ids' => ['cid']],
            'tt'   => ['class' => 'Wp_Sdtrk_Tracker_Tt',   'ids' => ['ttp', 'ttc', 'hash']],
        ];
    }

    /**
     * AJAX: persist the browser consent snapshot + identifiers onto the order.
     *
     * Hooked to wp_ajax(_nopriv)_wp_sdtrk_wc_persist. The order-status server
     * hook reads this snapshot to gate firing and to reuse the exact identifiers
     * the browser used (so the server and browser events deduplicate).
     *
     * @return void
     */
    public function handle_persist_ajax(): void
    {
        if (!isset($_POST['_nonce']) || !wp_verify_nonce($_POST['_nonce'], 'security_wp-sdtrk')) {
            wp_send_json_error();
        }
        if (!self::is_active() || !function_exists('wc_get_order')) {
            wp_send_json_error();
        }

        $snapshot = (isset($_POST['snapshot']) && is_array($_POST['snapshot'])) ? wp_unslash($_POST['snapshot']) : [];
        $order_id = absint($snapshot['orderId'] ?? 0);
        $key      = isset($snapshot['key']) ? sanitize_text_field($snapshot['key']) : '';

        $order = $order_id ? wc_get_order($order_id) : false;
        if (!$order || !hash_equals((string) $order->get_order_key(), $key)) {
            wp_send_json_error();
        }

        // Consent per platform (server consent captured browser-side).
        $consent_in = (isset($snapshot['consent']) && is_array($snapshot['consent'])) ? $snapshot['consent'] : [];
        $consent    = [];
        foreach (array_keys($this->server_platforms()) as $platform) {
            $value              = $consent_in[$platform] ?? false;
            $consent[$platform] = ($value === true || $value === 'true' || $value === '1' || $value === 1);
        }

        // Identifiers exactly as the browser computed them (for dedup).
        $ids_in = (isset($snapshot['ids']) && is_array($snapshot['ids'])) ? $snapshot['ids'] : [];
        $ids    = [];
        foreach (['fbp', 'fbc', 'cid', 'ttp', 'ttc', 'hash'] as $k) {
            $ids[$k] = isset($ids_in[$k]) ? sanitize_text_field($ids_in[$k]) : '';
        }

        $order->update_meta_data('_wp_sdtrk_consent', $consent);
        $order->update_meta_data('_wp_sdtrk_ids', $ids);
        $order->save();

        wp_send_json_success();
    }

    /**
     * Fire the server-side conversion APIs for a paid order.
     *
     * Hooked to woocommerce_order_status_processing and ..._completed. Both can
     * fire for the same order (sync vs. async payments), so each platform is
     * sent at most once (idempotency flag). Consent-gated via the persisted
     * snapshot; identifiers are replayed for browser/server deduplication
     * (shared event_id = order id).
     *
     * @param int $order_id
     * @return void
     */
    public function on_order_paid($order_id): void
    {
        if (!self::is_active() || !function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $consent = $order->get_meta('_wp_sdtrk_consent');
        $ids     = $order->get_meta('_wp_sdtrk_ids');
        $consent = is_array($consent) ? $consent : [];
        $ids     = is_array($ids) ? $ids : [];
        $bypass  = (bool) $order->get_meta('_wp_sdtrk_bypass');

        $mapper    = new Wp_Sdtrk_WC_Order_Mapper();
        $event_arr = $mapper->toEventArray($order);

        $dirty = false;
        foreach ($this->server_platforms() as $platform => $cfg) {
            $already_sent = (bool) $order->get_meta('_wp_sdtrk_server_sent_' . $platform);
            $consented    = !empty($consent[$platform]);
            if (!self::should_fire_server($consented, $bypass, $already_sent)) {
                continue;
            }
            if (!class_exists($cfg['class'])) {
                continue;
            }

            $data = [];
            foreach ($cfg['ids'] as $id_key) {
                if (isset($ids[$id_key]) && $ids[$id_key] !== '') {
                    $data[$id_key] = $ids[$id_key];
                }
            }
            // TikTok composes its event_id as "<orderId>_<hash>", so the key must
            // exist even when empty to avoid an undefined-key notice.
            if ($platform === 'tt' && !isset($data['hash'])) {
                $data['hash'] = '';
            }

            $event   = new Wp_Sdtrk_Tracker_Event($event_arr);
            $tracker = new $cfg['class']();
            $tracker->fireTracking_Server($event, 'Event', $data);

            $order->update_meta_data('_wp_sdtrk_server_sent_' . $platform, '1');
            $dirty = true;
        }

        if ($dirty) {
            $order->save();
        }
    }
}
