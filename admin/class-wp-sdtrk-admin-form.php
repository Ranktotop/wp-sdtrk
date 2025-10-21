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
        if (isset($_POST['wp_sdtrk_form_action']) && $_POST['wp_sdtrk_form_action'] === 'linkedin_mappings') {
            $this->handle_linkedin_mappings();
        }

        // weitere: elseif ($_POST['wp_sdtrk_form_action'] === '...') ...
    }


    /**
     * Handle LinkedIn mapping form submission.
     *
     * @return void
     */
    private function handle_linkedin_mappings(): void
    {
        // 1) Nonce prüfen
        if (!isset($_POST['wp_sdtrk_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wp_sdtrk_nonce'])), 'wp_sdtrk_linkedin_mappings')) {
            return;
        }

        // 2) Eingaben säubern
        $mappings_data = isset($_POST['sdtrk_linkedin_mappings']) ? array_map(function ($mapping) {
            return [
                'event'   => sanitize_text_field($mapping['event'] ?? ''),
                'convid'  => sanitize_text_field($mapping['convid'] ?? ''),
                'rules'   => is_array($mapping['rules'] ?? []) ? array_map(function ($rule) {
                    return [
                        'param' => sanitize_text_field($rule['param'] ?? ''),
                        'value' => sanitize_text_field($rule['value'] ?? ''),
                    ];
                }, $mapping['rules']) : [],
            ];
        }, (array) $_POST['sdtrk_linkedin_mappings']) : [];

        // 3) Pflicht prüfen
        if (empty($mappings_data)) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('No mappings provided', 'wp-sdtrk')
                    . '</p></div>';
            });
            return;
        }

        // 4) Validierung: Jedes Mapping muss Event und Conversion-ID haben
        foreach ($mappings_data as $mapping) {
            if (empty($mapping['event']) || empty($mapping['convid'])) {
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-error"><p>'
                        . esc_html__('Each mapping must have an Event and LinkedIn Conversion-ID', 'wp-sdtrk')
                        . '</p></div>';
                });
                return;
            }
        }

        try {
            // 5) Speichere Mappings
            update_option('sdtrk_linkedin_mappings', $mappings_data);

            // 6) Erfolg – Redirect mit Hinweis
            $redirect_url = add_query_arg(
                'sdtrk_success',
                urlencode(__('LinkedIn mappings saved successfully', 'wp-sdtrk')),
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
}
