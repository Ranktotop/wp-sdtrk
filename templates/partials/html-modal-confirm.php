<?php
// templates/partials/html-modal-confirm.php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="wpsdtrk-confirm-modal" class="wpsdtrk-modal hidden">
    <div class="wpsdtrk-modal-overlay"></div>
    <div class="wpsdtrk-modal-content">
        <h2><?php esc_html_e('Are you sure?', 'wp-sdtrk'); ?></h2>
        <p></p>
        <div class="wpsdtrk-modal-actions">
            <button class="button-secondary wpsdtrk-cancel-btn"><?php esc_html_e('Cancel', 'wp-sdtrk'); ?></button>
            <button class="button-primary wpsdtrk-confirm-btn"><?php esc_html_e('Confirm', 'wp-sdtrk'); ?></button>
        </div>
    </div>
</div>