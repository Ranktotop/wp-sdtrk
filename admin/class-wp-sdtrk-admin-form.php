<?php
class Wp_Sdtrk_Admin_Form_Handler
{

    /**
     * Handles form submissions in the admin area.
     *
     * This function is attached to the 'admin_init' action hook and is called
     * on every admin page load. It is responsible for handling form submissions
     * and redirecting the user to the appropriate page after submission.
     *
     * It currently handles the creation of new products, but could be extended
     * to handle other types of form submissions in the future.
     *
     * @since 1.0.0
     */
    public function handle_admin_form_callback(): void
    {
        // Nonce check
        // We don't check nonce here, because each handler uses its own nonce field

        // Admin check
        if (!is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // Zentrale Routing-Logik
        if (isset($_POST['wp_sdtrk_form_action']) && $_POST['wp_sdtrk_form_action'] === 'create_product') {
            $this->handle_create_product();
        }
        if (isset($_POST['wp_sdtrk_form_action']) && $_POST['wp_sdtrk_form_action'] === 'create_product_mapping') {
            $this->handle_create_product_mapping();
        }
        if (isset($_POST['wp_sdtrk_form_action']) && $_POST['wp_sdtrk_form_action'] === 'update_product_mapping') {
            $this->handle_update_product_mapping();
        }
        if (isset($_POST['wp_sdtrk_form_action']) && $_POST['wp_sdtrk_form_action'] === 'create_override') {
            $this->handle_override_access();
        }

        // weitere: elseif ($_POST['wp_sdtrk_form_action'] === '...') ...
    }


    /**
     * Handles the creation of new products.
     *
     * This function is called when the form submission contains the
     * 'wp_sdtrk_form_action' parameter with the value 'create_product'.
     *
     * It checks the nonce and verifies that the product ID and title are
     * not empty. If the checks pass, it creates a new product using the
     * WP_SDTRK_Helper_Product class and redirects the user to the same page
     * with a success message. If the checks fail, it adds an error message
     * to the admin notices.
     *
     * @since 1.0.0
     */
    private function handle_create_product(): void
    {
        if (
            !isset($_POST['wp_sdtrk_nonce']) ||
            !wp_verify_nonce($_POST['wp_sdtrk_nonce'], 'wp_sdtrk_create_product')
        ) {
            return;
        }

        $sku  = sanitize_text_field($_POST['sdtrk_new_product_sku'] ?? '');
        $name = sanitize_text_field($_POST['sdtrk_new_product_name'] ?? '');
        $desc = sanitize_textarea_field($_POST['sdtrk_new_product_description'] ?? '');

        if (!$sku || !$name) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . esc_html__('Product ID and title are required', 'wp-sdtrk') . '</p></div>';
            });
            return;
        }

        try {
            WP_SDTRK_Helper_Product::create($sku, $name, $desc);

            wp_safe_redirect(add_query_arg('sdtrk_success', urlencode(__('Product created successfully', 'wp-sdtrk')), $_SERVER['REQUEST_URI']));
            exit;
        } catch (\Exception $e) {
            add_action('admin_notices', function () use ($e) {
                echo '<div class="notice notice-error"><p>' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }

    /**
     * Handles the creation of product mappings.
     *
     * This function is called when the form submission contains the
     * 'wp_sdtrk_form_action' parameter with the value 'create_product_mapping'.
     * It verifies the nonce and checks if a product has been selected.
     * If so, it merges space and course IDs and assigns them to the product.
     * Successful operations redirect the user with a success message, 
     * while errors are displayed as admin notices.
     *
     * @since 1.0.0
     */

    /**
     * Handle the product→space mapping form submission.
     *
     * @return void
     */
    private function handle_create_product_mapping(): void
    {
        // 1) Nonce prüfen
        if (
            ! isset($_POST['wp_sdtrk_nonce']) ||
            ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wp_sdtrk_nonce'])), 'wp_sdtrk_map_product')
        ) {
            return;
        }

        // 2) Eingaben säubern
        $product_id = isset($_POST['sdtrk_product_id']) ? intval($_POST['sdtrk_product_id']) : 0;
        $space_ids  = isset($_POST['sdtrk_spaces']) ? array_map('intval', (array) $_POST['sdtrk_spaces']) : [];

        // 3) Pflicht prüfen
        if ($product_id <= 0) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('Please select a product', 'wp-sdtrk')
                    . '</p></div>';
            });
            return;
        }

        try {
            // 4) Alte Mappings löschen
            WP_SDTRK_Helper_Product_Space::remove_mappings_for_product($product_id);

            // 5) Neue Mappings anlegen und retroaktiv Zugänge vergeben
            foreach ($space_ids as $space_id) {
                WP_SDTRK_Helper_Product_Space::create_mapping_and_assign_users($product_id, $space_id);
            }

            //update access
            WP_SDTRK_Cron::check_expirations(product_id: $product_id);

            // 6) Erfolg – Redirect mit Hinweis
            $redirect_url = add_query_arg(
                'sdtrk_success',
                urlencode(__('Product mappings created successfully', 'wp-sdtrk')),
                $_SERVER['REQUEST_URI']
            );
            wp_safe_redirect($redirect_url);
            exit;
        } catch (\Exception $e) {
            // 7) Fehler anzeigen
            add_action('admin_notices', function () use ($e) {
                echo '<div class="notice notice-error"><p>'
                    . esc_html($e->getMessage())
                    . '</p></div>';
            });
        }
    }

    /**
     * Handle the product→space mapping update form submission.
     *
     * @return void
     */
    private function handle_update_product_mapping(): void
    {
        // 1) Nonce prüfen
        if (
            ! isset($_POST['wp_sdtrk_nonce']) ||
            ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wp_sdtrk_nonce'])), 'wp_sdtrk_update_product_mapping')
        ) {
            return;
        }

        // 2) Eingaben säubern
        $product_id     = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $new_space_ids  = isset($_POST['sdtrk_edit_entities'])
            ? array_map('intval', (array) $_POST['sdtrk_edit_entities'])
            : [];

        // 3) Pflicht prüfen
        if ($product_id <= 0) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('Invalid product ID', 'wp-sdtrk')
                    . '</p></div>';
            });
            return;
        }

        try {
            // 4) Alle alten Mappings entfernen
            WP_SDTRK_Helper_Product_Space::remove_mappings_for_product($product_id);

            // 5) Neue Mappings anlegen und retroaktiv Zugänge vergeben
            foreach ($new_space_ids as $space_id) {
                WP_SDTRK_Helper_Product_Space::create_mapping_and_assign_users($product_id, $space_id);
            }

            //update access
            WP_SDTRK_Cron::check_expirations(product_id: $product_id);

            // 6) Erfolg – Redirect mit Hinweis
            $redirect_url = add_query_arg(
                'sdtrk_success',
                urlencode(__('Successfully updated product mappings', 'wp-sdtrk')),
                $_SERVER['REQUEST_URI']
            );
            wp_safe_redirect($redirect_url);
            exit;
        } catch (\Exception $e) {
            // 7) Fehler anzeigen
            add_action('admin_notices', function () use ($e) {
                echo '<div class="notice notice-error"><p>'
                    . esc_html($e->getMessage())
                    . '</p></div>';
            });
        }
    }

    /**
     * Handles creation or update of admin-defined access overrides.
     *
     * @since 1.0.0
     */
    private function handle_override_access(): void
    {
        if (
            !isset($_POST['wp_sdtrk_nonce']) ||
            !wp_verify_nonce($_POST['wp_sdtrk_nonce'], 'wp_sdtrk_create_override')
        ) {
            return;
        }

        $user_id     = (int) ($_POST['user_id'] ?? 0);
        $product_id  = (int) ($_POST['product_id'] ?? 0);
        $mode        = sanitize_text_field($_POST['mode'] ?? '');
        $comment        = sanitize_text_field($_POST['comment'] ?? '');
        $valid_until = sanitize_text_field($_POST['valid_until'] ?? '');

        if (
            !$user_id ||
            !$product_id ||
            !in_array($mode, ['allow', 'deny'], true) ||
            empty($valid_until)
        ) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . esc_html__('Invalid form data for access override', 'wp-sdtrk') . '</p></div>';
            });
            return;
        }

        // Konvertiere ins externe Produkt-ID-Format
        $product = WP_SDTRK_Model_Product::load_by_id($product_id);

        try {
            $existing = WP_SDTRK_Helper_Access_Override::get_latest_override_by_product_user($user_id, $product->get_id(), false);

            if ($existing) {
                WP_SDTRK_Helper_Access_Override::patch_override($existing->get_id(), $valid_until, $mode, $comment);
            } else {
                $existing =
                    WP_SDTRK_Helper_Access_Override::add_override($user_id, $product->get_id(), $mode, $valid_until, $comment);
            }

            //update access
            WP_SDTRK_Cron::check_expirations(user_id: $user_id, product_id: $product->get_id());

            wp_safe_redirect(add_query_arg('sdtrk_success', urlencode(__('Access override saved', 'wp-sdtrk')), $_SERVER['REQUEST_URI']));
            exit;
        } catch (\Exception $e) {
            add_action('admin_notices', function () use ($e) {
                echo '<div class="notice notice-error"><p>' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
}
