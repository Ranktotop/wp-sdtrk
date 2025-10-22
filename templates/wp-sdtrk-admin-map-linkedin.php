<?php
// templates/wp-sdtrk-admin-manage-products.php

if (!defined('ABSPATH') || !is_user_logged_in() || !current_user_can('manage_options')) {
    wp_redirect(home_url());
    exit;
}

$helper_linkedin = new WP_SDTRK_Helper_Linkedin();
$mappings = $helper_linkedin->get_all();
$scroll_triggers = WP_SDTRK_Helper_Options::get_scroll_triggers();
$time_triggers = WP_SDTRK_Helper_Options::get_time_triggers();

$available_events = WP_SDTRK_Helper_Linkedin::get_common_events();

//add scroll-triggers to available events
foreach ($scroll_triggers as $scroll_trigger) {
    $available_events['scroll_' . $scroll_trigger . '_percent'] = sprintf(__('Scroll %s%%', 'wp-sdtrk'), esc_html($scroll_trigger));
}

//add time-triggers to available events
foreach ($time_triggers as $time_trigger) {
    $available_events['time_' . $time_trigger . '_seconds'] = sprintf(__('Time %s seconds', 'wp-sdtrk'), esc_html($time_trigger));
}
?>
<style>
    select.rule-param {
        width: 200px;
    }

    input.rule-value {
        width: 300px;
    }
</style>
<div class="wrap">
    <h1><?php esc_html_e('Linked In Conversion Management', 'wp-sdtrk'); ?></h1>
    <h2><?php esc_html_e('Existing Mappings', 'wp-sdtrk'); ?></h2>

    <div class="wpsdtrk-table-glass">
        <table>
            <thead>
                <tr>
                    <th><?php esc_html_e('Event', 'wp-sdtrk'); ?></th>
                    <th><?php esc_html_e('Conversion ID', 'wp-sdtrk'); ?></th>
                    <th><?php esc_html_e('Rules', 'wp-sdtrk'); ?></th>
                    <th><?php esc_html_e('Action', 'wp-sdtrk'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($mappings)) :
                ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e('No Mappings found', 'wp-sdtrk'); ?></td>
                    </tr>
                    <?php
                else :
                    foreach ($mappings as $mapping) :
                    ?>
                        <tr>
                            <td><?php echo esc_html($mapping->get_event_label()); ?></td>
                            <td><?php echo esc_html($mapping->get_conversion_id()); ?></td>
                            <td>
                                <ul style="margin: 0; padding-left: 1.2em;">
                                    <?php foreach ($mapping->get_rules() as $rule): ?>
                                        <li><strong><?php echo esc_html($rule->get_label()); ?>:</strong> <?php echo esc_html($rule->get_value()); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="button wpsdtrk_edit_mapping_btn"
                                    data-mapping-id="<?php echo esc_attr($mapping->get_id()); ?>"
                                    data-mapping-convid="<?php echo esc_attr($mapping->get_conversion_id()); ?>"
                                    data-event="<?php echo esc_attr($mapping->get_event()); ?>">
                                    <?php esc_html_e('Edit', 'wp-sdtrk'); ?>
                                </button>
                                <button
                                    type="button"
                                    class="button button-primary delete wpsdtrk_delete_linkedin_mapping_btn"
                                    data-mapping-id="<?php echo esc_attr($mapping->get_id()); ?>">
                                    ✕ <?php esc_html_e('Delete', 'wp-sdtrk'); ?>
                                </button>
                            </td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>

    <hr style="margin: 40px 0;">

    <h2><?php esc_html_e('Add New Mapping', 'wp-sdtrk'); ?></h2>

    <form method="post" action="" id="linkedin-mapping-form">
        <input type="hidden" name="wp_sdtrk_form_action" value="create_linkedin_mapping">
        <?php wp_nonce_field('wp_sdtrk_create_linkedin_mapping', 'wp_sdtrk_nonce'); ?>
        <input type="hidden" id="edit-mapping-id" name="edit-mapping-id" value="">

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sdtrk_new_mapping_event"><?php esc_html_e('Event', 'wp-sdtrk'); ?></label>
                </th>
                <td>
                    <select
                        name="sdtrk_new_mapping_event"
                        class="regular-text">
                        <?php
                        foreach ($available_events as $event_key => $event_label) :
                        ?>
                            <option value="<?php echo esc_attr($event_key); ?>">
                                <?php echo esc_html($event_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sdtrk_new_mapping_convid"><?php esc_html_e('LinkedIn Conversion ID', 'wp-sdtrk'); ?></label></th>
                <td><input name="sdtrk_new_mapping_convid" type="text" id="sdtrk_new_mapping_convid" class="regular-text"></td>
            </tr>
        </table>

        <h3><?php esc_html_e('Attribute Rules (AND Logic)', 'wp-sdtrk'); ?></h3>
        <p class="description" style="margin-bottom: 15px;">
            <?php esc_html_e('All rules must match for the event to trigger. Leave value empty to match any value.', 'wp-sdtrk'); ?>
        </p>

        <div id="rules-container" style="margin-bottom: 15px;">
            <!-- Rules werden hier dynamisch eingefügt -->
        </div>

        <button type="button" class="button button-secondary" id="add-rule-btn" style="margin-bottom: 20px;">
            + <?php esc_html_e('Add Rule', 'wp-sdtrk'); ?>
        </button>

        <p>
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Save Mapping', 'wp-sdtrk'); ?>
            </button>
        </p>
    </form>
</div>
<div id="wpsdtrk-modal-edit-mapping" class="wpsdtrk-modal hidden" data-modal-id="edit-mapping">
    <div class="wpsdtrk-modal-overlay"></div>
    <div class="wpsdtrk-modal-content">
        <h2 id="wpsdtrk-edit-product-title" style="margin-bottom: 10px;"></h2>
        <form id="wpsdtrk-edit-mapping-form" method="post" action="">
            <input type="hidden" name="wp_sdtrk_form_action" value="update_linkedin_mapping">
            <input type="hidden" name="mapping_id" id="wpsdtrk-edit-mapping-id" value="">
            <?php wp_nonce_field('wp_sdtrk_update_linkedin_mapping', 'wp_sdtrk_nonce'); ?>
            <h4><?php esc_html_e('Event', 'wp-sdtrk'); ?></h4>
            <select
                name="sdtrk_edit_mapping_event"
                class="regular-text">
                <?php
                foreach ($available_events as $event_key => $event_label) :
                ?>
                    <option value="<?php echo esc_attr($event_key); ?>">
                        <?php echo esc_html($event_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <h4><?php esc_html_e('Conversion ID', 'wp-sdtrk'); ?></h4>
            <input name="sdtrk_edit_mapping_convid" type="text" id="sdtrk_edit_mapping_convid" class="regular-text" style="margin-bottom: 20px;">

            <h4><?php esc_html_e('Attribute Rules (AND Logic)', 'wp-sdtrk'); ?></h4>
            <p class="description" style="margin-bottom: 15px;">
                <?php esc_html_e('All rules must match for the event to trigger. Leave value empty to match any value.', 'wp-sdtrk'); ?>
            </p>

            <div id="edit-rules-container" style="margin-bottom: 15px;">
                <!-- Rules werden hier dynamisch eingefügt -->
            </div>

            <button type="button" class="button button-secondary" id="add-edit-rule-btn" style="margin-bottom: 20px;">
                + <?php esc_html_e('Add Rule', 'wp-sdtrk'); ?>
            </button>

            <div class="wpsdtrk-modal-actions" style="margin-top: 20px;">
                <button type="submit" class="button button-primary"><?php esc_html_e('Save', 'wp-sdtrk'); ?></button>
                <button type="button" class="button wpsdtrk-modal-close"><?php esc_html_e('Cancel', 'wp-sdtrk'); ?></button>
            </div>
        </form>
    </div>
</div>