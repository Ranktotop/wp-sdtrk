<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * Removes every persistent artifact the plugin creates so a clean uninstall
 * leaves nothing behind:
 *   - DB table {prefix}sdtrk_linkedin
 *   - options wp_sdtrk_options (+ Redux transients), wp_sdtrk_feed_token,
 *     wp_sdtrk_feed_cache
 *   - per-post metabox meta (key wp_sdtrk_options)
 *   - the daily product-feed cron event
 *
 * Multisite-aware: runs the cleanup once per site in the network.
 *
 * @package Wp_Sdtrk
 */

// If uninstall not called from WordPress, then exit.
if (! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

/**
 * Remove all plugin data for the current site.
 */
function wp_sdtrk_uninstall_cleanup(): void
{
	global $wpdb;

	// 1) Own DB table
	$table = $wpdb->prefix . 'sdtrk_linkedin';
	$wpdb->query("DROP TABLE IF EXISTS `{$table}`");

	// 2) Options (settings panel, Redux transients cache, feed token + cache)
	delete_option('wp_sdtrk_options');
	delete_option('wp_sdtrk_options-transients');
	delete_option('wp_sdtrk_feed_token');
	delete_option('wp_sdtrk_feed_cache');

	// 3) Per-post metabox meta (Redux stores it under the opt_name key)
	delete_post_meta_by_key('wp_sdtrk_options');

	// 4) Scheduled product-feed cron event
	wp_clear_scheduled_hook('wp_sdtrk_cron_generate_feed');
}

if (is_multisite()) {
	$site_ids = get_sites(['fields' => 'ids']);
	foreach ($site_ids as $site_id) {
		switch_to_blog($site_id);
		wp_sdtrk_uninstall_cleanup();
		restore_current_blog();
	}
} else {
	wp_sdtrk_uninstall_cleanup();
}
