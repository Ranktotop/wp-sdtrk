<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://marcmeese.de/
 * @since             1.0.0
 * @package           Wp_Sdtrk
 *
 * @wordpress-plugin
 * Plugin Name:       Smart Server Side Tracking Plugin
 * Plugin URI:        https://marcmeese.de/
 * Description:       Adds server side tracking to non-woocommerce wordpress-sites
 * Version:           1.2.4
 * Author:            Marc Meese
 * Author URI:        https://marcmeese.de/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-sdtrk
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'WP_SDTRK_WP_SDTRK', 'wp-sdtrk' );

// This is the secret key for API authentication. You configured it in the settings menu of the license manager plugin.
define('WP_SDTRK_LICENSE_SECRET', '5e8c9e88782669.26665256');
define('WP_SDTRK_LICENSE_SERVER', 'https://license.rank-to-top.de');
define('WP_SDTRK_LICENSE_TYPE_PRO', 'wp_sdtrk_pro');

/**
 * Store plugin base dir, for easier access later from other classes.
 * (eg. Include, pubic or admin)
 */
define( 'WP_SDTRK_BASE_DIR', plugin_dir_path( __FILE__ ) );

/********************************************
 * RUN CODE ON PLUGIN UPGRADE AND ADMIN NOTICE
 *
 * @tutorial run_code_on_plugin_upgrade_and_admin_notice.php
 */
define( 'WP_SDTRK_BASE_NAME', plugin_basename( __FILE__ ) );
// RUN CODE ON PLUGIN UPGRADE AND ADMIN NOTICE

/**
 * Initialize custom templater
 */
if( ! class_exists( 'Exopite_Template' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/libraries/class-exopite-template.php';
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wp-sdtrk-activator.php
 */
function activate_wp_sdtrk() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-sdtrk-activator.php';
    Wp_Sdtrk_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wp-sdtrk-deactivator.php
 */
function deactivate_wp_sdtrk() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-sdtrk-deactivator.php';
    Wp_Sdtrk_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wp_sdtrk' );
register_deactivation_hook( __FILE__, 'deactivate_wp_sdtrk' );

/*****************************************
 * CUSTOM UPDATER FOR PLUGIN
 * @tutorial custom_updater_for_plugin.php
 */
if ( is_admin() ) {
    
    /**
     * A custom update checker for WordPress plugins.
     *
     * How to use:
     * - Copy vendor/plugin-update-checker to your plugin OR
     *   Download https://github.com/YahnisElsts/plugin-update-checker to the folder
     * - Create a subdomain or a folder for the update server eg. https://updates.example.net
     *   Download https://github.com/YahnisElsts/wp-update-server and copy to the subdomain or folder
     * - Add plguin zip to the 'packages' folder
     *
     * Useful if you don't want to host your project
     * in the official WP repository, but would still like it to support automatic updates.
     * Despite the name, it also works with themes.
     *
     * @link http://w-shadow.com/blog/2011/06/02/automatic-updates-for-commercial-themes/
     * @link https://github.com/YahnisElsts/plugin-update-checker
     * @link https://github.com/YahnisElsts/wp-update-server
     */
    if( ! class_exists( 'Puc_v4_Factory' ) ) {
        
        require_once join( DIRECTORY_SEPARATOR, array( WP_SDTRK_BASE_DIR, 'vendor', 'plugin-update-checker', 'plugin-update-checker.php' ) );
        
    }
    
    $MyUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
        // CHANGE THIS FOR YOUR UPDATE URL
        'https://update.rank-to-top.de/?action=get_metadata&slug=' . WP_SDTRK_WP_SDTRK, //Metadata URL.
        __FILE__, //Full path to the main plugin file.
        WP_SDTRK_WP_SDTRK //Plugin slug. Usually it's the same as the name of the directory.
        );
    
    /**
     * add plugin upgrade notification
     * https://andidittrich.de/2015/05/howto-upgrade-notice-for-wordpress-plugins.html
     */
    add_action( 'in_plugin_update_message-' . WP_SDTRK_WP_SDTRK . '/' . WP_SDTRK_WP_SDTRK .'.php', 'wp_sdtrk_show_upgrade_notification', 10, 2 );
    function wp_sdtrk_show_upgrade_notification( $current_plugin_metadata, $new_plugin_metadata ) {
        
        /**
         * Check "upgrade_notice" in readme.txt.
         *
         * Eg.:
         * == Upgrade Notice ==
         * = 20180624 = <- new version
         * Notice		<- message
         *
         */
        if ( isset( $new_plugin_metadata->upgrade_notice ) && strlen( trim( $new_plugin_metadata->upgrade_notice ) ) > 0 ) {
            
            // Display "upgrade_notice".
            echo sprintf( '<span style="background-color:#d54e21;padding:10px;color:#f9f9f9;margin-top:10px;display:block;"><strong>%1$s: </strong>%2$s</span>', esc_attr( 'Important Upgrade Notice', 'exopite-multifilter' ), esc_html( rtrim( $new_plugin_metadata->upgrade_notice ) ) );
            
        }
    }
    
    
}
// END CUSTOM UPDATER FOR PLUGIN

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wp-sdtrk.php';

/********************************************
 * THIS ALLOW YOU TO ACCESS YOUR PLUGIN CLASS
 * eg. in your template/outside of the plugin.
 *
 * Of course you do not need to use a global,
 * you could wrap it in singleton too,
 * or you can store it in a static class,
 * etc...
 *
 * @tutorial access_plugin_and_its_methodes_later_from_outside_of_plugin.php
 */
global $pbt_prefix_wp_sdtrk;
$pbt_prefix_wp_sdtrk = new Wp_Sdtrk();
$pbt_prefix_wp_sdtrk->run();
// END THIS ALLOW YOU TO ACCESS YOUR PLUGIN CLASS
