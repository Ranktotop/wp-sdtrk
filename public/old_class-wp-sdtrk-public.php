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
        $loadMinified = true;
        $minifySwitch = ($loadMinified) ? ".min" : "";

        $this->registerScript_localTracker($minifySwitch);
        $this->registerScript_fbTracker($minifySwitch);
        $this->registerScript_gaTracker($minifySwitch);
        $this->registerScript_ttTracker($minifySwitch);
        $this->registerScript_linTracker($minifySwitch);
        $this->registerScript_flTracker($minifySwitch);
        $this->registerScript_mtcTracker($minifySwitch);
        $this->registerScript_decrypter($minifySwitch);
        $this->registerScript_fingerprinter($minifySwitch);
        $this->registerScript_engine($minifySwitch);
    }

    public function licensecheck()
    {
        $licenseKey = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", ''), "licensekey_" . WP_SDTRK_LICENSE_TYPE_PRO);
        Wp_Sdtrk_License::wp_sdtrk_license_call($licenseKey, WP_SDTRK_LICENSE_TYPE_PRO, 'check');
    }

    /**
     * Write Hits down to csv
     */
    public function local_csv_feed($enclose = "auto")
    {
        $debug = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_debug"), "yes") == 0) ? true : false;
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log("----Write CSV File-----", $debug);
        $hitContainer = new Wp_Sdtrk_hitContainer($debug);
        $hits = $hitContainer->getHitsForCSV();
        //order the header asc
        $header = array_keys($hits[0]);
        sort($header);

        $filePath = plugin_dir_path(dirname(__FILE__)) . 'api/localHits.csv';
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log("Write " . sizeof($hits) . " lines to " . $filePath, $debug);
        try {
            $fp = fopen($filePath, 'w');
            // Write the header
            switch ($enclose) {
                case "auto":
                    fputcsv($fp, $header);
                    break;
                case "off":
                    fputs($fp, implode(",", array_map(function ($item) {
                        return $item;
                    }, $header)) . "\r\n");
                    break;
                case "on":
                    fputs($fp, implode(",", array_map(function ($item) {
                        return '"' . $item . '"';
                    }, $header)) . "\r\n");
                    break;
                default:
                    fputcsv($fp, $header);
                    break;
            }
            // Write fields
            switch ($enclose) {
                case "auto":
                    foreach ($hits as $hit) {
                        ksort($hit); //sort same as header
                        fputcsv($fp, $hit);
                    }
                    break;
                case "off":
                    foreach ($hits as $hit) {
                        ksort($hit); //sort same as header
                        fputs($fp, implode(",", array_map(function ($item) {
                            return $item;
                        }, $hit)) . "\r\n");
                    }
                    break;
                case "on":
                    foreach ($hits as $hit) {
                        ksort($hit); //sort same as header
                        fputs($fp, implode(",", array_map(function ($item) {
                            return '"' . $item . '"';
                        }, $hit)) . "\r\n");
                    }
                    break;
                default:
                    fputcsv($fp, $header);
                    break;
            }
            fclose($fp);
            Wp_Sdtrk_Helper::wp_sdtrk_vardump_log("Successfully wrote CSV-File!", $debug);
        } catch (Exception $e) {
            Wp_Sdtrk_Helper::wp_sdtrk_write_log($e->getMessage(), $debug);
            return false;
        }
    }

    /**
     * Gsync
     */
    public function local_gsync()
    {
        $result = true;

        // Get Data
        $trkServer = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server"), "yes") == 0) ? true : false;
        $syncGsheet = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_gsync"), "yes") == 0) ? true : false;
        $cred = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_gsync_cred");
        $localGauth_authenticated = (get_option('wp-sdtrk-gauth-token') === false) ? false : true;
        $sheetId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_gsync_sheetId");
        $tableName = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_gsync_tableName");
        $debug = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_debug"), "yes") == 0) ? true : false;

        // If data are set
        if ($trkServer && $syncGsheet && ! empty($cred) && $localGauth_authenticated && ! empty($sheetId) && ! empty($tableName)) {
            $options = array(
                "cred" => $cred,
                "sheetId" => $sheetId,
                "tableName" => $tableName,
                "startColumn" => "A",
                "endColumn" => "Z",
                "startRow" => "1",
                "debug" => $debug
            );

            require_once plugin_dir_path(dirname(__FILE__)) . 'api/google/gConnector.php';
            $gConnector = new gConnector($options);
            if ($gConnector->isConnected()) {
                $result = $gConnector->sync();
            } else {
                $result = false;
            }
        }
        return $result;
    }

    /**
     * Generates a specific handler context name
     *
     * @param string $type
     * @param string $name
     * @return string
     */
    private function get_jsHandler($type, $name)
    {
        switch ($type) {
            case 'name':
                return str_replace('-', '_', $this->wp_sdtrk) . '-' . $name;
                break;
            case 'file':
                return str_replace('_', '-', $this->wp_sdtrk . '-' . $name);
                break;
            case 'var':
                return str_replace('-', '_', $this->wp_sdtrk . '-' . $name);
                break;
        }
    }

    /**
     * Register and localize local tracker
     *
     * @param string $loadMinified
     */
    private function registerScript_localTracker($loadMinified = "")
    {
        // Init
        $localizedData = array();

        // Enabled-Switch
        $enabled = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server"), "yes") == 0) ? true : false;

        // Debug
        $debugmode = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_debug"), "yes") == 0) ? true : false;


        // Merge to array
        $localizedData['enabled'] = $enabled;
        $localizedData['dbg'] = $debugmode;

        // Register scripts
        wp_register_script($this->get_jsHandler('name', 'local'), plugins_url("js/" . $this->get_jsHandler('file', 'local') . $loadMinified . ".js", __FILE__), array(), $this->version, false);
        wp_localize_script($this->get_jsHandler('name', 'local'), $this->get_jsHandler('var', 'local'), $localizedData);
    }

    /**
     * Register and localize facebook tracker
     *
     * @param string $loadMinified
     */
    private function registerScript_fbTracker($loadMinified = "")
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

        // Debug
        $debugmode = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_debug"), "yes") == 0) ? true : false;


        // Merge to array
        $localizedData['pid'] = $fb_pixelId;
        $localizedData['b_e'] = $trkBrowser;
        $localizedData['b_ci'] = $fb_trkBrowserCookieId;
        $localizedData['b_cs'] = $fb_trkBrowserCookieService;
        $localizedData['s_e'] = $trkServer;
        $localizedData['s_ci'] = $fb_trkServerCookieId;
        $localizedData['s_cs'] = $fb_trkServerCookieService;
        $localizedData['dbg'] = $debugmode;

        // Register scripts
        wp_register_script($this->get_jsHandler('name', 'fb'), plugins_url("js/" . $this->get_jsHandler('file', 'fb') . $loadMinified . ".js", __FILE__), array(), $this->version, false);
        wp_localize_script($this->get_jsHandler('name', 'fb'), $this->get_jsHandler('var', 'fb'), $localizedData);
    }

    /**
     * Collect all GA-Data and pass them to JS
     */
    private function registerScript_gaTracker($loadMinified = "")
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

        // Track Server Enabled
        $trkServer = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_server"), "yes") == 0) ? true : false;

        // Google: Track Server Cookie Service
        $ga_trkServerCookieService = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_server_cookie_service");
        $ga_trkServerCookieService = ($ga_trkServerCookieService && ! empty(trim($ga_trkServerCookieService))) ? $ga_trkServerCookieService : false;

        // Google: Track Server Cookie ID
        $ga_trkServerCookieId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_server_cookie_id");
        $ga_trkServerCookieId = ($ga_trkServerCookieId && ! empty(trim($ga_trkServerCookieId))) ? $ga_trkServerCookieId : false;

        // Merge to array
        $localizedData['pid'] = $messId;
        $localizedData['debug'] = $debug;
        $localizedData['b_e'] = $trkBrowser;
        $localizedData['b_ci'] = $trkBrowserCookieId;
        $localizedData['b_cs'] = $trkBrowserCookieService;
        $localizedData['s_e'] = $trkServer;
        $localizedData['s_ci'] = $ga_trkServerCookieId;
        $localizedData['s_cs'] = $ga_trkServerCookieService;
        $localizedData['dbg'] = $debug; // this is for frontend debug log

        // Register scripts
        wp_register_script($this->get_jsHandler('name', 'ga'), plugins_url("js/" . $this->get_jsHandler('file', 'ga') . $loadMinified . ".js", __FILE__), array(), $this->version, false);
        wp_localize_script($this->get_jsHandler('name', 'ga'), $this->get_jsHandler('var', 'ga'), $localizedData);
    }

    /**
     * Collect all TT-Data and pass them to JS
     */
    private function registerScript_ttTracker($loadMinified = "")
    {
        // Init
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

        // Debug
        $debugmode = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "tt_trk_debug"), "yes") == 0) ? true : false;

        // Merge to array
        $localizedData['pid'] = $tt_pixelId;
        $localizedData['b_e'] = $trkBrowser;
        $localizedData['b_ci'] = $tt_trkBrowserCookieId;
        $localizedData['b_cs'] = $tt_trkBrowserCookieService;
        $localizedData['s_e'] = $trkServer;
        $localizedData['s_ci'] = $tt_trkServerCookieId;
        $localizedData['s_cs'] = $tt_trkServerCookieService;
        $localizedData['dbg'] = $debugmode;

        // Register scripts
        wp_register_script($this->get_jsHandler('name', 'tt'), plugins_url("js/" . $this->get_jsHandler('file', 'tt') . $loadMinified . ".js", __FILE__), array(), $this->version, false);
        wp_localize_script($this->get_jsHandler('name', 'tt'), $this->get_jsHandler('var', 'tt'), $localizedData);
    }

    /**
     * Collect all lIn-Data and pass them to JS
     */
    private function registerScript_linTracker($loadMinified = "")
    {
        // Init
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
                if (isset($dataSet['lin_trk_map_event_rules']) && ! empty($dataSet['lin_trk_map_event_rules'])) {
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
        // LinkedIn Item-Visibility-Mappings
        $linMappingData = wp_sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "lin_trk_ivmap");
        $linIvMappings = array();
        if ($linMappingData) {
            foreach ($linMappingData as $dataSet) {
                $ivTag = $dataSet['lin_trk_map_ivevent_lin_ivTag'];
                $convId = $dataSet['lin_trk_map_ivevent_lin_convid'];
                array_push($linIvMappings, array(
                    'ivTag' => $ivTag,
                    'convId' => $convId
                ));
            }
        }
        // Debug
        $debugmode = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "lin_trk_debug"), "yes") == 0) ? true : false;


        // Merge to array
        $localizedData['map_ev'] = $linMappings;
        $localizedData['map_btn'] = $linBtnMappings;
        $localizedData['map_iv'] = $linIvMappings;
        $localizedData['pid'] = $lin_pixelId;
        $localizedData['b_e'] = $trkBrowser;
        $localizedData['b_ci'] = $lin_trkBrowserCookieId;
        $localizedData['b_cs'] = $lin_trkBrowserCookieService;
        $localizedData['dbg'] = $debugmode;

        // Register scripts
        wp_register_script($this->get_jsHandler('name', 'lin'), plugins_url("js/" . $this->get_jsHandler('file', 'lin') . $loadMinified . ".js", __FILE__), array(), $this->version, false);
        wp_localize_script($this->get_jsHandler('name', 'lin'), $this->get_jsHandler('var', 'lin'), $localizedData);
    }

    /**
     * Collect all FL-Data and pass them to JS
     */
    private function registerScript_flTracker($loadMinified = "")
    {
        // Init
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

        // Debug
        $debugmode = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fl_trk_debug"), "yes") == 0) ? true : false;

        // Merge to array
        $localizedData['pid'] = $trkId;
        $localizedData['b_e'] = $trkBrowser;
        $localizedData['b_ci'] = $trkBrowserCookieId;
        $localizedData['b_cs'] = $trkBrowserCookieService;
        $localizedData['dbg'] = $debugmode;

        // Register scripts
        wp_register_script($this->get_jsHandler('name', 'fl'), plugins_url("js/" . $this->get_jsHandler('file', 'fl') . $loadMinified . ".js", __FILE__), array(), $this->version, false);
        wp_localize_script($this->get_jsHandler('name', 'fl'), $this->get_jsHandler('var', 'fl'), $localizedData);
    }

    /**
     * Collect all MTC-Data and pass them to JS
     */
    private function registerScript_mtcTracker($loadMinified = "")
    {
        // Init
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

        // Debug
        $debugmode = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "mtc_trk_debug"), "yes") == 0) ? true : false;

        // Merge to array
        $localizedData['pid'] = $trkId;
        $localizedData['b_e'] = $trkBrowser;
        $localizedData['b_ci'] = $trkBrowserCookieId;
        $localizedData['b_cs'] = $trkBrowserCookieService;
        $localizedData['dbg'] = $debugmode;

        // Register scripts
        wp_register_script($this->get_jsHandler('name', 'mtc'), plugins_url("js/" . $this->get_jsHandler('file', 'mtc') . $loadMinified . ".js", __FILE__), array(), $this->version, false);
        wp_localize_script($this->get_jsHandler('name', 'mtc'), $this->get_jsHandler('var', 'mtc'), $localizedData);
    }

    /**
     * Register and localize decrypter
     *
     * @param string $loadMinified
     */
    private function registerScript_decrypter($loadMinified = "")
    {
        // Init
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

        // Merge to array
        $localizedData['services'] = $services;

        // Register scripts
        wp_register_script($this->get_jsHandler('name', 'decrypter'), plugins_url("js/" . $this->get_jsHandler('file', 'decrypter') . $loadMinified . ".js", __FILE__), array(), $this->version, false);
        wp_localize_script($this->get_jsHandler('name', 'decrypter'), $this->get_jsHandler('var', 'decrypter'), $localizedData);
    }

    /**
     * Register and localize fingerprinter
     *
     * @param string $loadMinified
     */
    private function registerScript_fingerprinter($loadMinified = "")
    {
        // Init
        $localizedData = array();

        // Digistore24
        $enabled = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "trk_fp"), "yes") == 0) ? true : false;

        // Merge to array
        $localizedData['enabled'] = $enabled;

        // Register scripts
        wp_register_script($this->get_jsHandler('name', 'fp'), plugins_url("js/" . $this->get_jsHandler('file', 'fp') . $loadMinified . ".js", __FILE__), array(), $this->version, false);
        wp_localize_script($this->get_jsHandler('name', 'fp'), $this->get_jsHandler('var', 'fp'), $localizedData);
    }

    /**
     * Register and localize engine
     *
     * @param string $loadMinified
     */
    private function registerScript_engine($loadMinified = "")
    {
        // Init
        $localizedData = array();
        $localizedData['ajax_url'] = admin_url('admin-ajax.php');
        $localizedData['_nonce'] = wp_create_nonce('security_wp-sdtrk');

        global $post;
        $postId = ($post && $post->ID) ? $post->ID : false;
        $prodId = get_post_meta($postId, 'wp-sdtrk-productid', true);
        $prodId = (! $prodId) ? "" : $prodId;

        $trkOverwrite = get_post_meta($postId, 'wp-sdtrk-trkoverwrite', true);
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
        $scrollTracking = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "trk_scroll"), "yes") == 0) ? true : false;
        if ($scrollTracking) {
            $scrollData = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", array()), "trk_scroll_group");
            $scrollData = (! is_array($scrollData)) ? array() : $scrollData;
            if (sizeof($scrollData) > 0) {
                $cleanScrollData = array();
                foreach ($scrollData as $depth) {
                    if (isset($depth["trk_scroll_group_percent"]) && ! empty($depth["trk_scroll_group_percent"])) {
                        array_push($cleanScrollData, floatval($depth["trk_scroll_group_percent"]));
                    }
                }
                $localizedData['scrollTrigger'] = $cleanScrollData;
            }
        }

        // Get Click-Data from Settings
        $clickTracking = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "trk_buttons"), "yes") == 0) ? true : false;
        if ($clickTracking) {
            $localizedData['clickTrigger'] = $clickTracking;
        }

        // Get Visibility-Data from Settings
        $visibilityTracking = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "trk_visibility"), "yes") == 0) ? true : false;
        if ($visibilityTracking) {
            $localizedData['visibilityTrigger'] = $visibilityTracking;
        }

        // Content-ID
        global $post;
        $postId = ($post && $post->ID) ? $post->ID : false;
        $title = $postId ? get_the_title($post) : "";

        //admin
        $isAdmin = (current_user_can('manage_options')) ? true : false;

        // Merge to array
        $localizedData['admin'] = $isAdmin;
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
        $localizedData['evmap'] = Wp_Sdtrk_Helper::wp_sdtrk_getDefaultEventMap(); //eventmap for keep the custom-event-names in sync with server
        $localizedData['pmap'] = Wp_Sdtrk_Helper::wp_sdtrk_getParamNames();

        // Register additional scripts
        wp_register_script($this->get_jsHandler('name', 'event'), plugins_url("js/" . $this->get_jsHandler('file', 'event') . $loadMinified . ".js", __FILE__), array(), $this->version, false);
        wp_register_script($this->get_jsHandler('name', 'helper'), plugins_url("js/" . $this->get_jsHandler('file', 'helper') . $loadMinified . ".js", __FILE__), array(), $this->version, false);

        // Dependencies for engine
        $deps = array(
            'jquery',
            $this->get_jsHandler('name', 'decrypter'),
            $this->get_jsHandler('name', 'fp'),
            $this->get_jsHandler('name', 'event'),
            $this->get_jsHandler('name', 'helper'),
            $this->get_jsHandler('name', 'local'),
            $this->get_jsHandler('name', 'fb'),
            $this->get_jsHandler('name', 'ga'),
            $this->get_jsHandler('name', 'tt'),
            $this->get_jsHandler('name', 'lin'),
            $this->get_jsHandler('name', 'fl'),
            $this->get_jsHandler('name', 'mtc')
        );

        // Register scripts
        wp_enqueue_script($this->get_jsHandler('name', 'engine'), plugin_dir_url(__FILE__) . "js/" . $this->get_jsHandler('file', 'engine') . $loadMinified . ".js", $deps, $this->version, false);
        wp_localize_script($this->get_jsHandler('name', 'engine'), $this->get_jsHandler('var', 'engine'), $localizedData);
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

        $data = (isset($_POST['data'])) ? $_POST['data'] : array();
        $debugMode = (isset($_POST['debug'])) ? $_POST['debug'] : false;

        // Call function and send back result
        $result = $this->$functionName($data, $debugMode);
        die(json_encode($result));
    }

    /**
     * This function is called after Pageload (User-Browser)
     *
     * @param array $data
     * @param array $meta
     * @return array
     */
    public function validateTracker($data, $debugMode = false)
    {
        // Base-Checks
        if (! isset($data['event']) || ! isset($data['type']) || ! isset($data['handler']) || ! isset($data['data'])) {
            return array(
                'state' => false
            );
        }
        // Check for handler and run it
        $event = new Wp_Sdtrk_Tracker_Event($data['event']);
        $className = 'Wp_Sdtrk_Tracker_' . ucfirst($data['type']);
        if (class_exists($className)) {
            $tracker = new $className();
            if (method_exists($tracker, 'fireTracking_Server') && method_exists($tracker, 'setAndGetDebugMode_frontend')) {
                return array(
                    'debug' => $tracker->setAndGetDebugMode_frontend($debugMode),
                    'state' => $tracker->fireTracking_Server($event, $data['handler'], $data['data'])
                );
            }
        }
        return array(
            'state' => false,
            'debug' => false
        );
    }

    /**
     * Decrypt Data for given services
     *
     * @param array $data
     * @return array
     */
    public function decryptData($data)
    {
        if (! isset($data['data']) || ! isset($data['meta'])) {
            return array(
                'state' => false,
                'data' => false
            );
        }

        $services = $data['meta'];
        $encryptedData = $data['data'];

        if (empty($encryptedData) || empty($services)) {
            return array(
                'state' => true,
                'data' => $encryptedData
            );
        }

        foreach ($services as $service) {
            // If service exists and a secret key has been saved
            $className = 'Wp_Sdtrk_Decrypter_' . $service;
            $key = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), $service . "_encrypt_data_key");

            // Create the decrypter and decrypt data
            if (class_exists($className) && ($key !== false && ! empty($key))) {
                $decrypter = new $className($key, $encryptedData);
                $decryptedData = $decrypter->getDecryptedData();
            }
        }
        return array(
            'state' => true,
            'data' => $decryptedData
        );
    }

    // ///////////////////////////////////////////////////
    // ADD TO FILE -> public/class-plugin-name-public.php

    // add blackout to the whitelist of variables it wordpress knows and allows
    // in this case it is the plugin name
    public function whitelist_query_variable($query_vars)
    {
        $query_vars[] = $this->wp_sdtrk;
        return $query_vars;
    }

    // If this is done, we can access it later
    // This example checks very early in the process:
    // if the variable is set, we include our page and stop execution after it
    public function redirect_to_file(&$wp)
    {
        if (array_key_exists($this->wp_sdtrk, $wp->query_vars)) {

            switch ($wp->query_vars[$this->wp_sdtrk]) {

                case 'gauth':
                    include(WP_PLUGIN_DIR . '/' . $this->wp_sdtrk . '/api/gauth.php');
                    break;
                case 'hitfeed':
                    include(WP_PLUGIN_DIR . '/' . $this->wp_sdtrk . '/api/hitfeed.php');
                    break;
            }

            exit();
        }
    }
}
