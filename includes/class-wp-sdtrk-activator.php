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
		self::create_db_local_tracking();

		// 2) FOREIGN KEY Constraints einmalig hinzufügen

		// 3) Rewrite-Regeln flushen & Cron schedule
		flush_rewrite_rules();
		WP_SDTRK_Cron::register_cronjobs();
	}

	private static function create_db_local_tracking(): void
	{
		global $wpdb;
		$t = $wpdb->prefix . 'sdtrk_hits';
		$c = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE `{$t}` (
            `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `date`            	  DATETIME          NOT NULL,
            `event_name`          VARCHAR(255)      NOT NULL,
            `event_data`          LONGTEXT          NOT NULL,
            `event_hash`          CHAR(32)          NOT NULL,
            `synced`              BOOLEAN           NOT NULL,
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB {$c};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}
}
