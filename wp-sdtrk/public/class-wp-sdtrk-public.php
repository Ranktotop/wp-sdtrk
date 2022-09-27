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

        // Load minifed JS Versions
        $loadMinified = false;
        $minifySwitch = ($loadMinified) ? ".min" : "";

        $this->localize_decrypter($minifySwitch);
        $this->localize_localData($minifySwitch);
        $this->localize_fbData($minifySwitch);
        $this->localize_ttData($minifySwitch);
        $this->localize_linData($minifySwitch);
        $this->localize_gaData($minifySwitch);
        $this->localize_flData($minifySwitch);
        $this->localize_mtcData($minifySwitch);
        $this->localize_eventData($minifySwitch);
    }

    public function licensecheck()
    {
        $licenseKey = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", ''), "licensekey_" . WP_SDTRK_LICENSE_TYPE_PRO);
        Wp_Sdtrk_License::wp_sdtrk_license_call($licenseKey, WP_SDTRK_LICENSE_TYPE_PRO, 'check');
    }
    
    /**
     * Gsync
     */
    public function local_gsync()
    {
        $result = true;
        
        //Get Data
        $trkServer = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server"), "yes") == 0) ? true : false;
        $syncGsheet = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_gsync"), "yes") == 0) ? true : false;
        $cred = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_gsync_cred");
        $localGauth_authenticated = (get_option('wp-sdtrk-gauth-token')===false) ? false : true;
        $sheetId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_gsync_sheetId");
        $tableName = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_gsync_tableName");
        $debug = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_debug"), "yes") == 0) ? true : false;
        
        //If data are set
        if($trkServer && $syncGsheet && !empty($cred) && $localGauth_authenticated && !empty($sheetId) && !empty($tableName)){
            $options = array(
                "cred" => $cred,
                "sheetId"=>$sheetId,
                "tableName"=>$tableName,
                "startColumn" => "A",
                "endColumn" => "Z",
                "startRow" => "1",
                "debug" => $debug
            );
            
            $dbHelper = new Wp_Sdtrk_DbHelper();
            $dataToSync = $dbHelper->getRowsForGsync();
            if(sizeof($dataToSync)>0){
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/google/gConnector.php';
                $gConnector = new gConnector($options);
                if($gConnector->isConnected()){
                    $result = $gConnector->pushLocalData($dataToSync);
                }else{
                    $result = false;
                }
            }
        }
        return $result;
    }

    /**
     * Setup Decrypter-Data
     */
    private function localize_decrypter($loadMinified = "")
    {
        // Init
        // Register Script for Facebook-Tracking
        wp_register_script("wp_sdtrk-decrypter", plugins_url("js/wp-sdtrk-decrypter" . $loadMinified . ".js", __FILE__), array(
            'jquery'
        ), "1.0", false);

        $localizedData = array();
        $services = array();

        // Digistore24
        $ds24_decrypt = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ds24_encrypt_data"), "yes") == 0) ? true : false;
        $ds24_decryptKey = ($ds24_decrypt) ? Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ds24_encrypt_data_key") : false;
        $ds24_decryptKey = (! empty($ds24_decryptKey) && $ds24_decryptKey) ? $ds24_decryptKey : false;

        // If valid add to services
        if ($ds24_decryptKey) {
            array_push($services, "ds24");
        }

        $localizedData['services'] = $services;
        wp_localize_script("wp_sdtrk-decrypter", 'wp_sdtrk_decrypter_config', $localizedData);
        wp_enqueue_script('wp_sdtrk-decrypter');
    }

    /**
     * Collect all Event-Data and pass them to JS
     */
    private function localize_eventData($loadMinified = "")
    {
        // Init
        // Register Script for JS Event-Data-Class
        wp_register_script("wp_sdtrk_event", plugins_url("js/wp-sdtrk-event" . $loadMinified . ".js", __FILE__), array(
            'jquery'
        ), "1.0", false);
        wp_enqueue_script('wp_sdtrk_event');

        // Register Script for collecting Event-Data in Browser
        wp_enqueue_script($this->wp_sdtrk, plugin_dir_url(__FILE__) . "js/wp-sdtrk-public" . $loadMinified . ".js", array(
            'jquery'
        ), $this->version, false);

        $localizedData = array();
        $localizedData['ajax_url'] = admin_url('admin-ajax.php');
        $localizedData['_nonce'] = wp_create_nonce('security_wp-sdtrk');

        global $post;
        $postId = ($post && $post->ID) ? $post->ID : false;
        $prodId = get_post_meta($postId, 'productid', true);
        $prodId = (! $prodId) ? "" : $prodId;

        $trkOverwrite = get_post_meta($postId, 'trkoverwrite', true);
        $trkOverwrite = (! $trkOverwrite || $trkOverwrite === "no") ? false : true;

        // Get the brandName from Settings
        $brandName = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "brandname");
        $brandName = ($brandName && ! empty(trim($brandName))) ? $brandName : get_bloginfo('name');

        // Get the timeData from Settings
        $timeTracking = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "trk_time"), "yes") == 0) ? true : false;
        if ($timeTracking) {
            $timeData = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", array()), "trk_time_group");
            $timeData = (! is_array($timeData)) ? array() : $timeData;
            if (sizeof($timeData) > 0) {
                $cleanTimeData = array();
                foreach ($timeData as $time) {
                    if (isset($time["trk_time_group_seconds"]) && ! empty($time["trk_time_group_seconds"])) {
                        array_push($cleanTimeData, floatval($time["trk_time_group_seconds"]));
                    }
                }
                $localizedData['timeTrigger'] = $cleanTimeData;
            }
        }

        // Get Scroll-Data from Settings
        $scrollTracking = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "trk_scrolling"), "yes") == 0) ? true : false;
        if ($scrollTracking) {
            $scrollData = intval(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", array()), "trk_scrolling_depth"));
            if ($scrollData > 0) {
                $localizedData['scrollTrigger'] = $scrollData;
            }
        }

        // Get Klick-Data from Settings
        $clickTracking = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "trk_buttons"), "yes") == 0) ? true : false;
        if ($clickTracking) {
            $localizedData['clickTrigger'] = $clickTracking;
        }

        // Content-ID
        global $post;
        $postId = ($post && $post->ID) ? $post->ID : false;
        $title = $postId ? get_the_title($post) : "";

        $localizedData['prodId'] = $prodId;
        $localizedData['trkow'] = $trkOverwrite;
        $localizedData['pageId'] = $postId;
        $localizedData['pageTitle'] = $title;
        $localizedData['rootDomain'] = Wp_Sdtrk_Helper::wp_sdtrk_getRootDomain();
        $localizedData['currentDomain'] = rtrim(get_site_url(), "/") . '/';
        $localizedData['brandName'] = $brandName;
        $localizedData['addr'] = Wp_Sdtrk_Helper::wp_sdtrk_getClientIp();
        $localizedData['agent'] = $_SERVER['HTTP_USER_AGENT'];
        $localizedData['source'] = Wp_Sdtrk_Helper::wp_sdtrk_getCurrentURL(true);
        $localizedData['referer'] = Wp_Sdtrk_Helper::wp_sdtrk_getCurrentReferer(true);

        wp_localize_script($this->wp_sdtrk, 'wp_sdtrk', $localizedData);
    }

    /**
     * Collect all Local-Data and pass them to JS
     */
    private function localize_localData($loadMinified = "")
    {
        // Init
        // Register Script for Local-Tracking
        wp_register_script("wp_sdtrk-local", plugins_url("js/wp-sdtrk-local" . $loadMinified . ".js", __FILE__), array(
            'jquery'
        ), "1.0", false);

        $localizedData = array();
        // Enabled-Switch
        $enabled = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server"), "yes") == 0) ? true : false;

        $localizedData['enabled'] = $enabled;
        wp_localize_script("wp_sdtrk-local", 'wp_sdtrk_local', $localizedData);
        wp_enqueue_script('wp_sdtrk-local');
    }

    /**
     * Collect all FB-Data and pass them to JS
     */
    private function localize_fbData($loadMinified = "")
    {
        // Init
        // Register Script for Facebook-Tracking
        wp_register_script("wp_sdtrk-fb", plugins_url("js/wp-sdtrk-fb" . $loadMinified . ".js", __FILE__), array(
            'jquery'
        ), "1.0", false);

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
     * Collect all TT-Data and pass them to JS
     */
    private function localize_ttData($loadMinified = "")
    {
        // Init
        // Register Script for TikTok-Tracking
        wp_register_script("wp_sdtrk-tt", plugins_url("js/wp-sdtrk-tt" . $loadMinified . ".js", __FILE__), array(
            'jquery'
        ), "1.0", false);

        $localizedData = array();

        // Pixel ID
        $tt_pixelId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "tt_pixelid");
        $tt_pixelId = ($tt_pixelId && ! empty(trim($tt_pixelId))) ? $tt_pixelId : false;

        // Track Browser Enabled
        $trkBrowser = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "tt_trk_browser"), "yes") == 0) ? true : false;

        // Tik Tok: Track Browser Cookie Service
        $tt_trkBrowserCookieService = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "tt_trk_browser_cookie_service");
        $tt_trkBrowserCookieService = ($tt_trkBrowserCookieService && ! empty(trim($tt_trkBrowserCookieService))) ? $tt_trkBrowserCookieService : false;

        // Tik Tok: Track Browser Cookie ID
        $tt_trkBrowserCookieId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "tt_trk_browser_cookie_id");
        $tt_trkBrowserCookieId = ($tt_trkBrowserCookieId && ! empty(trim($tt_trkBrowserCookieId))) ? $tt_trkBrowserCookieId : false;

        // Track Server Enabled
        $trkServer = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "tt_trk_server"), "yes") == 0) ? true : false;

        // Tik Tok: Track Server Cookie Service
        $tt_trkServerCookieService = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "tt_trk_server_cookie_service");
        $tt_trkServerCookieService = ($tt_trkServerCookieService && ! empty(trim($tt_trkServerCookieService))) ? $tt_trkServerCookieService : false;

        // Tik Tok: Track Server Cookie ID
        $tt_trkServerCookieId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "tt_trk_server_cookie_id");
        $tt_trkServerCookieId = ($tt_trkServerCookieId && ! empty(trim($tt_trkServerCookieId))) ? $tt_trkServerCookieId : false;

        $localizedData['tt_id'] = $tt_pixelId;
        $localizedData['tt_b_e'] = $trkBrowser;
        $localizedData['c_tt_b_i'] = $tt_trkBrowserCookieId;
        $localizedData['c_tt_b_s'] = $tt_trkBrowserCookieService;
        $localizedData['tt_s_e'] = $trkServer;
        $localizedData['c_tt_s_i'] = $tt_trkServerCookieId;
        $localizedData['c_tt_s_s'] = $tt_trkServerCookieService;

        wp_localize_script("wp_sdtrk-tt", 'wp_sdtrk_tt', $localizedData);
        wp_enqueue_script('wp_sdtrk-tt');
    }

    /**
     * Collect all lIn-Data and pass them to JS
     */
    private function localize_linData($loadMinified = "")
    {
        // Init
        // Register Script for LinkedIn-Tracking
        wp_register_script("wp_sdtrk-lin", plugins_url("js/wp-sdtrk-lin" . $loadMinified . ".js", __FILE__), array(
            'jquery'
        ), "1.0", false);

        $localizedData = array();

        // Pixel ID
        $lin_pixelId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "lin_pixelid");
        $lin_pixelId = ($lin_pixelId && ! empty(trim($lin_pixelId))) ? $lin_pixelId : false;

        // Track Browser Enabled
        $trkBrowser = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "lin_trk_browser"), "yes") == 0) ? true : false;

        // LinkedIn: Track Browser Cookie Service
        $lin_trkBrowserCookieService = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "lin_trk_browser_cookie_service");
        $lin_trkBrowserCookieService = ($lin_trkBrowserCookieService && ! empty(trim($lin_trkBrowserCookieService))) ? $lin_trkBrowserCookieService : false;

        // LinkedIn: Track Browser Cookie ID
        $lin_trkBrowserCookieId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "lin_trk_browser_cookie_id");
        $lin_trkBrowserCookieId = ($lin_trkBrowserCookieId && ! empty(trim($lin_trkBrowserCookieId))) ? $lin_trkBrowserCookieId : false;

        // LinkedIn Mappings
        $linMappingData = wp_sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "lin_trk_map");
        $linMappings = array();
        if ($linMappingData) {
            foreach ($linMappingData as $dataSet) {
                $eventName = $dataSet['lin_trk_map_event'];
                $convId = $dataSet['lin_trk_map_event_lin_convid'];
                $rules = array();
                if ($dataSet['lin_trk_map_event_rules'] && ! empty($dataSet['lin_trk_map_event_rules'])) {
                    foreach ($dataSet['lin_trk_map_event_rules'] as $ruleSet) {
                        $ruleParam = $ruleSet["lin_trk_map_event_rules_param"];
                        $ruleValue = $ruleSet["lin_trk_map_event_rules_value"];
                        if ($ruleParam && $ruleValue) {
                            $rules[$ruleParam] = $ruleValue;
                        }
                    }
                }
                array_push($linMappings, array(
                    'eventName' => $eventName,
                    'convId' => $convId,
                    'rules' => $rules
                ));
            }
        }
        // LinkedIn Button-Mappings
        $linMappingData = wp_sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "lin_trk_btnmap");
        $linBtnMappings = array();
        if ($linMappingData) {
            foreach ($linMappingData as $dataSet) {
                $btnTag = $dataSet['lin_trk_map_btnevent_lin_btnTag'];
                $convId = $dataSet['lin_trk_map_btnevent_lin_convid'];
                array_push($linBtnMappings, array(
                    'btnTag' => $btnTag,
                    'convId' => $convId
                ));
            }
        }
        $localizedData['lin_map'] = $linMappings;
        $localizedData['lin_btnmap'] = $linBtnMappings;
        $localizedData['lin_id'] = $lin_pixelId;
        $localizedData['lin_b_e'] = $trkBrowser;
        $localizedData['c_lin_b_i'] = $lin_trkBrowserCookieId;
        $localizedData['c_lin_b_s'] = $lin_trkBrowserCookieService;

        wp_localize_script("wp_sdtrk-lin", 'wp_sdtrk_lin', $localizedData);
        wp_enqueue_script('wp_sdtrk-lin');
    }

    /**
     * Collect all GA-Data and pass them to JS
     */
    private function localize_gaData($loadMinified = "")
    {
        // Init
        // Register Script for Google-Tracking
        wp_register_script("wp_sdtrk-ga", plugins_url("js/wp-sdtrk-ga" . $loadMinified . ".js", __FILE__), array(
            'jquery'
        ), "1.0", false);
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

        // Track Server Enabled
        $trkServer = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_server"), "yes") == 0) ? true : false;

        // Google: Track Server Cookie Service
        $ga_trkServerCookieService = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_server_cookie_service");
        $ga_trkServerCookieService = ($ga_trkServerCookieService && ! empty(trim($ga_trkServerCookieService))) ? $ga_trkServerCookieService : false;

        // Google: Track Server Cookie ID
        $ga_trkServerCookieId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_server_cookie_id");
        $ga_trkServerCookieId = ($ga_trkServerCookieId && ! empty(trim($ga_trkServerCookieId))) ? $ga_trkServerCookieId : false;

        $localizedData['ga_id'] = $messId;
        $localizedData['ga_debug'] = $debug;
        $localizedData['ga_b_e'] = $trkBrowser;
        $localizedData['c_ga_b_i'] = $trkBrowserCookieId;
        $localizedData['c_ga_b_s'] = $trkBrowserCookieService;
        $localizedData['ga_s_e'] = $trkServer;
        $localizedData['c_ga_s_i'] = $ga_trkServerCookieId;
        $localizedData['c_ga_s_s'] = $ga_trkServerCookieService;

        wp_localize_script("wp_sdtrk-ga", 'wp_sdtrk_ga', $localizedData);
        wp_enqueue_script('wp_sdtrk-ga');
    }

    /**
     * Collect all FL-Data and pass them to JS
     */
    private function localize_flData($loadMinified = "")
    {
        // Init
        // Register Script for Google-Tracking
        wp_register_script("wp_sdtrk-fl", plugins_url("js/wp-sdtrk-fl" . $loadMinified . ".js", __FILE__), array(
            'jquery'
        ), "1.0", false);
        $localizedData = array();

        // Mess ID
        $trkId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fl_tracking_id");
        $trkId = ($trkId && ! empty(trim($trkId))) ? $trkId : false;

        // Track Browser Enabled
        $trkBrowser = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fl_trk_browser"), "yes") == 0) ? true : false;

        // Funnelytics: Track Browser Cookie Service
        $trkBrowserCookieService = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fl_trk_browser_cookie_service");
        $trkBrowserCookieService = ($trkBrowserCookieService && ! empty(trim($trkBrowserCookieService))) ? $trkBrowserCookieService : false;

        // Funnelytics: Track Browser Cookie ID
        $trkBrowserCookieId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fl_trk_browser_cookie_id");
        $trkBrowserCookieId = ($trkBrowserCookieId && ! empty(trim($trkBrowserCookieId))) ? $trkBrowserCookieId : false;

        $localizedData['fl_id'] = $trkId;
        $localizedData['fl_b_e'] = $trkBrowser;
        $localizedData['c_fl_b_i'] = $trkBrowserCookieId;
        $localizedData['c_fl_b_s'] = $trkBrowserCookieService;

        wp_localize_script("wp_sdtrk-fl", 'wp_sdtrk_fl', $localizedData);
        wp_enqueue_script('wp_sdtrk-fl');
    }

    /**
     * Collect all MTC-Data and pass them to JS
     */
    private function localize_mtcData($loadMinified = "")
    {
        // Init
        // Register Script for Google-Tracking
        wp_register_script("wp_sdtrk-mtc", plugins_url("js/wp-sdtrk-mtc" . $loadMinified . ".js", __FILE__), array(
            'jquery'
        ), "1.0", false);
        $localizedData = array();

        // Mess ID
        $trkId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "mtc_tracking_id");
        $trkId = ($trkId && ! empty(trim($trkId))) ? $trkId : false;

        // Track Browser Enabled
        $trkBrowser = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "mtc_trk_browser"), "yes") == 0) ? true : false;

        // Mautic: Track Browser Cookie Service
        $trkBrowserCookieService = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "mtc_trk_browser_cookie_service");
        $trkBrowserCookieService = ($trkBrowserCookieService && ! empty(trim($trkBrowserCookieService))) ? $trkBrowserCookieService : false;

        // Mautic: Track Browser Cookie ID
        $trkBrowserCookieId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "mtc_trk_browser_cookie_id");
        $trkBrowserCookieId = ($trkBrowserCookieId && ! empty(trim($trkBrowserCookieId))) ? $trkBrowserCookieId : false;

        $localizedData['mtc_id'] = $trkId;
        $localizedData['mtc_b_e'] = $trkBrowser;
        $localizedData['c_mtc_b_i'] = $trkBrowserCookieId;
        $localizedData['c_mtc_b_s'] = $trkBrowserCookieService;

        wp_localize_script("wp_sdtrk-mtc", 'wp_sdtrk_mtc', $localizedData);
        wp_enqueue_script('wp_sdtrk-mtc');
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
                // Local tracking
                case 'local':
                    $localTracker = new Wp_Sdtrk_Tracker_Local();
                    $localTracker->fireTracking_Server($event, $meta);
                    return array(
                        'state' => true
                    );
                    break;

                // Facebook CAPI
                case 'fb':
                    $fbp = (isset($meta['fbp'])) ? $meta['fbp'] : "";
                    $fbc = (isset($meta['fbc'])) ? $meta['fbc'] : "";
                    $fbTracker = new Wp_Sdtrk_Tracker_Fb();
                    $fbTracker->fireTracking_Server($event, $fbp, $fbc);
                    return array(
                        'state' => true
                    );
                    break;
                // Facebook CAPI Time-Events
                case 'fb-tt':
                    $fbp = (isset($meta['fbp'])) ? $meta['fbp'] : "";
                    $fbc = (isset($meta['fbc'])) ? $meta['fbc'] : "";
                    $timeEventId = (isset($meta['timeEventId'])) ? $meta['timeEventId'] : "";
                    $timeEventName = (isset($meta['timeEventName'])) ? $meta['timeEventName'] : "";
                    $event->setTimeTriggerData($timeEventName, $timeEventId);
                    $fbTracker = new Wp_Sdtrk_Tracker_Fb();
                    $fbTracker->fireTracking_Server($event, $fbp, $fbc);
                    return array(
                        'state' => true
                    );
                    break;
                // Facebook CAPI Scroll-Events
                case 'fb-sd':
                    $fbp = (isset($meta['fbp'])) ? $meta['fbp'] : "";
                    $fbc = (isset($meta['fbc'])) ? $meta['fbc'] : "";
                    $scrollEventId = (isset($meta['scrollEventId'])) ? $meta['scrollEventId'] : "";
                    $scrollEventName = (isset($meta['scrollEventName'])) ? $meta['scrollEventName'] : "";
                    $event->setScrollTriggerData($scrollEventName, $scrollEventId);
                    $fbTracker = new Wp_Sdtrk_Tracker_Fb();
                    $fbTracker->fireTracking_Server($event, $fbp, $fbc);
                    return array(
                        'state' => true
                    );
                    break;
                // Facebook CAPI Button-Events
                case 'fb-bc':
                    $fbp = (isset($meta['fbp'])) ? $meta['fbp'] : "";
                    $fbc = (isset($meta['fbc'])) ? $meta['fbc'] : "";
                    $clickEventId = (isset($meta['clickEventId'])) ? $meta['clickEventId'] : "";
                    $clickEventName = (isset($meta['clickEventName'])) ? $meta['clickEventName'] : "";
                    $clickEventTag = (isset($meta['clickEventTag'])) ? $meta['clickEventTag'] : "";
                    $event->setClickTriggerData($clickEventName, $clickEventId, $clickEventTag);
                    $fbTracker = new Wp_Sdtrk_Tracker_Fb();
                    $fbTracker->fireTracking_Server($event, $fbp, $fbc);
                    return array(
                        'state' => true
                    );
                    break;
                case 'ga':
                    $cid = (isset($meta['cid'])) ? $meta['cid'] : "";
                    $gaTracker = new Wp_Sdtrk_Tracker_Ga();
                    $gaTracker->fireTracking_Server($event, $cid);
                    return array(
                        'state' => true
                    );
                    break;
                // Google CAPI Time-Events
                case 'ga-tt':
                    $cid = (isset($meta['cid'])) ? $meta['cid'] : "";
                    $timeEventId = (isset($meta['timeEventId'])) ? $meta['timeEventId'] : "";
                    $timeEventName = (isset($meta['timeEventName'])) ? $meta['timeEventName'] : "";
                    $event->setTimeTriggerData($timeEventName, $timeEventId);
                    $gaTracker = new Wp_Sdtrk_Tracker_Ga();
                    $gaTracker->fireTracking_Server($event, $cid);
                    return array(
                        'state' => true
                    );
                    break;
                // Google CAPI Scroll-Events
                case 'ga-sd':
                    $cid = (isset($meta['cid'])) ? $meta['cid'] : "";
                    $scrollEventId = (isset($meta['scrollEventId'])) ? $meta['scrollEventId'] : "";
                    $scrollEventName = (isset($meta['scrollEventName'])) ? $meta['scrollEventName'] : "";
                    $event->setScrollTriggerData($scrollEventName, $scrollEventId);
                    $gaTracker = new Wp_Sdtrk_Tracker_Ga();
                    $gaTracker->fireTracking_Server($event, $cid);
                    return array(
                        'state' => true
                    );
                    break;
                // Google CAPI Button-Events
                case 'ga-bc':
                    $cid = (isset($meta['cid'])) ? $meta['cid'] : "";
                    $clickEventId = (isset($meta['clickEventId'])) ? $meta['clickEventId'] : "";
                    $clickEventName = (isset($meta['clickEventName'])) ? $meta['clickEventName'] : "";
                    $clickEventTag = (isset($meta['clickEventTag'])) ? $meta['clickEventTag'] : "";
                    $event->setClickTriggerData($clickEventName, $clickEventId, $clickEventTag);
                    $gaTracker = new Wp_Sdtrk_Tracker_Ga();
                    $gaTracker->fireTracking_Server($event, $cid);
                    return array(
                        'state' => true
                    );
                    break;
                // Tik Tok CAPI
                case 'tt':
                    $hashId = (isset($meta['hashId'])) ? $meta['hashId'] : "";
                    $ttc = (isset($meta['ttc'])) ? $meta['ttc'] : "";
                    $ttTracker = new Wp_Sdtrk_Tracker_Tt();
                    $ttTracker->fireTracking_Server($event, $hashId, $ttc);
                    return array(
                        'state' => true
                    );
                    break;
                // Tik Tok CAPI Time-Events
                case 'tt-tt':
                    $hashId = (isset($meta['hashId'])) ? $meta['hashId'] : "";
                    $ttc = (isset($meta['ttc'])) ? $meta['ttc'] : "";
                    $timeEventId = (isset($meta['timeEventId'])) ? $meta['timeEventId'] : "";
                    $timeEventName = (isset($meta['timeEventName'])) ? $meta['timeEventName'] : "";
                    $event->setTimeTriggerData($timeEventName, $timeEventId);
                    $ttTracker = new Wp_Sdtrk_Tracker_Tt();
                    $ttTracker->fireTracking_Server($event, $hashId, $ttc);
                    return array(
                        'state' => true
                    );
                    break;
                // Tik Tok CAPI Scroll-Events
                case 'tt-sd':
                    $hashId = (isset($meta['hashId'])) ? $meta['hashId'] : "";
                    $ttc = (isset($meta['ttc'])) ? $meta['ttc'] : "";
                    $scrollEventId = (isset($meta['scrollEventId'])) ? $meta['scrollEventId'] : "";
                    $scrollEventName = (isset($meta['scrollEventName'])) ? $meta['scrollEventName'] : "";
                    $event->setScrollTriggerData($scrollEventName, $scrollEventId);
                    $ttTracker = new Wp_Sdtrk_Tracker_Tt();
                    $ttTracker->fireTracking_Server($event, $hashId, $ttc);
                    return array(
                        'state' => true
                    );
                    break;
                // Tik Tok CAPI Button-Events
                case 'tt-bc':
                    $hashId = (isset($meta['hashId'])) ? $meta['hashId'] : "";
                    $ttc = (isset($meta['ttc'])) ? $meta['ttc'] : "";
                    $clickEventId = (isset($meta['clickEventId'])) ? $meta['clickEventId'] : "";
                    $clickEventName = (isset($meta['clickEventName'])) ? $meta['clickEventName'] : "";
                    $clickEventTag = (isset($meta['clickEventTag'])) ? $meta['clickEventTag'] : "";
                    $event->setClickTriggerData($clickEventName, $clickEventId, $clickEventTag);
                    $ttTracker = new Wp_Sdtrk_Tracker_Tt();
                    $ttTracker->fireTracking_Server($event, $hashId, $ttc);
                    return array(
                        'state' => true
                    );
                    break;
            }
        }
        return array(
            'state' => false
        );
    }

    /**
     * Decrypt Data for given services
     *
     * @param array $data
     * @param array $meta
     * @return array
     */
    public function decryptData($data, $meta)
    {
        $services = $meta;
        $decryptedData = $data;
        if (empty($data) || empty($meta)) {
            return array(
                'state' => true,
                'data' => $decryptedData
            );
        }
        foreach ($services as $service) {
            // If service exists and a secret key has been saved
            $className = 'Wp_Sdtrk_Decrypter_' . $service;
            $key = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), $service . "_encrypt_data_key");

            // Create the decrypter and decrypt data
            if (class_exists($className) && ($key !== false && ! empty($key))) {
                $decrypter = new $className($key, $decryptedData);
                $decryptedData = $decrypter->getDecryptedData();
            }
        }
        return array(
            'state' => true,
            'data' => $decryptedData
        );
    }
    
    /////////////////////////////////////////////////////
    // ADD TO FILE -> public/class-plugin-name-public.php
    
    // add blackout to the whitelist of variables it wordpress knows and allows
    // in this case it is the plugin name
    public function whitelist_query_variable( $query_vars ) {
        
        $query_vars[] = $this->wp_sdtrk;
        return $query_vars;
        
    }
    
    // If this is done, we can access it later
    // This example checks very early in the process:
    // if the variable is set, we include our page and stop execution after it
    public function redirect_to_file( &$wp ){
        
        if ( array_key_exists( $this->wp_sdtrk, $wp->query_vars ) ) {
            
            switch ( $wp->query_vars[$this->wp_sdtrk] ) {
                
                case 'gauth':
                    include( WP_PLUGIN_DIR . '/' . $this->wp_sdtrk . '/api/gauth.php' );
                    break;                    
            }
            
            exit();
            
        }
    }
}
