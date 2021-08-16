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

        // Register Script for JS Event-Data-Class
        wp_register_script("wp_sdtrk_event", plugins_url("js/wp-sdtrk-event.js", __FILE__), array(
            'jquery'
        ), "1.0", false);
        wp_enqueue_script('wp_sdtrk_event');

        // Register Script for collecting Event-Data in Browser
        wp_enqueue_script($this->wp_sdtrk, plugin_dir_url(__FILE__) . 'js/wp-sdtrk-public.js', array(
            'jquery'
        ), $this->version, false);
        $this->localize_eventData();

        // Register Script for Facebook-Tracking
        wp_register_script("wp_sdtrk-fb", plugins_url("js/wp-sdtrk-fb.js", __FILE__), array(
            'jquery'
        ), "1.0", false);
        $this->localize_fbData();

        // Register Script for Google-Tracking
        wp_register_script("wp_sdtrk-ga", plugins_url("js/wp-sdtrk-ga.js", __FILE__), array(
            'jquery'
        ), "1.0", false);
        $this->localize_gaData();
    }

    public function licensecheck()
    {
        $licenseKey = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", ''), "licensekey_" . WP_SDTRK_LICENSE_TYPE_PRO);
        Wp_Sdtrk_License::wp_sdtrk_license_call($licenseKey, WP_SDTRK_LICENSE_TYPE_PRO, 'check');
    }

    /**
     * Collect all Event-Data and pass them to JS
     */
    private function localize_eventData()
    {
        // Init
        $localizedData = array();
        $localizedData['ajax_url'] = admin_url('admin-ajax.php');
        $localizedData['_nonce'] = wp_create_nonce('security_wp-sdtrk');

        global $post;
        $postId = ($post && $post->ID) ? $post->ID : false;
        $prodId = get_post_meta($postId, 'productid', true);
        $prodId = (! $prodId) ? "" : $prodId;

        // Get the brandName from Settings
        $brandName = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "brandname");
        $brandName = ($brandName && ! empty(trim($brandName))) ? $brandName : get_bloginfo('name');

        // ID
        $ga_id = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_measurement_id");
        $ga_id = ($ga_id && ! empty(trim($ga_id))) ? $ga_id : false;

        // GA Debug Mode
        $ga_debug = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_debug"), "yes") == 0) ? true : false;
        $ga_debug = ($ga_debug && ! empty(trim($ga_debug))) ? $ga_debug : false;

        // Google: Track Browser Cookie Service
        $ga_trkBrowserCookieService = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_browser_cookie_service");
        $ga_trkBrowserCookieService = ($ga_trkBrowserCookieService && ! empty(trim($ga_trkBrowserCookieService))) ? $ga_trkBrowserCookieService : false;

        // Google: Track Browser Cookie ID
        $ga_trkBrowserCookieId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_browser_cookie_id");
        $ga_trkBrowserCookieId = ($ga_trkBrowserCookieId && ! empty(trim($ga_trkBrowserCookieId))) ? $ga_trkBrowserCookieId : false;

        $localizedData['prodId'] = $prodId;
        $localizedData['rootDomain'] = Wp_Sdtrk_Helper::wp_sdtrk_getRootDomain();
        $localizedData['brandName'] = $brandName;
        $localizedData['ga_id'] = $ga_id;
        $localizedData['ga_debug'] = $ga_debug;
        $localizedData['c_ga_b_i'] = $ga_trkBrowserCookieId;
        $localizedData['c_ga_b_s'] = $ga_trkBrowserCookieService;
        $localizedData['addr'] = Wp_Sdtrk_Helper::wp_sdtrk_getClientIp();
        $localizedData['agent'] = $_SERVER['HTTP_USER_AGENT'];
        $localizedData['source'] = Wp_Sdtrk_Helper::wp_sdtrk_getCurrentURL();

        wp_localize_script($this->wp_sdtrk, 'wp_sdtrk', $localizedData);
    }

    /**
     * Collect all FB-Data and pass them to JS
     */
    private function localize_fbData()
    {
        // Init
        $localizedData = array();

        // Pixel ID
        $fb_pixelId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_pixelid");
        $fb_pixelId = ($fb_pixelId && ! empty(trim($fb_pixelId))) ? $fb_pixelId : false;
        
        // Track Browser Enabled
        $trkBrowser = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_browser"), "yes") == 0) ? true : false;

        // Facebook: Track Browser Cookie Service
        $fb_trkBrowserCookieService = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_browser_cookie_service");
        $fb_trkBrowserCookieService = ($fb_trkBrowserCookieService && ! empty(trim($fb_trkBrowserCookieService))) ? $fb_trkBrowserCookieService : false;

        // Facebook: Track Browser Cookie ID
        $fb_trkBrowserCookieId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_browser_cookie_id");
        $fb_trkBrowserCookieId = ($fb_trkBrowserCookieId && ! empty(trim($fb_trkBrowserCookieId))) ? $fb_trkBrowserCookieId : false;

        // Track Server Enabled
        $trkServer = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server"), "yes") == 0) ? true : false;
        
        // Facebook: Track Server Cookie Service
        $fb_trkServerCookieService = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server_cookie_service");
        $fb_trkServerCookieService = ($fb_trkServerCookieService && ! empty(trim($fb_trkServerCookieService))) ? $fb_trkServerCookieService : false;

        // Facebook: Track Server Cookie ID
        $fb_trkServerCookieId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server_cookie_id");
        $fb_trkServerCookieId = ($fb_trkServerCookieId && ! empty(trim($fb_trkServerCookieId))) ? $fb_trkServerCookieId : false;
        
        $localizedData['fb_id'] = $fb_pixelId;
        $localizedData['fb_b_e'] = $trkBrowser;
        $localizedData['c_fb_b_i'] = $fb_trkBrowserCookieId;
        $localizedData['c_fb_b_s'] = $fb_trkBrowserCookieService;
        $localizedData['fb_s_e'] = $trkServer;
        $localizedData['c_fb_s_i'] = $fb_trkServerCookieId;
        $localizedData['c_fb_s_s'] = $fb_trkServerCookieService;

        wp_localize_script("wp_sdtrk-fb", 'wp_sdtrk_fb', $localizedData);
        wp_enqueue_script('wp_sdtrk-fb');
    }
    
    /**
     * Collect all GA-Data and pass them to JS
     */
    private function localize_gaData()
    {
        // Init
        $localizedData = array();
        
        // Mess ID
        $messId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_measurement_id");
        $messId = ($messId && ! empty(trim($messId))) ? $messId : false;
        
        // Track Browser Enabled
        $trkBrowser = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_browser"), "yes") == 0) ? true : false;
        
        // Debug Mode
        $debug = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_debug"), "yes") == 0) ? true : false;
        
        // Google: Track Browser Cookie Service
        $trkBrowserCookieService = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_browser_cookie_service");
        $trkBrowserCookieService = ($trkBrowserCookieService && ! empty(trim($trkBrowserCookieService))) ? $trkBrowserCookieService : false;
        
        // Google: Track Browser Cookie ID
        $trkBrowserCookieId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_browser_cookie_id");
        $trkBrowserCookieId = ($trkBrowserCookieId && ! empty(trim($trkBrowserCookieId))) ? $trkBrowserCookieId : false;
                
        $localizedData['ga_id'] = $messId;
        $localizedData['ga_debug'] = $debug;
        $localizedData['ga_b_e'] = $trkBrowser;
        $localizedData['c_ga_b_i'] = $trkBrowserCookieId;
        $localizedData['c_ga_b_s'] = $trkBrowserCookieService;
        
        wp_localize_script("wp_sdtrk-ga", 'wp_sdtrk_ga', $localizedData);
        wp_enqueue_script('wp_sdtrk-ga');
    }

    /**
     * ---------------------------------------------------
     * ----------------- Ajax Functions ------------------
     * ---------------------------------------------------
     */

    /**
     * Ajax generic Callback-Function
     */
    public function handleAjaxCallback()
    {
        /**
         * Do not forget to check your nonce for security!
         *
         * @link https://codex.wordpress.org/Function_Reference/wp_verify_nonce
         */
        if (! wp_verify_nonce($_POST['_nonce'], 'security_wp-sdtrk')) {
            wp_send_json_error();
            die();
        }

        // Check if given function exists
        $functionName = $_POST['func'];
        if (! method_exists($this, $functionName)) {
            wp_send_json_error();
            die();
        }

        $_POST['data'] = (isset($_POST['data'])) ? $_POST['data'] : array();
        $_POST['meta'] = (isset($_POST['meta'])) ? $_POST['meta'] : array();

        // Call function and send back result
        $result = $this->$functionName($_POST['data'], $_POST['meta']);
        die(json_encode($result));
    }

    /**
     * This function is called after Pageload (User-Browser)
     *
     * @param array $data
     * @param array $meta
     * @return array
     */
    public function validateTracker($data, $meta)
    {
        if (isset($meta['event']) && isset($meta['type'])) {
            $event = new Wp_Sdtrk_Tracker_Event($meta['event']);
            switch ($meta['type']) {
                // Facebook CAPI
                case 'fb':
                    $fbp = (isset($meta['fbp'])) ? $meta['fbp'] : "";
                    $fbc = (isset($meta['fbc'])) ? $meta['fbc'] : "";
                    $fbTracker = new Wp_Sdtrk_Tracker_Fb();
                    $fbTracker->fireTracking_Server($event,$fbp,$fbc);
                    return array(
                        'state' => true,
                        'data' => $event->getEventAsArray()
                    );
                    break;
            }
        }
        return array(
            'state' => false
        );
    }
}
