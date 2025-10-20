<?php
class Wp_Sdtrk_Admin_Ajax_Handler
{
    /**
     * Ajax generic Callback-Function
     */
    public function handle_admin_ajax_callback()
    {
        /**
         * Do not forget to check your nonce for security!
         *
         * @link https://codex.wordpress.org/Function_Reference/wp_verify_nonce
         */

        // Nonce check
        if (! wp_verify_nonce($_POST['_nonce'], 'security_wp-sdtrk')) {
            wp_send_json_error();
            die();
        }

        // Admin check
        if (!is_admin()) {
            wp_send_json_error('User is not admin');
            return ['state' => false, 'message' => __('User is not admin', 'wp-sdtrk')];
        }

        // Check if given function exists
        $functionName = $_POST['func'];
        if (! method_exists($this, $functionName)) {
            wp_send_json_error();
            die();
        }

        $_POST['data'] = (isset($_POST['data'])) ? $_POST['data'] : array();
        $_POST['meta'] = (isset($_POST['meta'])) ? $_POST['meta'] : array();

        // Call function and send back result
        $result = $this->$functionName($_POST['data'], $_POST['meta']);
        die(json_encode($result));
    }

    /**
     * Deletes a product by its ID.
     *
     * @param array $data Must contain key 'product_id'.
     * @param array $meta Optional, ignored.
     * @return array JSON response (success/fail)
     *
     * @throws \Exception If an error occurs, an Exception object is thrown.
     */
    private function delete_product(array $data, array $meta): array
    {
        if (!isset($data['product_id']) || !is_numeric($data['product_id'])) {
            return ['state' => false, 'message' => __('Invalid product ID', 'wp-sdtrk')];
        }

        try {
            //Before we delete the product, we remove all users from product exclusive spaces
            $exclusive_spaces = WP_SDTRK_Helper_Fcom::get_spaces_exclusive_to_product((int) $data['product_id']);
            foreach ($exclusive_spaces as $space) {
                $space->revoke_all_user_access();
            }
            WP_SDTRK_Helper_Product::delete((int) $data['product_id']);

            return ['state' => true, 'message' => __('Product successfully deleted', 'wp-sdtrk')];
        } catch (\Exception $e) {
            return ['state' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Updates a product by its ID.
     *
     * @param array $data Must contain key 'product_id'.
     * @param array $meta Optional, ignored.
     * @return array JSON response (success/fail)
     *
     * @throws \Exception If an error occurs, an Exception object is thrown.
     */
    private function update_product(array $data, array $meta): array
    {
        if (!isset($data['product_id']) || !is_numeric($data['product_id'])) {
            return ['state' => false, 'message' => __('Invalid product ID', 'wp-sdtrk')];
        }

        $name = sanitize_text_field($data['name'] ?? '');
        $desc = sanitize_textarea_field($data['description'] ?? '');

        try {
            WP_SDTRK_Helper_Product::update((int) $data['product_id'], $name, $desc);
            return ['state' => true, 'message' => __('Product successfully updated', 'wp-sdtrk')];
        } catch (\Exception $e) {
            return ['state' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Returns the assignment of Spaces and Courses for a product.
     *
     * @param array $data Must contain the key 'product_id'.
     * @param array $meta Optional, ignored here.
     * @return array JSON response (success/fail)
     *
     * @throws \Exception When an error occurs, an exception object is thrown.
     */
    private function get_product_mapping(array $data, array $meta): array
    {
        if (empty($data['product_id']) || !is_numeric($data['product_id'])) {
            return ['state' => false, 'message' => __('Invalid product ID', 'wp-sdtrk')];
        }

        try {
            $product_id = (int) $data['product_id'];
            $product = WP_SDTRK_Helper_Product::get_by_id($product_id);

            $communities = $product->get_mapped_communities();
            $courses = $product->get_mapped_courses();
            $spaces = array_merge($communities, $courses);

            // Rückgabe als einfache Datenstruktur (nicht Objekte)
            $mapping = array_map(function (WP_SDTRK_Model_Fcom $space) {
                return [
                    'id'    => $space->get_id(),
                    'title' => $space->get_title(),
                    'type'  => $space->get_type(),
                ];
            }, $spaces);

            return ['state' => true, 'mapping' => $mapping, 'message' => __('Sucessfully retrieved product mapping', 'wp-sdtrk')];
        } catch (\Exception $e) {
            return ['state' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Deletes all space assignments of a product.
     *
     * @param array $data Must contain the key 'product_id'.
     * @param array $meta Optional, ignored here.
     * @return array JSON response (state => bool, message => string)
     */
    private function delete_product_mapping(array $data, array $meta): array
    {
        // 1) Validierung
        if (empty($data['product_id']) || ! is_numeric($data['product_id'])) {
            return [
                'state'   => false,
                'message' => __('Invalid product ID', 'wp-sdtrk'),
            ];
        }

        $product_id = (int) $data['product_id'];

        try {
            // Remove all space mappings
            WP_SDTRK_Helper_Product_Space::remove_mappings_for_product($product_id);

            //update access
            WP_SDTRK_Cron::check_expirations(product_id: $product_id);

            return [
                'state'   => true,
                'message' => sprintf(__('All mappings successfully deleted for product #%d', 'wp-sdtrk'), $product_id)
            ];
        } catch (\Exception $e) {
            // 4) Fehler-Antwort
            return [
                'state'   => false,
                'message' => sprintf(
                    /* translators: %s = Fehlermeldung */
                    __('Error deleting mappings for product #%d: %s', 'wp-sdtrk'),
                    $product_id,
                    $e->getMessage()
                ),
            ];
        }
    }

    private function delete_access_rule(array $data, array $meta): array
    {
        if (!isset($data['rule_id']) || !is_numeric($data['rule_id'])) {
            return ['state' => false, 'message' => __('Invalid rule ID', 'wp-sdtrk')];
        }

        try {
            $rule = WP_SDTRK_Helper_Access_Override::get_by_id((int) $data['rule_id']);
            if (!$rule) {
                return ['state' => false, 'message' => __('Rule not found', 'wp-sdtrk')];
            }
            WP_SDTRK_Helper_Access_Override::remove_overrides($rule->get_user_id(), $rule->get_product_id());

            //update access
            WP_SDTRK_Cron::check_expirations(user_id: $rule->get_user_id(), product_id: $rule->get_product_id());

            return ['state' => true, 'message' => __('Rule successfully deleted', 'wp-sdtrk')];
        } catch (\Exception $e) {
            return ['state' => false, 'message' => $e->getMessage()];
        }
    }

    private function update_access_rule(array $data, array $meta): array
    {
        if (!isset($data['rule_id']) || !is_numeric($data['rule_id'])) {
            return ['state' => false, 'message' => __('Invalid rule ID', 'wp-sdtrk')];
        }

        $mode = sanitize_text_field($data['mode'] ?? '');
        $valid_until = sanitize_text_field($data['valid_until'] ?? '');
        $comment = sanitize_text_field($data['comment'] ?? '');

        if (!$valid_until) {
            return ['state' => false, 'message' => __('Invalid valid until date', 'wp-sdtrk')];
        }

        try {
            $rule = WP_SDTRK_Helper_Access_Override::get_by_id((int) $data['rule_id']);
            if (!$rule) {
                return ['state' => false, 'message' => __('Rule not found', 'wp-sdtrk')];
            }

            //patch rule
            WP_SDTRK_Helper_Access_Override::patch_override($rule->get_id(), $valid_until, $mode, $comment);

            //update access
            WP_SDTRK_Cron::check_expirations(user_id: $rule->get_user_id(), product_id: $rule->get_product_id());

            return ['state' => true, 'message' => __('Rule successfully updated', 'wp-sdtrk')];
        } catch (\Exception $e) {
            return ['state' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Tests the Community API connection
     *
     * @param array $data Should contain url, port, ssl, master_token, service_token
     * @param array $meta Optional, ignored.
     * @return array JSON response (success/fail)
     */
    private function test_community_api_connection(array $data, array $meta): array
    {
        $url = sanitize_text_field($data['url'] ?? '');
        $port = sanitize_text_field($data['port'] ?? '');
        $ssl = (bool)($data['ssl'] ?? false);
        $master_token = sanitize_text_field(wp_unslash($data['master_token'] ?? ''));
        $service_token = sanitize_text_field(wp_unslash($data['service_token'] ?? ''));

        if (empty($url) || empty($master_token) || empty($service_token)) {
            return ['state' => false, 'message' => __('Please fill in all required fields', 'wp-sdtrk')];
        }

        // Build the full API URL
        $protocol = $ssl ? 'https' : 'http';
        $api_base_url = $protocol . '://' . $url . ':' . $port;

        $results = [];

        // Test 1: Master Token
        $admin_response = wp_remote_get($api_base_url . '/system/verify/admin', [
            'headers' => [
                'auth-token' => $master_token,
                'Accept' => 'application/json'
            ],
            'timeout' => 10
        ]);

        if (is_wp_error($admin_response)) {
            $results['admin'] = 'Error: ' . $admin_response->get_error_message();
        } else {
            $status_code = wp_remote_retrieve_response_code($admin_response);
            if ($status_code === 200) {
                $results['admin'] = 'OK';
            } else {
                $results['admin'] = 'HTTP ' . $status_code;
            }
        }

        // Test 2: Service Token
        $service_response = wp_remote_get($api_base_url . '/system/verify/service', [
            'headers' => [
                'auth-token' => $service_token,
                'Accept' => 'application/json'
            ],
            'timeout' => 10
        ]);

        if (is_wp_error($service_response)) {
            $results['service'] = 'Error: ' . $service_response->get_error_message();
        } else {
            $status_code = wp_remote_retrieve_response_code($service_response);
            if ($status_code === 200) {
                $results['service'] = 'OK';
            } else {
                $results['service'] = 'HTTP ' . $status_code;
            }
        }

        // Result summary
        if ($results['admin'] === 'OK' && $results['service'] === 'OK') {
            return [
                'state' => true,
                'message' => __('Both tokens verified successfully!', 'wp-sdtrk') .
                    '<br>Master Token: ' . $results['admin'] .
                    '<br>Service Token: ' . $results['service']
            ];
        } else {
            return [
                'state' => false,
                'message' => __('Token verification failed!', 'wp-sdtrk') .
                    '<br>Master Token: ' . $results['admin'] .
                    '<br>Service Token: ' . $results['service']
            ];
        }
    }
}
