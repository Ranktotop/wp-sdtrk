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
