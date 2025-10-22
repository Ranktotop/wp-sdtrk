<?php
class Wp_Sdtrk_Admin_Form_Handler
{
    public function handle_admin_form_callback(): void
    {
        if (!is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (isset($_POST['wp_sdtrk_form_action']) && $_POST['wp_sdtrk_form_action'] === 'create_linkedin_mapping') {
            $this->handle_create_linkedin_mapping();
        }

        if (isset($_POST['wp_sdtrk_form_action']) && $_POST['wp_sdtrk_form_action'] === 'update_linkedin_mapping') {
            $this->handle_update_linkedin_mapping();
        }
    }

    private function handle_create_linkedin_mapping(): void
    {
        if (
            !isset($_POST['wp_sdtrk_nonce']) ||
            !wp_verify_nonce($_POST['wp_sdtrk_nonce'], 'wp_sdtrk_create_linkedin_mapping')
        ) {
            return;
        }

        $event_name = sanitize_text_field($_POST['sdtrk_new_mapping_event'] ?? '');
        $conversion_id = sanitize_text_field($_POST['sdtrk_new_mapping_convid'] ?? '');

        if (!$event_name || !$conversion_id) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . esc_html__('Event and LinkedIn Conversion-ID are required', 'wp-sdtrk') . '</p></div>';
            });
            return;
        }

        // check if event is tag-based (e.g. button_click, element_visible)
        $is_tag_based_event = in_array($event_name, ['button_click', 'element_visible']);
        if ($is_tag_based_event) {
            $tag = trim(sanitize_text_field($_POST['sdtrk_new_mapping_element_tag'] ?? ''));
            if (empty($tag)) {
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-error"><p>' . esc_html__('For tag-based events, the element tag is required', 'wp-sdtrk') . '</p></div>';
                });
                return;
            }
            // calc real event name. E.g.: button_click_<tag>
            $event_name = $event_name . '_' . $tag;
        }

        // collect rules if not tag-based event
        $rules = [];
        if (isset($_POST['rules']) && is_array($_POST['rules']) && !$is_tag_based_event) {
            foreach ($_POST['rules'] as $rule) {
                if (!empty($rule['param'])) {
                    $rules[] = [
                        'param' => sanitize_text_field($rule['param']),
                        'value' => sanitize_text_field($rule['value'] ?? ''),
                    ];
                }
            }
        }

        try {
            WP_SDTRK_Helper_Linkedin::create($conversion_id, $event_name, $rules);

            wp_safe_redirect(add_query_arg('sdtrk_success', urlencode(__('Mapping created successfully', 'wp-sdtrk')), $_SERVER['REQUEST_URI']));
            exit;
        } catch (\Exception $e) {
            add_action('admin_notices', function () use ($e) {
                echo '<div class="notice notice-error"><p>' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }

    /**
     * Handle the linkedin mapping update form submission.
     *
     * @return void
     */
    private function handle_update_linkedin_mapping(): void
    {
        // 1) Nonce prüfen
        if (
            ! isset($_POST['wp_sdtrk_nonce']) ||
            ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wp_sdtrk_nonce'])), 'wp_sdtrk_update_linkedin_mapping')
        ) {
            return;
        }

        // 2) Eingaben säubern
        $mapping_id     = isset($_POST['mapping_id']) ? intval($_POST['mapping_id']) : 0;
        $conversion_id  = sanitize_text_field($_POST['sdtrk_edit_mapping_convid'] ?? '');
        $event_name     = sanitize_text_field($_POST['sdtrk_edit_mapping_event'] ?? '');

        if (!$conversion_id || !$event_name) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . esc_html__('LinkedIn Conversion-ID and Event Name are required', 'wp-sdtrk') . '</p></div>';
            });
            return;
        }

        // check if event is tag-based (e.g. button_click, element_visible)
        $is_tag_based_event = in_array($event_name, ['button_click', 'element_visible']);
        if ($is_tag_based_event) {
            $tag = trim(sanitize_text_field($_POST['sdtrk_edit_mapping_element_tag'] ?? ''));
            if (empty($tag)) {
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-error"><p>' . esc_html__('For tag-based events, the element tag is required', 'wp-sdtrk') . '</p></div>';
                });
                return;
            }
            // calc real event name. E.g.: button_click_<tag>
            $event_name = $event_name . '_' . $tag;
        }

        // Rules sammeln
        $rules = [];
        if (isset($_POST['rules']) && is_array($_POST['rules']) && !$is_tag_based_event) {
            foreach ($_POST['rules'] as $rule) {
                if (!empty($rule['param'])) {
                    $rules[] = [
                        'param' => sanitize_text_field($rule['param']),
                        'value' => sanitize_text_field($rule['value'] ?? ''),
                    ];
                }
            }
        }

        // 3) Pflicht prüfen
        if ($mapping_id <= 0) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('Invalid mapping ID', 'wp-sdtrk')
                    . '</p></div>';
            });
            return;
        }

        try {
            // 4) Neue Regeln setzen
            $mapping = WP_SDTRK_Model_Linkedin::load_by_id($mapping_id);
            $mapping->set_rules(json_encode($rules));

            $check_for_duplicate = $mapping->get_conversion_id() !== $conversion_id || $mapping->get_event() !== $event_name;

            // 5) Wenn neue convid prüfe auf Duplikat und ggf. setzen
            if ($check_for_duplicate) {
                $existing = WP_SDTRK_Helper_Linkedin::get_by_event_and_convid($event_name, $conversion_id);
                if ($existing && $existing->get_id() !== $mapping->get_id()) {
                    throw new \Exception(sprintf(
                        __('Mapping with event "%s" and conversion ID "%s" already exists.', 'wp-sdtrk'),
                        $event_name,
                        $conversion_id
                    ));
                }
                $mapping->set_conversion_id($conversion_id);
                $mapping->set_event($event_name);
            }

            $mapping->save();

            // 6) Erfolg – Redirect mit Hinweis
            $redirect_url = add_query_arg(
                'sdtrk_success',
                urlencode(__('Successfully updated linkedin mapping', 'wp-sdtrk')),
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
