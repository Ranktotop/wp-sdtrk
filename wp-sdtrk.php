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
 * Version:           1.7.6
 * Author:            Marc Meese
 * Author URI:        https://marcmeese.de/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-sdtrk
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('WP_SDTRK_VERSION', '1.7.6');

//  Composer‑Autoloader laden (für Carbon Fields)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// ——— GLOBALER LOGGER ———
if (! function_exists('sdtrk_log')) {
    /**
     * Einfacher Logger für WP_SDTRK.
     *
     * @param mixed  $message String, Array oder Objekt.
     * @param string $level   Optional. Log-Level (info, warning, error).
     * @param bool   $ignore  Optional. Wenn true, wird der Log-Eintrag auch bei aktiviertem WP_DEBUG nicht geschrieben.
     */
    function sdtrk_log($message, string $level = 'info', bool $ignore = false): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && ! $ignore) {
            // 1) Zeitstempel holen (WP-Lokalzeit)
            $timestamp = date_i18n('Y-m-d H:i:s');
            // 2) Prefix mit Datum, Plugin-Tag und Level
            $prefix = sprintf('[%s] [WP_SDTRK][%s] ', $timestamp, strtoupper($level));

            // 3) Loggen
            if (is_array($message) || is_object($message)) {
                error_log($prefix . print_r($message, true));
            } else {
                error_log($prefix . $message);
            }
        }
    }
}
// ————————————————

// Enable GitHub-based plugin updates using plugin-update-checker
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/Ranktotop/wp-sdtrk/',
    __FILE__,
    'wp-sdtrk'
);

// Verwende GitHub Releases (nicht den Branch-Zip)
$updateChecker->getVcsApi()->enableReleaseAssets();

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wp-sdtrk-activator.php
 */
function activate_wp_sdtrk()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-wp-sdtrk-activator.php';
    Wp_Sdtrk_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wp-sdtrk-deactivator.php
 */
function deactivate_wp_sdtrk()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-wp-sdtrk-deactivator.php';
    Wp_Sdtrk_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_wp_sdtrk');
register_deactivation_hook(__FILE__, 'deactivate_wp_sdtrk');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-wp-sdtrk.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wp_sdtrk()
{

    $plugin = new Wp_Sdtrk();
    $plugin->run();
}
run_wp_sdtrk();
