<?php

/**
 * Fired during plugin deactivation
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Wp_Sdtrk
 * @subpackage Wp_Sdtrk/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Wp_Sdtrk
 * @subpackage Wp_Sdtrk/includes
 * @author     Your Name <email@example.com>
 */
class Wp_Sdtrk_Deactivator
{

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate()
	{

		global $wpdb;
		$p = $wpdb->prefix;

		// 1) Alle alten FOREIGN KEYS entfernen
		$map = [];
		foreach ($map as $table => $keys) {
			foreach ($keys as $fk) {
				$wpdb->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
			}
		}

		// 2) Cron‐Event löschen
		WP_SDTRK_Cron::unregister_cronjobs();
	}
}
