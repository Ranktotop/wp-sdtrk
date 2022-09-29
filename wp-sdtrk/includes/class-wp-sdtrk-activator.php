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
class Wp_Sdtrk_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

	    // schedule cron job
	    if ( ! wp_next_scheduled( 'wp_sdtrk_licensecheck_cron' ) ) {
	        wp_schedule_event( time(), 'hourly', 'wp_sdtrk_licensecheck_cron' );
	    }	    
	    
	    //create database for local tracking
	    self::create_localTrackingDb();
	    
	    $timezone = 'Europe/Berlin';
	    $gsynctime = intval(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_gsync_crontime"));
	    $gsynctimestamp = strtotime($gsynctime.':00'.' '.$timezone);
	    $csvsynctime = intval(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_csv_crontime"));
	    $csvsynctimestamp = strtotime($csvsynctime.':00'.' '.$timezone);
	    
	    // schedule gsync cron job
	    if ( ! wp_next_scheduled( 'wp_sdtrk_gsync_cron' ) ) {
	        wp_schedule_event($gsynctimestamp, 'daily', 'wp_sdtrk_gsync_cron' );
	    }
	    
	    // schedule gsync cron job
	    if ( ! wp_next_scheduled( 'wp_sdtrk_csvsync_cron' ) ) {
	        wp_schedule_event($csvsynctimestamp, 'daily', 'wp_sdtrk_csvsync_cron' );
	    }
	}
	
	/**
	 * Create a database for local tracking
	 */
	public static function create_localTrackingDb() {
	    
	    global $wpdb;
	    $table_name = $wpdb->prefix . "wpsdtrk_hits";
	    $wp_sdtrk_db_version = get_option( 'wp-sdtrk_db_version', '1.0' );
	    
	    if( $wpdb->get_var( "show tables like '{$table_name}'" ) != $table_name ||
	    version_compare( $wp_sdtrk_db_version, '1.0' ) < 0 ) {
	        
	        $charset_collate = $wpdb->get_charset_collate();
	        
	        $sql[] = "CREATE TABLE " . $table_name." (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            date bigint(10) NOT NULL,
            eventName tinytext NOT NULL,            
            eventParams text,
            gsync BOOLEAN NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
	        
	        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	        
	        /**
	         * It seems IF NOT EXISTS isn't needed if you're using dbDelta - if the table already exists it'll
	         * compare the schema and update it instead of overwriting the whole table.
	         *
	         * @link https://code.tutsplus.com/tutorials/custom-database-tables-maintaining-the-database--wp-28455
	         */
	        dbDelta( $sql );
	        
	        add_option( 'wp-sdtrk_db_version', $wp_sdtrk_db_version );
	        
	    }
	    
	}

}
