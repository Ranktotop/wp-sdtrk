<?php
if (! defined('ABSPATH') || ! is_user_logged_in() || ! current_user_can('manage_options')) {
    wp_redirect(home_url());
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('LinkedIn Event Conversion Mappings', 'wp-sdtrk'); ?></h1>
    <p><?php esc_html_e('Map LinkedIn custom conversions to events with optional attribute rules (AND logic).', 'wp-sdtrk'); ?></p>

    <hr style="margin: 20px 0;">

    <!-- Existing Mappings Table -->
    <h2><?php esc_html_e('Existing Mappings', 'wp-sdtrk'); ?></h2>

    <table class="widefat striped">
        <thead>
            <tr>
                <th style="width: 20%;"><?php esc_html_e('Event', 'wp-sdtrk'); ?></th>
                <th style="width: 20%;"><?php esc_html_e('LinkedIn Conversion-ID', 'wp-sdtrk'); ?></th>
                <th style="width: 40%;"><?php esc_html_e('Rules (AND)', 'wp-sdtrk'); ?></th>
                <th style="width: 20%;"><?php esc_html_e('Actions', 'wp-sdtrk'); ?></th>
            </tr>
        </thead>
        <tbody id="mappings-list">
            <?php
            $mappings = get_option('sdtrk_linkedin_mappings', []);

            if (empty($mappings)) :
            ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px;">
                        <?php esc_html_e('No mappings found. Add one below.', 'wp-sdtrk'); ?>
                    </td>
                </tr>
                <?php else :
                foreach ($mappings as $mapping_id => $mapping) :
                    $event_labels = [
                        'page_view'      => __('Page View', 'wp-sdtrk'),
                        'add_to_cart'    => __('Add to Cart', 'wp-sdtrk'),
                        'purchase'       => __('Purchase', 'wp-sdtrk'),
                        'sign_up'        => __('Complete registration', 'wp-sdtrk'),
                        'generate_lead'  => __('Lead', 'wp-sdtrk'),
                        'begin_checkout' => __('Initiate checkout', 'wp-sdtrk'),
                        'view_item'      => __('View Content', 'wp-sdtrk'),
                    ];

                    $event_label = isset($event_labels[$mapping['event']]) ? $event_labels[$mapping['event']] : $mapping['event'];
                    $rules_count = isset($mapping['rules']) ? count($mapping['rules']) : 0;
                ?>
                    <tr data-id="<?php echo esc_attr($mapping_id); ?>">
                        <td><?php echo esc_html($event_label); ?></td>
                        <td><code><?php echo esc_html($mapping['convid']); ?></code></td>
                        <td>
                            <small style="color: #666;">
                                <?php if ($rules_count > 0) : ?>
                                    <?php foreach ($mapping['rules'] as $rule) : ?>
                                        <div><?php printf(
                                                    esc_html__('%s = %s', 'wp-sdtrk'),
                                                    esc_html($rule['param']),
                                                    esc_html($rule['value'] ?: __('(any)', 'wp-sdtrk'))
                                                ); ?></div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <em><?php esc_html_e('All events', 'wp-sdtrk'); ?></em>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <button type="button" class="button edit-mapping-btn" data-id="<?php echo esc_attr($mapping_id); ?>">
                                <?php esc_html_e('Edit', 'wp-sdtrk'); ?>
                            </button>
                            <button type="button" class="button button-danger remove-mapping" data-id="<?php echo esc_attr($mapping_id); ?>">
                                ✕ <?php esc_html_e('Delete', 'wp-sdtrk'); ?>
                            </button>
                        </td>
                    </tr>
            <?php endforeach;
            endif;
            ?>
        </tbody>
    </table>

    <hr style="margin: 30px 0;">

    <!-- Add/Edit New Mapping Form -->
    <h2><?php esc_html_e('Add New Mapping', 'wp-sdtrk'); ?></h2>

    <form method="post" id="edit-mapping-form">
        <input type="hidden" name="wp_sdtrk_form_action" value="linkedin_mappings">
        <?php wp_nonce_field('wp_sdtrk_linkedin_mappings', 'wp_sdtrk_nonce'); ?>
        <input type="hidden" id="edit-mapping-id" name="edit-mapping-id" value="">

        <table class="form-table">
            <tr>
                <th><label for="edit-event"><?php esc_html_e('Event', 'wp-sdtrk'); ?></label></th>
                <td>
                    <select id="edit-event" name="edit-event" required>
                        <option value=""><?php esc_html_e('-- Select Event --', 'wp-sdtrk'); ?></option>
                        <option value="page_view"><?php esc_html_e('Page View', 'wp-sdtrk'); ?></option>
                        <option value="add_to_cart"><?php esc_html_e('Add to Cart', 'wp-sdtrk'); ?></option>
                        <option value="purchase"><?php esc_html_e('Purchase', 'wp-sdtrk'); ?></option>
                        <option value="sign_up"><?php esc_html_e('Complete registration', 'wp-sdtrk'); ?></option>
                        <option value="generate_lead"><?php esc_html_e('Lead', 'wp-sdtrk'); ?></option>
                        <option value="begin_checkout"><?php esc_html_e('Initiate checkout', 'wp-sdtrk'); ?></option>
                        <option value="view_item"><?php esc_html_e('View Content', 'wp-sdtrk'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="edit-convid"><?php esc_html_e('LinkedIn Conversion-ID', 'wp-sdtrk'); ?></label></th>
                <td>
                    <input type="text" id="edit-convid" name="edit-convid" placeholder="e.g., 1234567890" required>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Attribute Rules (AND Logic)', 'wp-sdtrk'); ?></h3>
        <p style="color: #666; margin-bottom: 15px;">
            <?php esc_html_e('All rules must match for the event to trigger. Leave empty to match any value.', 'wp-sdtrk'); ?>
        </p>

        <div id="rules-container">
            <!-- Rules will be added here by JavaScript -->
        </div>

        <button type="button" class="button button-secondary" id="add-rule-btn" style="margin-bottom: 20px;">
            + <?php esc_html_e('Add Rule', 'wp-sdtrk'); ?>
        </button>

        <div>
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Save Mapping', 'wp-sdtrk'); ?>
            </button>
            <button type="button" class="button" id="clear-form-btn">
                <?php esc_html_e('Clear', 'wp-sdtrk'); ?>
            </button>
        </div>
    </form>

    <div id="mapping-message" class="notice" style="display:none; margin-top: 20px;"></div>
</div>