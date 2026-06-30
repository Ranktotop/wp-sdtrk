<?php
// templates/wp-sdtrk-admin-feed-manage.php
//
// Hidden admin page to control which published WooCommerce products are
// excluded from the product feed. The table body, counter and pagination are
// filled by admin/js/wp-sdtrk-admin-feed-manage.js via the list_feed_products /
// save_feed_exclusion AJAX endpoints. See spec/07-woocommerce/feed-management.md.

if (!defined('ABSPATH') || !is_user_logged_in() || !current_user_can('manage_options')) {
    wp_redirect(home_url());
    exit;
}

$feed_available = class_exists('Wp_Sdtrk_WC_Feed') && Wp_Sdtrk_WC_Feed::is_enabled();
?>
<div class="wrap wpsdtrk-feed-manage">
    <h1><?php esc_html_e('Manage Product Feed', 'wp-sdtrk'); ?></h1>

    <?php if (!$feed_available) : ?>
        <p><?php esc_html_e('The product feed is not enabled. Enable the WooCommerce integration and the product feed in the plugin settings first.', 'wp-sdtrk'); ?></p>
    <?php else : ?>
        <p class="description">
            <?php esc_html_e('All published products are in the feed by default. Switch a product to "Excluded" to keep it out. Changes take effect on the next feed refresh.', 'wp-sdtrk'); ?>
        </p>

        <p id="wpsdtrk-feed-counter" class="wpsdtrk-feed-counter" aria-live="polite"></p>

        <div class="wpsdtrk-feed-toolbar" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:16px 0;">
            <input
                type="text"
                id="wpsdtrk-feed-search"
                class="regular-text"
                placeholder="<?php esc_attr_e('Search products…', 'wp-sdtrk'); ?>"
                autocomplete="off">

            <select id="wpsdtrk-feed-status-filter">
                <option value="all"><?php esc_html_e('Status: All', 'wp-sdtrk'); ?></option>
                <option value="in_feed"><?php esc_html_e('Status: In feed', 'wp-sdtrk'); ?></option>
                <option value="excluded"><?php esc_html_e('Status: Excluded', 'wp-sdtrk'); ?></option>
            </select>

            <span class="wpsdtrk-feed-bulk" style="display:inline-flex;gap:8px;">
                <button type="button" class="button button-secondary" id="wpsdtrk-feed-bulk-exclude" disabled>
                    <?php esc_html_e('Exclude selected', 'wp-sdtrk'); ?>
                </button>
                <button type="button" class="button button-secondary" id="wpsdtrk-feed-bulk-include" disabled>
                    <?php esc_html_e('Include selected', 'wp-sdtrk'); ?>
                </button>
            </span>
        </div>

        <div class="wpsdtrk-table-glass">
            <table>
                <thead>
                    <tr>
                        <th style="width:32px;">
                            <label class="screen-reader-text" for="wpsdtrk-feed-select-all"><?php esc_html_e('Select all', 'wp-sdtrk'); ?></label>
                            <input type="checkbox" id="wpsdtrk-feed-select-all">
                        </th>
                        <th style="width:48px;"><?php esc_html_e('Image', 'wp-sdtrk'); ?></th>
                        <th><?php esc_html_e('Product', 'wp-sdtrk'); ?></th>
                        <th><?php esc_html_e('SKU', 'wp-sdtrk'); ?></th>
                        <th><?php esc_html_e('Price', 'wp-sdtrk'); ?></th>
                        <th style="width:160px;"><?php esc_html_e('Status', 'wp-sdtrk'); ?></th>
                    </tr>
                </thead>
                <tbody id="wpsdtrk-feed-rows">
                    <tr><td colspan="6"><?php esc_html_e('Loading…', 'wp-sdtrk'); ?></td></tr>
                </tbody>
            </table>
        </div>

        <div class="wpsdtrk-feed-pagination" id="wpsdtrk-feed-pagination" style="display:flex;gap:12px;align-items:center;margin-top:16px;">
            <button type="button" class="button" id="wpsdtrk-feed-prev" disabled><?php esc_html_e('Previous', 'wp-sdtrk'); ?></button>
            <span id="wpsdtrk-feed-page-info" aria-live="polite"></span>
            <button type="button" class="button" id="wpsdtrk-feed-next" disabled><?php esc_html_e('Next', 'wp-sdtrk'); ?></button>
        </div>
    <?php endif; ?>
</div>
