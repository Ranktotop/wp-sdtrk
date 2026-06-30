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

        // Capability check — these are administrative mutations (LinkedIn
        // mappings, feed token). is_admin() is NOT a gate (it is true for any
        // admin-ajax request, including unauthenticated ones), so verify the
        // actual capability.
        if (! current_user_can('manage_options')) {
            wp_send_json_error();
            die();
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
     * Deletes all space assignments of a product.
     *
     * @param array $data Must contain the key 'product_id'.
     * @param array $meta Optional, ignored here.
     * @return array JSON response (state => bool, message => string)
     */
    private function delete_linkedin_mapping(array $data, array $meta): array
    {
        // 1) Validierung
        if (empty($data['mapping_id']) || ! is_numeric($data['mapping_id'])) {
            return [
                'state'   => false,
                'message' => __('Invalid mapping ID', 'wp-sdtrk'),
            ];
        }

        $mapping_id = (int) $data['mapping_id'];

        try {
            // Remove the mapping
            WP_SDTRK_Helper_Linkedin::delete($mapping_id);
            return [
                'state'   => true,
                'message' => sprintf(__('Successfully deleted LinkedIn mapping #%d', 'wp-sdtrk'), $mapping_id)
            ];
        } catch (\Exception $e) {
            return [
                'state'   => false,
                'message' => sprintf(
                    /* translators: %s = Fehlermeldung */
                    __('Error deleting LinkedIn mapping #%d: %s', 'wp-sdtrk'),
                    $mapping_id,
                    $e->getMessage()
                ),
            ];
        }
    }

    /**
     * List published products for the feed management page (paginated, searchable).
     *
     * Translates the table's controls into a wc_get_products() query and marks
     * each row's current feed status. The status filter constrains the query via
     * include/exclude against the exclusion list. Returns pagination metadata
     * plus the global counters (total published vs. excluded) for the header.
     *
     * @param array $data search, page, per_page, status ('all'|'in_feed'|'excluded')
     * @param array $meta Ignored.
     * @return array { state, rows[], total, totalPages, page, totalProducts, excludedCount }
     */
    private function list_feed_products(array $data, array $meta): array
    {
        if (!class_exists('Wp_Sdtrk_WC_Feed') || !function_exists('wc_get_products')) {
            return ['state' => false, 'message' => __('Product feed is not available', 'wp-sdtrk')];
        }

        $feed     = new Wp_Sdtrk_WC_Feed();
        $excluded = $feed->get_excluded_ids();

        $search   = isset($data['search']) ? sanitize_text_field((string) $data['search']) : '';
        $page     = isset($data['page']) ? max(1, (int) $data['page']) : 1;
        $per_page = isset($data['per_page']) ? (int) $data['per_page'] : 50;
        $per_page = max(1, min(200, $per_page));
        $status   = isset($data['status']) ? (string) $data['status'] : 'all';

        $args = [
            'status'   => 'publish',
            'paginate' => true,
            'limit'    => $per_page,
            'page'     => $page,
            'orderby'  => 'title',
            'order'    => 'ASC',
            'return'   => 'objects',
        ];
        if ($search !== '') {
            $args['s'] = $search;
        }
        // Status filter narrows the query against the exclusion list. Using a
        // sentinel [0] for "excluded" with an empty list guarantees zero rows.
        if ($status === 'excluded') {
            $args['include'] = !empty($excluded) ? $excluded : [0];
        } elseif ($status === 'in_feed' && !empty($excluded)) {
            $args['exclude'] = $excluded;
        }

        $result   = wc_get_products($args);
        $products = is_object($result) ? ($result->products ?? []) : (array) $result;
        $total    = is_object($result) ? (int) ($result->total ?? count($products)) : count($products);
        $maxPages = is_object($result) ? (int) ($result->max_num_pages ?? 1) : 1;

        $rows = [];
        foreach ($products as $product) {
            $id    = (int) $product->get_id();
            $price = $product->get_price();
            $rows[] = [
                'id'       => $id,
                'name'     => (string) $product->get_name(),
                'sku'      => (string) $product->get_sku(),
                'price'    => function_exists('wc_price') ? wp_strip_all_tags(wc_price($price)) : (string) $price,
                'image'    => (string) wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                'excluded' => in_array($id, $excluded, true),
            ];
        }

        $total_products = function_exists('wp_count_posts')
            ? (int) (wp_count_posts('product')->publish ?? 0)
            : $total;

        return [
            'state'         => true,
            'rows'          => $rows,
            'total'         => $total,
            'totalPages'    => max(1, $maxPages),
            'page'          => $page,
            'totalProducts' => $total_products,
            'excludedCount' => count($excluded),
        ];
    }

    /**
     * Regenerates the WooCommerce product-feed token and returns the new URL.
     *
     * @param array $data Ignored.
     * @param array $meta Ignored.
     * @return array JSON response (state => bool, url => string, message => string)
     */
    private function regenerate_feed_token(array $data, array $meta): array
    {
        if (!class_exists('Wp_Sdtrk_WC_Feed')) {
            return ['state' => false, 'message' => __('Product feed is not available', 'wp-sdtrk')];
        }

        $feed = new Wp_Sdtrk_WC_Feed();
        $feed->rotate_token();

        return [
            'state'   => true,
            'url'     => $feed->get_feed_url(),
            'message' => __('Feed token regenerated', 'wp-sdtrk'),
        ];
    }

    /**
     * Returns the linkedin mapping for a given mapping ID.
     *
     * @param array $data Must contain the key 'mapping_id'.
     * @param array $meta Optional, ignored here.
     * @return array JSON response (success/fail)
     *
     * @throws \Exception When an error occurs, an exception object is thrown.
     */
    private function get_linkedin_mapping(array $data, array $meta): array
    {
        if (empty($data['mapping_id']) || !is_numeric($data['mapping_id'])) {
            return ['state' => false, 'message' => __('Invalid mapping ID', 'wp-sdtrk')];
        }

        try {
            $mapping_id = (int) $data['mapping_id'];
            $mapping = WP_SDTRK_Helper_Linkedin::get_by_id($mapping_id);

            if (!$mapping) {
                return ['state' => false, 'message' => __('Mapping not found', 'wp-sdtrk')];
            }

            // Rückgabe als einfache Datenstruktur (nicht Objekte)
            $mapping = [
                'id'    => $mapping->get_id(),
                'event' => $mapping->get_event(),
                'convid'  => $mapping->get_conversion_id(),
                'rules' => array_map(function (WP_SDTRK_Model_Linkedin_Rule $rule) {
                    return [
                        'key'   => $rule->get_key_name(),
                        'value' => $rule->get_value(),
                    ];
                }, $mapping->get_rules()),
            ];

            return ['state' => true, 'mapping' => $mapping, 'message' => __('Successfully retrieved LinkedIn mapping', 'wp-sdtrk')];
        } catch (\Exception $e) {
            return ['state' => false, 'message' => $e->getMessage()];
        }
    }
}
