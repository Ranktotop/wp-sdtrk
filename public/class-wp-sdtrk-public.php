<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Wp_Sdtrk
 * @subpackage Wp_Sdtrk/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package Wp_Sdtrk
 * @subpackage Wp_Sdtrk/public
 * @author Your Name <email@example.com>
 */
class Wp_Sdtrk_Public
{

    /**
     * The ID of this plugin.
     *
     * @since 1.0.0
     * @access private
     * @var string $wp_sdtrk The ID of this plugin.
     */
    private $wp_sdtrk;

    /**
     * The version of this plugin.
     *
     * @since 1.0.0
     * @access private
     * @var string $version The current version of this plugin.
     */
    private $version;

    /**
     * ***********************************************************
     * ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE
     *
     * @tutorial access_plugin_admin_public_methodes_from_inside.php
     */
    /**
     * Store plugin main class to allow public access.
     *
     * @since 20180622
     * @var object The main class.
     */
    public $main;

    // END ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE

    /**
     * Initialize the class and set its properties.
     *
     * @since 1.0.0
     * @param string $wp_sdtrk
     *            The name of the plugin.
     * @param string $version
     *            The version of this plugin.
     */
    // public function __construct( $wp_sdtrk, $version ) {

    // $this->wp_sdtrk = $wp_sdtrk;
    // $this->version = $version;

    // }

    /**
     * ***********************************************************
     * ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE
     *
     * @tutorial access_plugin_admin_public_methodes_from_inside.php
     */
    /**
     * Initialize the class and set its properties.
     *
     * @since 1.0.0
     * @param string $wp_sdtrk
     *            The name of this plugin.
     * @param string $version
     *            The version of this plugin.
     */
    public function __construct($wp_sdtrk, $version, $plugin_main)
    {
        $this->wp_sdtrk = $wp_sdtrk;
        $this->version = $version;
        $this->main = $plugin_main;
    }

    // END ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since 1.0.0
     */
    public function enqueue_styles()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Wp_Sdtrk_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Wp_Sdtrk_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */
        wp_enqueue_style($this->wp_sdtrk, plugin_dir_url(__FILE__) . 'css/wp-sdtrk-public.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since 1.0.0
     */
    public function enqueue_scripts()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Wp_Sdtrk_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Wp_Sdtrk_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */
        wp_enqueue_script($this->wp_sdtrk, plugin_dir_url(__FILE__) . 'js/wp-sdtrk-public.js', array(
            'jquery'
        ), $this->version, false);
        wp_register_script("wp_sdtrk-fb", plugins_url("js/wp-sdtrk-fb.js", __FILE__), array(
            'jquery'
        ), "1.0", false);
    }

    public function licensecheck()
    {
        $licenseKey = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", ''), "licensekey_" . WP_SDTRK_LICENSE_TYPE_PRO);
        Wp_Sdtrk_License::wp_sdtrk_license_call($licenseKey, WP_SDTRK_LICENSE_TYPE_PRO, 'check');
    }

    /**
     * Renders the Tracking-Codes
     */
    public function renderTracking()
    {
        // Frontend only
        if (is_admin()) {
            return;
        }
        $this->renderFacebookTracking();
    }

    /**
     * Renders the Facebook Tracking-Code
     */
    public function renderFacebookTracking()
    {
        // Get the Data
        $fb_pixelId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_pixelid");
        $fbBrwsTracking = false;
        $fbSrvTracking = false;
        $fbSrvToken = "";
        $fbSrvDebugMode = false;
        $fbSrvDebugCode = "";
        $fbEventId = substr(str_shuffle(MD5(microtime())), 0, 10);

        // If ID is valid
        if ($fb_pixelId && ! empty(trim($fb_pixelId))) {

            // Get Tracking-Types
            $fbBrwsTracking = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_browser"), "yes") == 0) ? true : false;
            $fbSrvTracking = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server"), "yes") == 0) ? true : false;

            // If server-side-tracking is enabled
            if ($fbSrvTracking) {

                // Get the token
                $fbSrvToken = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server_token");

                // If token is set
                if ($fbSrvToken && ! empty(trim($fbSrvToken))) {
                    // Get Debug Mode
                    $fbSrvDebugMode = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server_debug"), "yes") == 0) ? true : false;

                    // If Debug is enabled
                    if ($fbSrvDebugMode) {

                        // get Debug-Code
                        $fbSrvDebugCode = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server_debug_code");
                    }
                }
            }
        }

        // Browser-Tracking
        if ($fbBrwsTracking) {
            wp_localize_script("wp_sdtrk-fb", 'wp_sdtrk_fb', array(
                'wp_sdtrk_fb_pixelid' => $fb_pixelId,
                'wp_sdtrk_fb_eventid' => $fbEventId
            ));
            wp_enqueue_script('wp_sdtrk-fb');
        }

        // Server-Tracking
        if ($fbSrvTracking && $fbSrvToken) {
            $data = array(
                0 => array(
                    "event_name" => "PageView",
                    "event_time" => date_create()->getTimestamp(),
                    "event_id" => $fbEventId,
                    "event_source_url" => Wp_Sdtrk_Helper::wp_sdtrk_getCurrentURL(),
                    "action_source"=> "website",
                    "user_data" => array(
                        "client_ip_address" => Wp_Sdtrk_Helper::wp_sdtrk_getClientIp(),
                        "client_user_agent" => $_SERVER['HTTP_USER_AGENT'],
                        "fbc" => "",
                        "fbp" => $_COOKIE['_fbp'] ?? ''
                    ),
                    "contents" => array(
                        array(
                            "id" => "product123",
                            "quantity" => 1
                        )
                    ),
                    "custom_data" => array(
                        "currency" => "EUR",
                        "value" => 0,
                        "content_ids" => "[12345]",
                        "content_type" => "product",
                        "content_name" => "custom",
                        "utm_source" => "facebook"
                    ),
                    
                )
            );

            $fields = array(
                "data" => $data,
                "test_event_code" => $fbSrvDebugCode
            );
            
            $payload = json_encode($fields);

            $url = 'https://graph.facebook.com/v11.0/' . $fb_pixelId . '/events?access_token=' . $fbSrvToken;
            Wp_Sdtrk_Helper::wp_sdtrk_httpPost($url, $payload);
        }
    }
}
