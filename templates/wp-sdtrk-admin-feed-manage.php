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
            <?php esc_html_e('Fields with a feed problem (no/too small/too large image, price 0, empty/too long description) are highlighted in red — hover the icon to see why.', 'wp-sdtrk'); ?>
            <?php esc_html_e('The ID column is the product ID sent to the feed as the content ID — it must match the ID your pixel reports to Meta.', 'wp-sdtrk'); ?>
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
                        <th scope="col" style="width:32px;">
                            <label class="screen-reader-text" for="wpsdtrk-feed-select-all"><?php esc_html_e('Select all on this page', 'wp-sdtrk'); ?></label>
                            <input type="checkbox" id="wpsdtrk-feed-select-all">
                        </th>
                        <th scope="col" style="width:48px;"><?php esc_html_e('Image', 'wp-sdtrk'); ?></th>
                        <th scope="col" style="width:180px;"><?php esc_html_e('Product', 'wp-sdtrk'); ?></th>
                        <th scope="col"><?php esc_html_e('Description', 'wp-sdtrk'); ?></th>
                        <th scope="col" style="width:90px;"><?php esc_html_e('Price', 'wp-sdtrk'); ?></th>
                        <th scope="col" style="width:80px;"><?php esc_html_e('ID', 'wp-sdtrk'); ?></th>
                        <th scope="col" style="width:160px;"><?php esc_html_e('Status', 'wp-sdtrk'); ?></th>
                    </tr>
                </thead>
                <tbody id="wpsdtrk-feed-rows">
                    <tr><td colspan="7"><?php esc_html_e('Loading…', 'wp-sdtrk'); ?></td></tr>
                </tbody>
            </table>
        </div>

        <div class="wpsdtrk-feed-pagination" id="wpsdtrk-feed-pagination" style="display:flex;gap:12px;align-items:center;margin-top:16px;">
            <button type="button" class="button" id="wpsdtrk-feed-prev" disabled><?php esc_html_e('Previous', 'wp-sdtrk'); ?></button>
            <span id="wpsdtrk-feed-page-info" aria-live="polite"></span>
            <button type="button" class="button" id="wpsdtrk-feed-next" disabled><?php esc_html_e('Next', 'wp-sdtrk'); ?></button>
        </div>

        <details class="wpsdtrk-gpc-panel" id="wpsdtrk-gpc-panel" style="margin-top:28px;">
            <summary style="cursor:pointer;font-size:14px;font-weight:600;">
                <?php esc_html_e('Google product categories', 'wp-sdtrk'); ?>
            </summary>
            <p class="description" style="margin-top:8px;">
                <?php esc_html_e('Google Merchant Center wants a product category (Meta does not). WooCommerce has no field for it, so map each WooCommerce category to a Google category once — every product in it (and its sub-categories) inherits the value. Categories without a mapping yet are highlighted in red below.', 'wp-sdtrk'); ?>
            </p>

            <div class="wpsdtrk-table-glass" style="margin-top:12px;">
                <table>
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('WooCommerce category', 'wp-sdtrk'); ?></th>
                            <th scope="col" style="width:90px;"><?php esc_html_e('Products', 'wp-sdtrk'); ?></th>
                            <th scope="col"><?php esc_html_e('Google product category', 'wp-sdtrk'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wpsdtrk-gpc-rows">
                        <tr><td colspan="3"><?php esc_html_e('Loading…', 'wp-sdtrk'); ?></td></tr>
                    </tbody>
                </table>
            </div>

            <?php // JS populates this datalist from the bundled Google taxonomy the first time the panel is opened. ?>
            <datalist id="wpsdtrk-gpc-taxonomy"></datalist>
        </details>
    <?php endif; ?>
</div>
