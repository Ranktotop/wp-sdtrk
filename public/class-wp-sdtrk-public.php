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
        wp_register_script("wp_sdtrk-ga", plugins_url("js/wp-sdtrk-ga.js", __FILE__), array(
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
        
        $event = new Wp_Sdtrk_Tracker_Event();
        
        //Facebook
        $fbTracker = new Wp_Sdtrk_Tracker_Fb();
        $fbTracker->fireTracking($event);
        
        //Google Analytics
        $gaTracker = new Wp_Sdtrk_Tracker_Ga();
        $gaTracker->fireTracking($event);
        
    }
    
    /**
     * Looks for GET Parameters and save them in Cookies
     */
    public function saveCookies(){
        // Frontend only
        if (is_admin()) {
            return;
        }        
        $cookieNames = array('utm_source','utm_medium','utm_term','utm_content','utm_campaign');
        foreach($cookieNames as $param){
            if(isset($_GET[$param]) && !empty($_GET[$param])){
                Wp_Sdtrk_Helper::wp_sdtrk_setCookie($param,$_GET[$param],true,14);
            }
        }
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
     * Backloads the Serverside Facebook Tracking
     * @param array $data
     * @param array $meta
     * @return boolean[]|NULL[]|boolean[]
     */
    public function backload_fb_s($data, $meta){            
        // if all required values are passed
        if (isset($meta['s_consent']) && $meta['eventData']) {
            $oldConsent = ($meta['s_consent'] === '1') ? true : false;
            //If consent was not given
            if(!$oldConsent){
                $event = new Wp_Sdtrk_Tracker_Event();
                $event->setEventFromArray($meta['eventData']);
                
                //Facebook
                $fbTracker = new Wp_Sdtrk_Tracker_Fb();
                $fbTracker->fireTracking_Server($event);
            }
            
                        
            return array(
                'state' => true
            );
        }
        return array(
            'state' => false
        );
    }
}
