<?php

/**
 * Fired during plugin activation
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Wp_Sdtrk
 * @subpackage Wp_Sdtrk/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Wp_Sdtrk
 * @subpackage Wp_Sdtrk/includes
 * @author     Your Name <email@example.com>
 */
class Wp_Sdtrk_Activator
{

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate()
	{

		// 1) Tabellen (ohne FK) anlegen
		self::create_db_linkedin_mapping();

		// 2) FOREIGN KEY Constraints einmalig hinzufügen

		// 3) Rewrite-Regeln flushen & Cron schedule
		flush_rewrite_rules();
		WP_SDTRK_Cron::register_cronjobs();
	}

	private static function create_db_linkedin_mapping(): void
	{
		global $wpdb;
		$t = $wpdb->prefix . 'sdtrk_linkedin';
		$c = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE `{$t}` (
        `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `event`               VARCHAR(255)      NOT NULL,
        `convid`              VARCHAR(255)      NOT NULL,
        `rules`               LONGTEXT          NOT NULL,
        PRIMARY KEY  (`id`),
        UNIQUE KEY `idx_event_convid` (`event`, `convid`)
    ) ENGINE=InnoDB {$c};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}
}
