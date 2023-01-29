<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Wp_Sdtrk
 * @subpackage Wp_Sdtrk/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package Wp_Sdtrk
 * @subpackage Wp_Sdtrk/admin
 * @author Your Name <email@example.com>
 */
class Wp_Sdtrk_Admin
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

    // ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE

    /**
     * Initialize the class and set its properties.
     *
     * @since 1.0.0
     * @param string $wp_sdtrk
     *            The name of this plugin.
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

    // ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE

    /**
     * Register the stylesheets for the admin area.
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
        wp_enqueue_style($this->wp_sdtrk, plugin_dir_url(__FILE__) . 'css/wp-sdtrk-admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
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
        wp_enqueue_script($this->wp_sdtrk, plugin_dir_url(__FILE__) . 'js/wp-sdtrk-admin.js', array(
            'jquery'
        ), $this->version, false);
    }

    public function test_sanitize_callback($val)
    {
        return sanitize_text_field($val);
    }
    
    /**
     * Simply change value yes to no
     * @param string $val
     * @return string
     */
    public function negateCallback($val){
        return ($val ==="yes") ? "no" : $val;
    }
    
    /**
     * Clears the local database if called
     * @param string $val
     * @return string
     */
    public function clearDbCallback($val)
    {
        if($val==="yes"){
            $val = "no";
            $dbHelper = new Wp_Sdtrk_DbHelper();
            $dbHelper->clearDB();
            // Setup Message
            Wp_Sdtrk_Helper::wp_sdtrk_sheduleNotice(__('Local hits database has been cleared', 'wp-sdtrk'),"success");
        }
        return $val;
    }
    
    /**
     * Clears the sync state for gsync
     * @param string $val
     * @return string
     */
    public function clearGsyncCallback($val)
    {
        if($val==="yes"){
            $val = "no";
            $dbHelper = new Wp_Sdtrk_DbHelper();
            $dbHelper->clearGSync();
            // Setup Message
            Wp_Sdtrk_Helper::wp_sdtrk_sheduleNotice(__('Sync state has been reseted', 'wp-sdtrk'),"success");
        }
        return $val;
    }
    
    /**
     * Re-shedules the gsync-cronjob
     * @param string $time
     */
    public function resetGsyncCron($time){
        $timezone = 'Europe/Berlin';
        $timestamp = strtotime($time.':00'.' '.$timezone. " +1 days");
        
        // delete and re-schedule gsync cron job
        if (wp_next_scheduled( 'wp_sdtrk_gsync_cron' ) ) {
            wp_clear_scheduled_hook('wp_sdtrk_gsync_cron');
            
        }	
        wp_schedule_event($timestamp, 'daily', 'wp_sdtrk_gsync_cron' );
        return $time;
    }
    
    /**
     * Sync now callback for gsheet
     * @param string $val
     * @return string
     */
    public function syncGsyncNowCallback($val)
    {
        if($val==="yes"){
            $val = "no";
            $public = new Wp_Sdtrk_Public($this->wp_sdtrk, $this->version, $this->main );
            $public->local_gsync();
        }
        return $val;
    }       
    
    /**
     * Re-shedules the CSVsync-cronjob
     * @param string $time
     */
    public function resetCSVsyncCron($time){  
        $timezone = 'Europe/Berlin';        
        $syncCsvHourly = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_csv_crontime_frequency"), "yes") == 0) ? true : false;
        
        //If hourly sync is activated
        if($syncCsvHourly){
            $csvFrequency = 'hourly';
            $timestamp = time()+ $time*60*60;   
        }
        else{
            $csvFrequency = 'daily';
            $timestamp = strtotime($time.':00'.' '.$timezone. " +1 days");
        }	
        
        // delete and re-schedule gsync cron job
        if (wp_next_scheduled( 'wp_sdtrk_csvsync_cron' ) ) {
            wp_clear_scheduled_hook('wp_sdtrk_csvsync_cron');
            
        }
        wp_schedule_event($timestamp, $csvFrequency, 'wp_sdtrk_csvsync_cron' );
        return $time;
    }
    
    /**
     * Sync now callback for csv
     * @param string $val
     * @return string
     */
    public function syncCSVsyncNowCallback($val)
    {
        if($val==="yes"){
            $val = "no";
            $public = new Wp_Sdtrk_Public($this->wp_sdtrk, $this->version, $this->main );
            $public->local_csv_feed();
        }
        return $val;
    }

    public function create_menu()
    {

        /**
         * Create a submenu page under Plugins.
         * Framework also add "Settings" to your plugin in plugins list.
         *
         * @link https://github.com/JoeSz/Exopite-Simple-Options-Framework
         */
        $config_submenu = array(

            'type' => 'menu', // Required, menu or metabox
            'id' => $this->wp_sdtrk, // Required, meta box id, unique per page, to save: get_option( id )
            'parent' => 'options-general.php', // Parent page of plugin menu (default Settings [options-general.php])
            'submenu' => true, // Required for submenu
            'title' => __('Server-Side-Tracking', 'wp-sdtrk'), // The title of the options page and the name in admin menu
            'capability' => 'manage_options', // The capability needed to view the page
            'plugin_basename' => plugin_basename(plugin_dir_path(__DIR__) . $this->wp_sdtrk . '.php')
            // 'tabbed' => false,
            // 'multilang' => false, // To turn of multilang, default on.
        );

        /*
         * To add a metabox.
         * This normally go to your functions.php or another hook
         */
        $config_metabox = array(
            
            /*
             * METABOX
             */
            'type' => 'metabox', // Required, menu or metabox
            'id' => $this->wp_sdtrk, // Required, meta box id, unique, for saving meta: id[field-id]
            'post_types' => array(
                'page'
            ), // Post types to display meta box
                // 'post_types' => array( 'post', 'page' ), // Could be multiple
            'context' => 'advanced', // The context within the screen where the boxes should display: 'normal', 'side', and 'advanced'.
            'priority' => 'default', // The priority within the context where the boxes should show ('high', 'low').
            'title' => 'Smart Server Side Tracking Plugin', // The title of the metabox
            'capability' => 'edit_posts', // The capability needed to view the page
            'tabbed' => true,
            // 'multilang' => false, // To turn of multilang, default off except if you have qTransalte-X.
            'options' => 'simple' // Only for metabox, options is stored az induvidual meta key, value pair.
        );

        /**
         * instantiate your admin page
         */
        $options_panel = new Exopite_Simple_Options_Framework($config_submenu, $this->getGeneralSettingFields());
        $options_panel = new Exopite_Simple_Options_Framework($config_metabox, $this->getMetaboxSettingFields());
    }

    private function getMetaboxSettingFields()
    {
        // General
        $fields[] = array(
            'name' => 'general',
            'title' => 'Smart Server Side Tracking Plugin',
            'icon' => 'dashicons-admin-generic',
            'fields' => array(
                array(
                    'type' => 'content',
                    'title' => '<h3>' . __('Basic Tracking Settings', 'wp-sdtrk') . '</h3>',
                    'wrap_class' => 'divideHeader',
                    'content' => ''
                ),
                array(
                    'id' => 'wp-sdtrk-productid',
                    'type' => 'text',
                    'title' => __('Product ID', 'wp-sdtrk'),
                    'description' => __('Please enter a product id in order to track the ViewContent-Event', 'wp-sdtrk'),
                    'attributes' => array(
                        'placeholder' => __('Product-ID', 'wp-sdtrk')
                    )
                ),
                array(
                    'id' => 'wp-sdtrk-trkoverwrite',
                    'type' => 'switcher',
                    'title' => __('Bypass Tracking-Consent', 'wp-sdtrk'),
                    'description' => __('Check to track all visitors of this page regardless of their cookie consent', 'wp-sdtrk'),
                    'default' => 'no'
                )
            )
        );
        return $fields;
    }

    private function getGeneralSettingFields()
    {        
        $csvFileUrl = plugin_dir_url(plugin_dir_path(dirname(__FILE__)) . 'api/localHits.csv').'localHits.csv';
        
        //Local Gsheet Sync Data
        $localGauth_authenticated = (get_option('wp-sdtrk-gauth-token')===false) ? false : true;
        $gauth_state = ($localGauth_authenticated) ? '<span style="color:green">'.__('authenticated', 'wp-sdtrk').'</span>' : '<span style="color:red">'.__('not authenticated', 'wp-sdtrk').'</span>';
        $gauth_btnLabel = ($localGauth_authenticated) ? __('Re-authenticate', 'wp-sdtrk') : __('Authenticate', 'wp-sdtrk');
        
        //XML Hit Feed data
        $hitFeedUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/index.php?wp-sdtrk=hitfeed&key='.__('YOURSECRET','wp-sdtrk');

        // Check for activated Cookie-Plugins
        $cookieOptions = array(
            'none' => __('Fire always', 'wp-sdtrk')
        );
        require_once (ABSPATH . 'wp-admin/includes/plugin.php');
        // Borlabs
        if (is_plugin_active('borlabs-cookie/borlabs-cookie.php')) {
            $cookieOptions['borlabs'] = __('Borlabs Cookie', 'wp-sdtrk');
        }
        
        //Collect Eventlist
        $eventList = array(
            'page_view'=>__('Page View', 'wp-sdtrk'),
            'add_to_cart'=>__('Add to Cart', 'wp-sdtrk'),
            'purchase'=>__('Purchase', 'wp-sdtrk'),
            'sign_up'=>__('Complete registration', 'wp-sdtrk'),
            'generate_lead'=>__('Lead', 'wp-sdtrk'),
            'begin_checkout'=>__('Initiate checkout', 'wp-sdtrk'),
            'view_item'=>__('View Content', 'wp-sdtrk'));
        
        //Collect TimeTracker-Data
        $timeTrackingEnabled = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "trk_time"), "yes") == 0) ? true : false;
        if ($timeTrackingEnabled) {
            $timeTrackingData = wp_sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "trk_time_group");
            // Create array from Data
            foreach ($timeTrackingData as $timeTrackingSet) {
                $eventList['timetracker-'.$timeTrackingSet['trk_time_group_seconds']] = __('Time-Tracker', 'wp-sdtrk').' '.$timeTrackingSet['trk_time_group_seconds'].' '.__('Seconds', 'wp-sdtrk');
            }
        }
        //Collect ScrollTracker-Data
        $scrollTrackingEnabled = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "trk_scroll"), "yes") == 0) ? true : false;
        if ($scrollTrackingEnabled) {
            $scrollTrackingData = wp_sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "trk_scroll_group");
            // Create array from Data
            foreach ($scrollTrackingData as $scrollTrackingSet) {
                $eventList['scrolltracker-'.$scrollTrackingSet['trk_scroll_group_percent']] = __('Scroll-Tracker', 'wp-sdtrk').' '.$scrollTrackingSet['trk_scroll_group_percent'].' '.__('Percent', 'wp-sdtrk');
            }
        }   
        
        //Collect ButtonTracker-Data
        $buttonTrackingEnabled = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "trk_buttons"), "yes") == 0) ? true : false;
        $buttonTrackingMsg = ($buttonTrackingEnabled) ? "" :'<strong>'.__('Note: Button-Click-Tracking is currently disabled in general settings! So this sttings will be ignored', 'wp-sdtrk').'</strong>';
        
        //Collect ItemVisitTracker-Data
        $visibilityTrackingEnabled = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "trk_visibility"), "yes") == 0) ? true : false;
        $visibilityTrackingMsg = ($visibilityTrackingEnabled) ? "" :'<strong>'.__('Note: Item-Visibility-Tracking is currently disabled in general settings! So this sttings will be ignored', 'wp-sdtrk').'</strong>';
        
        
        $fields[] = array(
            'name' => 'basic',
            'title' => __('General', 'wp-sdtrk'),
            'icon' => 'dashicons-admin-generic',
            'fields' => array(
                array(
                    'type' => 'content',
                    'title' => '<h3>' . __('Basic Data Settings', 'wp-sdtrk') . '</h3>',                    
                    'wrap_class' => 'pageHeader',
                    'content' => ''
                ),
                array(
                    'id' => 'brandname',
                    'type' => 'text',
                    'title' => __('Default Brand-Name', 'wp-sdtrk'),
                    'description' => __('This Name is used for several services', 'wp-sdtrk')
                ),
                array(
                    'type' => 'content',
                    'title' => '<h3>' . __('Basic Tracking Settings', 'wp-sdtrk') . '</h3>',
                    'wrap_class' => 'divideHeader',
                    'content' => ''
                ),
                array(
                    'id' => 'trk_fp',
                    'type' => 'switcher',
                    'title' => __('Enable Fingerprinting', 'wp-sdtrk'),
                    'description' => __('Check to fingerprint users (works cookie-less)', 'wp-sdtrk'),
                    'default' => 'no'
                ),
                array(
                    'id' => 'trk_time',
                    'type' => 'switcher',
                    'title' => __('Fire signal-event after time', 'wp-sdtrk'),
                    'description' => __('Check to fire a signal event after time', 'wp-sdtrk'),
                    'default' => 'no'
                ),
                array(
                    'type' => 'group',
                    'id' => 'trk_time_group',
                    'title' => __('Time-Settings', 'wp-sdtrk'),
                    'description' => __('Fire a signal-event after X Seconds', 'wp-sdtrk'),
                    'after' => __('Attention: Every event must be processed! Make sure that the frequency is therefore not too high and not several events are triggered at the same time!', 'wp-sdtrk'),
                    'wrap_class' => 'shifted',
                    'dependency' => array(
                        'trk_time',
                        '!=',
                        'false'
                    ),
                    'options' => array(
                        'repeater' => true,
                        'accordion' => true,
                        'button_title' => __('Add new', 'wp-sdtrk'),
                        'group_title' => __('Time-Settings', 'wp-sdtrk'),
                        'limit' => 50,
                        'sortable' => true,
                        'mode' => 'compact'
                    ),
                    'fields' => array(

                        array(
                            'id' => 'trk_time_group_seconds',
                            'type' => 'number',
                            'min' => '1',
                            'step' => '1',
                            'default' => '10',
                            'max' => '18000',
                            'attributes' => array(
                                // mark this field az title, on type this will change group item title
                                'data-title' => 'title',
                                'placeholder' => __('Seconds to fire after', 'wp-sdtrk')
                            )
                        )
                    )
                ),
                
                array(
                    'id' => 'trk_scroll',
                    'type' => 'switcher',
                    'wrap_class' => 'headsettings',
                    'title' => __('Fire signal-event on scroll-depth', 'wp-sdtrk'),
                    'description' => __('Check to fire a signal event after scroll-depth has been reached', 'wp-sdtrk'),
                    'default' => 'no'
                ),
                array(
                    'type' => 'group',
                    'id' => 'trk_scroll_group',
                    'wrap_class' => 'shifted',
                    'title' => __('Scroll-Settings', 'wp-sdtrk'),
                    'description' => __('Fire signal-event if the user has scrolled to x percent of the page', 'wp-sdtrk'),
                    'after' => __('Attention: Every event must be processed! Make sure that the frequency is therefore not too high and not several events are triggered at the same depth!', 'wp-sdtrk'),
                    'dependency' => array(
                        'trk_scroll',
                        '!=',
                        'false'
                    ),
                    'options' => array(
                        'repeater' => true,
                        'accordion' => true,
                        'button_title' => __('Add new', 'wp-sdtrk'),
                        'group_title' => __('Scroll-Settings', 'wp-sdtrk'),
                        'limit' => 50,
                        'sortable' => true,
                        'mode' => 'compact'
                    ),
                    'fields' => array(
                        
                        array(
                            'id' => 'trk_scroll_group_percent',
                            'type' => 'number',
                            'min' => '1',
                            'step' => '1',
                            'default' => '30',
                            'max' => '100',
                            'attributes' => array(
                                // mark this field az title, on type this will change group item title
                                'data-title' => 'title',
                                'placeholder' => __('Percent to reach', 'wp-sdtrk')
                            )
                        )
                    )
                ),
                array(
                    'id' => 'trk_buttons',
                    'type' => 'switcher',
                    'title' => __('Fire signal-event on button-clicks', 'wp-sdtrk'),
                    'description' => __('Check to fire a signal event after an element has been clicked', 'wp-sdtrk'),
                    'default' => 'no',
                    'after' => __('Attention: In order for clicks to be tracked, the element to be tracked must contain the class trkbtn-TAGNAME-trkbtn. The TAGNAME placeholder can be replaced by any word and will be passed as a parameter', 'wp-sdtrk') . '<br><b style="color:white">' . __('Example:', 'wp-sdtrk') . '</b> ' . htmlentities('<a href="https://example.com" class="trkbtn-mybutton-trkbtn">MyButton</a>')
                ),
                array(
                    'id' => 'trk_visibility',
                    'type' => 'switcher',
                    'title' => __('Fire signal-event on visibility of items', 'wp-sdtrk'),
                    'description' => __('Check to fire a signal event after an element gets visible', 'wp-sdtrk'),
                    'default' => 'no',
                    'after' => __('Attention: In order for tracking to work, the element to be tracked must contain the class watchitm-TAGNAME-watchitm. The TAGNAME placeholder can be replaced by any word and will be passed as a parameter', 'wp-sdtrk') . '<br><b style="color:white">' . __('Example:', 'wp-sdtrk') . '</b> ' . htmlentities('<h2 class="watchitm-mybutton-watchitm">My Headline</h2>')
                )
            )
        );
        $fields[] = array(
            'name' => 'trkservices',
            'title' => __('Tracking Services', 'wp-sdtrk'),
            'icon' => 'dashicons-welcome-view-site',
            'sections' => array(
                array(
                    'name' => 'local',
                    'title' => 'Local',
                    'icon' => 'dashicons-database',
                    'fields' => array(
                        array(
                            'type' => 'content',
                            'title' => '<h3>Local Tracking</h3>',
                            'wrap_class' => 'pageHeader',
                            'content' => '',
                        ),
                        array(
                            'id' => 'local_trk_server',
                            'type' => 'switcher',
                            'title' => __('Activate server based tracking', 'wp-sdtrk'),
                            'description' => __('Check to save hits into the local database', 'wp-sdtrk'),
                            'default' => 'no',
                        ),
                        array(
                            'id' => 'local_trk_server_debug',
                            'type' => 'switcher',
                            'dependency' => array(
                                'local_trk_server',
                                '==',
                                'true'
                            ),
                            'title' => __('Activate Debugging', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to activate %s debugging', 'wp-sdtrk'),'Local'),
                            'default' => 'no'
                        ),
                        array(
                            'type' => 'content',
                            'title' => '<h3>' . __('Feed Sync Settings', 'wp-sdtrk') . '</h3>',
                            'wrap_class' => 'divideHeader',
                            'dependency' => array(
                                'local_trk_server',
                                '==',
                                'true'
                            ),
                            'content' => ''
                        ),
                        array(
                            'id' => 'local_trk_server_xml',
                            'type' => 'switcher',
                            'dependency' => array(
                                'local_trk_server',
                                '==',
                                'true'
                            ),
                            'title' => __('Activate Hit-Feed', 'wp-sdtrk'),
                            'description' => __('Check to activate access to the Live-Hit-Feed', 'wp-sdtrk'),
                            'default' => 'no'
                        ),
                        array(
                            'id' => 'local_trk_server_xml_secret',
                            'type' => 'text',
                            'title' => __('Secret Key', 'wp-sdtrk'),
                            'description' => __('Here you can set a secret key (recommended)', 'wp-sdtrk'),
                            'dependency' => array(
                                'local_trk_server|local_trk_server_xml',
                                '==|==',
                                'true|true'
                            ),
                        ),
                        array(
                            'type' => 'content',
                            'title' => __('Hit-Feed notes', 'wp-sdtrk'),
                            'content' => __('Your feed will be accessible here:', 'wp-sdtrk').' <i>'.$hitFeedUrl.'</i><br>'.__('You can connect GoogleDataStudio with the exclusive Connector explained in the plugins tutorial section:', 'wp-sdtrk'),
                            'dependency' => array(
                                'local_trk_server|local_trk_server_xml',
                                '==|==',
                                'true|true'
                            ),
                        ),
                        array(
                            'type' => 'content',
                            'title' => '<h3>' . __('CSV Sync Settings', 'wp-sdtrk') . '</h3>',
                            'wrap_class' => 'divideHeader',
                            'dependency' => array(
                                'local_trk_server',
                                '==',
                                'true'
                            ),
                            'content' => ''
                        ),
                        array(
                            'id' => 'local_trk_server_csv',
                            'type' => 'switcher',
                            'dependency' => array(
                                'local_trk_server',
                                '==',
                                'true'
                            ),
                            'title' => __('Activate local CSV Sync', 'wp-sdtrk'),
                            'description' => __('Check to activate sync to csv-file', 'wp-sdtrk'),
                            'default' => 'no'
                        ),
                        array(
                            'type' => 'content',
                            'title' => __('CSV-Sync notes', 'wp-sdtrk'),
                            'content' => __('CSV-file will be stored here:', 'wp-sdtrk').' <i><a href="'.$csvFileUrl.'">'.$csvFileUrl.'</a></i><br>'.__('You can connect GoogleDataStudio with this Connector:', 'wp-sdtrk').' <a target="blank" href="https://github.com/googledatastudio/community-connectors/tree/master/fetch-csv">Fetch-CSV</a>',
                            'dependency' => array(
                                'local_trk_server|local_trk_server_csv',
                                '==|==',
                                'true|true'
                            ),                            
                        ),
                        array(
                            'id' => 'local_trk_server_csv_crontime_frequency',
                            'type' => 'switcher',
                            'dependency' => array(
                                'local_trk_server_csv|local_trk_server',
                                '==|==',
                                'true|true'
                            ),
                            'title' => __('Sync hourly', 'wp-sdtrk'),
                            'description' => __('Check to sync per hour instead of the daily base', 'wp-sdtrk'),
                            'default' => 'no'
                        ),
                        array(
                            'id'      => 'local_trk_server_csv_crontime',
                            'type'    => 'range',
                            'dependency' => array(
                                'local_trk_server_csv|local_trk_server',
                                '==|==',
                                'true|true'
                            ),
                            'sanitize' => array(
                                $this,
                                'resetCSVsyncCron'
                            ),
                            'title'   => __('At what hour should the synchronization take place', 'wp-sdtrk'),
                            'default' => '0',
                            'min'     => '0',                                      // optional
                            'max'     => '23',                                     // optional
                            'step'    => '1',                                      // optional
                            'description' => __('Next sync is currently sheduled at', 'wp-sdtrk').' '.Wp_Sdtrk_Helper::wp_sdtrk_TimestampToDate('d.m.Y H:i:s',wp_next_scheduled( 'wp_sdtrk_csvsync_cron' ),'Europe/Berlin'),
                            
                        ),
                        array(
                            'id' => 'local_trk_server_csv_crontime_force',
                            'type' => 'switcher',
                            'title' => __('Sync now', 'wp-sdtrk'),
                            'description' => __('Check to sync data directly after saving settings', 'wp-sdtrk'),
                            'default' => 'no',
                            'dependency' => array(
                                'local_trk_server_csv|local_trk_server',
                                '==|==',
                                'true|true'
                            ),
                            'sanitize' => array(
                                $this,
                                'syncCSVsyncNowCallback'
                            )
                        ),
                        array(
                            'type' => 'content',
                            'title' => '<h3>' . __('Google-Sheets Sync Settings', 'wp-sdtrk') . '</h3>',
                            'wrap_class' => 'divideHeader',
                            'dependency' => array(
                                'local_trk_server',
                                '==',
                                'true'
                            ),
                            'content' => ''
                        ),
                        array(
                            'id' => 'local_trk_server_gsync',
                            'type' => 'switcher',
                            'dependency' => array(
                                'local_trk_server',
                                '==',
                                'true'
                            ),
                            'title' => __('Activate Google Sheets Sync', 'wp-sdtrk'),
                            'description' => __('Check to activate sync to google sheets', 'wp-sdtrk'),
                            'default' => 'no'
                        ),
                        array(
                            'id'      => 'local_trk_server_gsync_crontime',
                            'type'    => 'range',
                            'dependency' => array(
                                'local_trk_server_gsync|local_trk_server',
                                '==|==',
                                'true|true'
                            ),
                            'sanitize' => array(
                                $this,
                                'resetGsyncCron'
                            ),
                            'title'   => __('At what hour should the synchronization take place', 'wp-sdtrk'),
                            'default' => '0',  
                            'min'     => '0',                                      // optional
                            'max'     => '23',                                     // optional
                            'step'    => '1',                                      // optional
                        ),
                        array(
                            'id' => 'local_trk_server_gsync_crontime_force',
                            'type' => 'switcher',
                            'title' => __('Sync now', 'wp-sdtrk'),
                            'description' => __('Check to sync data directly after saving settings', 'wp-sdtrk'),
                            'default' => 'no',
                            'dependency' => array(
                                'local_trk_server_gsync|local_trk_server',
                                '==|==',
                                'true|true'
                            ),
                            'sanitize' => array(
                                $this,
                                'syncGsyncNowCallback'
                            )
                        ),
                        array(
                            'id'          => 'local_trk_server_gsync_cred',
                            'type'        => 'textarea',
                            'title'       => __('O-Auth2 Credentials', 'wp-sdtrk'),
                            'attributes'    => array(
                                'placeholder' => __('Insert your credentials-content here', 'wp-sdtrk'),
                            ),
                            'dependency' => array(
                                'local_trk_server|local_trk_server_gsync',
                                '==|==',
                                'true|true'
                            ),
                            'description' => __('You can get this data from Google Developer Console', 'wp-sdtrk'),
                            'after' => '<strong style="color:white">'.__('Your authorized javascript-origin:', 'wp-sdtrk').'</strong> <i>"'.'https://' . $_SERVER['HTTP_HOST'].'"</i>'.'<br><strong style="color:white">'.__('Your authorized redirect-url:', 'wp-sdtrk').'</strong> <i>"'.'https://' . $_SERVER['HTTP_HOST'] . '/index.php'."?wp-sdtrk=gauth".'"</i>',
                        ),
                        array(
                            'id'      => 'local_trk_server_gsync_authenticate',
                            'type'    => 'button',
                            'title'   => __('Authentication', 'wp-sdtrk'),
                            'options' => array(
                                'href'      => 'https://' . $_SERVER['HTTP_HOST'] . '/index.php?wp-sdtrk=gauth&reauthenticate=1',
                                'target'    => '_self',
                                'value'     => $gauth_btnLabel,
                                'btn-class' => 'exopite-sof-btn',
                            ),
                            'description' => __('Click on the button after you have entered your credentials', 'wp-sdtrk'),
                            'after' => __('You are currently', 'wp-sdtrk').' <span style="color:green">'.$gauth_state.'</span>',
                            'dependency' => array(
                                'local_trk_server|local_trk_server_gsync|local_trk_server_gsync_cred',
                                '==|==|!=',
                                'true|true|'
                            ),
                        ),
                        array(
                            'id' => 'local_trk_server_gsync_sheetId',
                            'type' => 'text',
                            'title' => __('Google Sheet-ID', 'wp-sdtrk'),
                            'description' => __('Insert the target sheet-id from google sheets', 'wp-sdtrk'),
                            'dependency' => array(
                                'local_trk_server|local_trk_server_gsync',
                                '==|==',
                                'true|true'
                            ),
                        ),
                        array(
                            'id' => 'local_trk_server_gsync_tableName',
                            'type' => 'text',
                            'title' => __('Table-Name', 'wp-sdtrk'),
                            'description' => __('Insert the target table-name from your google sheet', 'wp-sdtrk'),
                            'dependency' => array(
                                'local_trk_server|local_trk_server_gsync',
                                '==|==',
                                'true|true'
                            ),
                        ),
                        array(
                            'type' => 'content',
                            'title' => '<h3>' . __('Danger-Zone', 'wp-sdtrk') . '</h3><p style="color:red"><strong>'.__('Be careful with these functions!', 'wp-sdtrk').'</strong></p>',
                            'wrap_class' => 'divideHeader',
                            'content' => ''
                        ),
                        array(
                            'id' => 'local_dng_clear_db',
                            'type' => 'switcher',
                            'title' => __('Clear Database', 'wp-sdtrk'),
                            'description' => __('Check to delete all entries in local database', 'wp-sdtrk'),
                            'default' => 'no',
                            'sanitize' => array(
                                $this,
                                'negateCallback'
                            )
                        ),
                        array(
                            'id' => 'local_dng_clear_db_sure',
                            'type' => 'switcher',
                            'wrap_class' => 'dangerZone',
                            'dependency' => array(
                                'local_dng_clear_db',
                                '==',
                                'true'
                            ),
                            'title' => __('Are you sure?', 'wp-sdtrk'),
                            'description' => __('This cannot be undone!', 'wp-sdtrk'),
                            'default' => 'no',
                            'sanitize' => array(
                                $this,
                                'clearDbCallback'
                            )
                        ),
                        array(
                            'id' => 'local_dng_clear_sync',
                            'type' => 'switcher',
                            'title' => __('Reset google-sheet sync', 'wp-sdtrk'),
                            'description' => __('Check to re-sync all data to google-sheets', 'wp-sdtrk'),
                            'default' => 'no',
                            'sanitize' => array(
                                $this,
                                'negateCallback'
                            )
                        ),
                        array(
                            'id' => 'local_dng_clear_sync_sure',
                            'type' => 'switcher',
                            'wrap_class' => 'dangerZone',
                            'dependency' => array(
                                'local_dng_clear_sync',
                                '==',
                                'true'
                            ),
                            'title' => __('Are you sure?', 'wp-sdtrk'),
                            'description' => __('This cannot be undone!', 'wp-sdtrk'),
                            'default' => 'no',
                            'sanitize' => array(
                                $this,
                                'clearGsyncCallback'
                            )
                        ),
                        
                    )
                ),
                array(
                    'name' => 'facebook',
                    'title' => 'Meta',
                    'icon' => 'dashicons-facebook',
                    'fields' => array(
                        array(
                            'type' => 'content',
                            'title' => '<h3>Meta Tracking</h3>',
                            'wrap_class' => 'pageHeader',
                            'content' => '',
                        ),
                        array(
                            'id' => 'fb_pixelid',
                            'type' => 'text',
                            'title' => 'Meta Pixel-ID',
                            'description' => sprintf( __( 'Insert your %s Pixel-ID', 'wp-sdtrk'),'Meta'),
                        ),                        
                        array(
                            'id' => 'fb_trk_debug',
                            'type' => 'switcher',
                            'dependency' => array(
                                'fb_pixelid',
                                '!=',
                                ''
                            ),
                            'title' => __('Activate Debugging', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to activate %s debugging', 'wp-sdtrk'),'Meta'),
                            'default' => 'no'
                        ),
                        array(
                            'id' => 'fb_trk_server_debug_code',
                            'type' => 'text',
                            'wrap_class' => 'shifted',
                            'dependency' => array(
                                'fb_trk_debug|fb_pixelid',
                                '==|!=',
                                'true|'
                            ),
                            'title' => __('Server Test-Code', 'wp-sdtrk'),
                            'description' => sprintf( __('If you want to debug the events in the %s events-manager, you have to enter the current test-code!', 'wp-sdtrk'),'Meta'),
                            'after' => sprintf( __('You can get the Test-Code within the %s events-manager', 'wp-sdtrk'),'Meta'),
                        ),
                        array(
                            'type' => 'content',
                            'title' => '<h3>' . __('Browser based Tracking', 'wp-sdtrk') . '</h3>',
                            'wrap_class' => 'divideHeader',
                            'dependency' => array(
                                'fb_pixelid',
                                '!=',
                                ''
                            ),
                            'content' => ''
                        ),
                        array(
                            'id' => 'fb_trk_browser',
                            'type' => 'switcher',
                            'title' => __('Activate browser based tracking', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to fire %s browser pixel', 'wp-sdtrk'),'Meta'),
                            'default' => 'no',
                            'dependency' => array(
                                'fb_pixelid',
                                '!=',
                                ''
                            )
                        ),
                        array(
                            'id' => 'fb_trk_browser_cookie_service',
                            'type' => 'radio',
                            'dependency' => array(
                                'fb_trk_browser|fb_pixelid',
                                '!=|!=',
                                'false|'
                            ),
                            'title' => __('Choose cookie consent behavior', 'wp-sdtrk'),
                            'options' => $cookieOptions,
                            'default' => 'none', // optional
                            'style' => 'fancy' // optional
                        ),
                        array(
                            'id' => 'fb_trk_browser_cookie_id',
                            'type' => 'text',
                            'wrap_class' => 'shifted',
                            'dependency' => array(
                                'fb_trk_browser_cookie_service_borlabs|fb_trk_browser|fb_pixelid',
                                '==|!=|!=',
                                'true|false|'
                            ),
                            'title' => __('Cookie ID', 'wp-sdtrk'),
                            'description' => __('You can get this information in the Plugins Consent-Settings', 'wp-sdtrk'),
                            'after' => '<p style="color:#57b957">' . __('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk') . '</p><p><code style="font-style: italic;">' . htmlentities('<script>wp_sdtrk_backload_fb_b();</script>') . '</code></p>'
                        ),
                        array(
                            'type' => 'content',
                            'title' => '<h3>' . __('Server based Tracking', 'wp-sdtrk') . '</h3>',
                            'wrap_class' => 'divideHeader',
                            'dependency' => array(
                                'fb_pixelid',
                                '!=',
                                ''
                            ),
                            'content' => ''
                        ),
                        array(
                            'id' => 'fb_trk_server',
                            'type' => 'switcher',
                            'title' => __('Activate server based tracking', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to send %s-Events server-side to the API', 'wp-sdtrk'),'Meta'),
                            'default' => 'no',
                            'dependency' => array(
                                'fb_pixelid',
                                '!=',
                                ''
                            )
                        ),
                        array(
                            'id' => 'fb_trk_server_token',
                            'type' => 'text',
                            'dependency' => array(
                                'fb_trk_server|fb_pixelid',
                                '==|!=',
                                'true|'
                            ),
                            'title' => __('API Token', 'wp-sdtrk'),
                            'description' => sprintf( __( 'You can get the token within the %s %s settings', 'wp-sdtrk'),'Meta','Events-Manager'),
                        ),
                        array(
                            'id' => 'fb_trk_server_cookie_service',
                            'type' => 'radio',
                            'dependency' => array(
                                'fb_trk_server|fb_pixelid',
                                '!=|!=',
                                'false|'
                            ),
                            'title' => __('Choose cookie consent behavior', 'wp-sdtrk'),
                            'options' => $cookieOptions,
                            'default' => 'none', // optional
                            'style' => 'fancy' // optional
                        ),
                        array(
                            'id' => 'fb_trk_server_cookie_id',
                            'type' => 'text',
                            'wrap_class' => 'shifted',
                            'dependency' => array(
                                'fb_trk_server_cookie_service_borlabs|fb_trk_server|fb_pixelid',
                                '==|!=|!=',
                                'true|false|'
                            ),
                            'title' => __('Cookie ID', 'wp-sdtrk'),
                            'description' => __('You can get this information in the Plugins Consent-Settings', 'wp-sdtrk'),
                            'after' => '<p style="color:#57b957">' . __('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk') . '</p><p><code style="font-style: italic;">' . htmlentities('<script>wp_sdtrk_backload_fb_s();</script>') . '</code></p>'
                        )
                    )
                ),
                array(
                    'name' => 'google',
                    'title' => 'Google',
                    'icon' => 'dashicons-google',
                    'fields' => array(
                        array(
                            'type' => 'content',
                            'title' => '<h3>Google Tracking</h3>',
                            'wrap_class' => 'pageHeader',
                            'content' => '',
                        ),
                        array(
                            'id' => 'ga_measurement_id',
                            'type' => 'text',
                            'title' => 'Google Measurement ID',
                            'description' => sprintf( __( 'Insert your %s Pixel-ID', 'wp-sdtrk'),'Google'),
                        ),
                        array(
                            'id' => 'ga_trk_debug',
                            'type' => 'switcher',
                            'dependency' => array(
                                'ga_measurement_id',
                                '!=',
                                ''
                            ),
                            'title' => __('Activate Debugging', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to activate %s debugging', 'wp-sdtrk'),'Google'),
                            'default' => 'no'
                        ),
                        array(
                            'id' => 'ga_trk_debug_live',
                            'type' => 'switcher',
                            'wrap_class' => 'shifted',
                            'dependency' => array(
                                'ga_measurement_id|ga_trk_debug',
                                '!=|==',
                                '|true'
                            ),
                            'title' => __('Debug in live-view', 'wp-sdtrk'),
                            'description' => __('Check to show debug-hits in the google analytics realtime report', 'wp-sdtrk'),
                            'default' => 'no'
                        ),
                        array(
                            'type' => 'content',
                            'wrap_class' => 'divideHeader',
                            'dependency' => array(
                                'ga_measurement_id',
                                '!=',
                                ''
                            ),
                            'title' => '<h3>' . __('Browser based Tracking', 'wp-sdtrk') . '</h3>',
                            'content' => ''
                        ),
                        array(
                            'id' => 'ga_trk_browser',
                            'type' => 'switcher',
                            'dependency' => array(
                                'ga_measurement_id',
                                '!=',
                                ''
                            ),
                            'title' => __('Activate browser based tracking', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to fire %s browser pixel', 'wp-sdtrk'),'Google'),
                            'default' => 'no'
                        ),
                        array(
                            'id' => 'ga_trk_browser_cookie_service',
                            'type' => 'radio',
                            'dependency' => array(
                                'ga_trk_browser|ga_measurement_id',
                                '!=|!=',
                                'false|'
                            ),
                            'title' => __('Choose cookie consent behavior', 'wp-sdtrk'),
                            'options' => $cookieOptions,
                            'default' => 'none', // optional
                            'style' => 'fancy' // optional
                        ),
                        array(
                            'id' => 'ga_trk_browser_cookie_id',
                            'type' => 'text',
                            'wrap_class' => 'shifted',
                            'dependency' => array(
                                'ga_trk_browser_cookie_service_borlabs|ga_trk_browser|ga_measurement_id',
                                '==|!=|!=',
                                'true|false|'
                            ),
                            'title' => __('Cookie ID', 'wp-sdtrk'),
                            'description' => __('You can get this information in the Plugins Consent-Settings', 'wp-sdtrk'),
                            'after' => '<p style="color:#57b957">' . __('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk') . '</p><p><code style="font-style: italic;">' . htmlentities('<script>wp_sdtrk_backload_ga_b();</script>') . '</code></p>'
                        ),
                        array(
                            'type' => 'content',
                            'wrap_class' => 'divideHeader',
                            'title' => '<h3>' . __('Server based Tracking', 'wp-sdtrk') . '</h3>',
                            'content' => '',
                            'dependency' => array(
                                'ga_measurement_id',
                                '!=',
                                ''
                            )
                        ),
                        array(
                            'id' => 'ga_trk_server',
                            'type' => 'switcher',
                            'dependency' => array(
                                'ga_measurement_id',
                                '!=',
                                ''
                            ),
                            'title' => __('Activate server based tracking', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to send %s-Events server-side to the API', 'wp-sdtrk'),'Google'),
                            'default' => 'no'
                        ),
                        array(
                            'id' => 'ga_trk_server_token',
                            'type' => 'text',
                            'dependency' => array(
                                'ga_measurement_id|ga_trk_server',
                                '!=|==',
                                '|true'
                            ),
                            'title' => __('API Token', 'wp-sdtrk'),
                            'description' => sprintf( __( 'You can get the token within the %s %s settings', 'wp-sdtrk'),'Google','Datastream'),
                        ),
                        array(
                            'id' => 'ga_trk_server_cookie_service',
                            'type' => 'radio',
                            'dependency' => array(
                                'ga_measurement_id|ga_trk_server',
                                '!=|!=',
                                '|false'
                            ),
                            'title' => __('Choose cookie consent behavior', 'wp-sdtrk'),
                            'options' => $cookieOptions,
                            'default' => 'none', // optional
                            'style' => 'fancy' // optional
                        ),
                        array(
                            'id' => 'ga_trk_server_cookie_id',
                            'type' => 'text',
                            'wrap_class' => 'shifted',
                            'dependency' => array(
                                'ga_trk_server_cookie_service_borlabs|ga_trk_server|ga_measurement_id',
                                '==|!=|!=',
                                'true|false|'
                            ),
                            'title' => __('Cookie ID', 'wp-sdtrk'),
                            'description' => __('You can get this information in the Plugins Consent-Settings', 'wp-sdtrk'),
                            'after' => '<p style="color:#57b957">' . __('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk') . '</p><p><code style="font-style: italic;">' . htmlentities('<script>wp_sdtrk_backload_ga_s();</script>') . '</code></p>'
                        )
                    )
                ),
                array(
                    'name' => 'tiktok',
                    'title' => 'TikTok',
                    'icon' => 'dashicons-embed-audio',
                    'fields' => array(
                        array(
                            'type' => 'content',
                            'title' => '<h3>TikTok Tracking</h3>',
                            'wrap_class' => 'pageHeader',
                            'content' => '',
                        ),
                        array(
                            'id' => 'tt_pixelid',
                            'type' => 'text',
                            'title' => 'TikTok Pixel-ID',
                            'description' => sprintf( __( 'Insert your %s Pixel-ID', 'wp-sdtrk'),'TikTok'),
                        ),
                        array(
                            'id' => 'tt_trk_debug',
                            'type' => 'switcher',
                            'dependency' => array(
                                'tt_pixelid',
                                '!=',
                                ''
                            ),
                            'title' => __('Activate Debugging', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to activate %s debugging', 'wp-sdtrk'),'TikTok'),
                            'default' => 'no'
                        ),
                        array(
                            'id' => 'tt_trk_server_debug_code',
                            'type' => 'text',
                            'wrap_class' => 'shifted',
                            'dependency' => array(
                                'tt_trk_debug|tt_pixelid',
                                '==|!=',
                                'true|'
                            ),
                            'title' => __('Server Test-Code', 'wp-sdtrk'),
                            'description' => sprintf( __('If you want to debug the events in the %s events-manager, you have to enter the current test-code!', 'wp-sdtrk'),'TikTok'),
                            'after' => sprintf( __('You can get the Test-Code within the %s events-manager', 'wp-sdtrk'),'TikTok'),
                        ),
                        array(
                            'type' => 'content',
                            'wrap_class' => 'divideHeader',
                            'title' => '<h3>' . __('Browser based Tracking', 'wp-sdtrk') . '</h3>',
                            'content' => '',
                            'dependency' => array(
                                'tt_pixelid',
                                '!=',
                                ''
                            )
                        ),
                        array(
                            'id' => 'tt_trk_browser',
                            'type' => 'switcher',
                            'title' => __('Activate browser based tracking', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to fire %s browser pixel', 'wp-sdtrk'),'TikTok'),
                            'default' => 'no',
                            'dependency' => array(
                                'tt_pixelid',
                                '!=',
                                ''
                            )
                        ),
                        array(
                            'id' => 'tt_trk_browser_cookie_service',
                            'type' => 'radio',
                            'dependency' => array(
                                'tt_trk_browser|tt_pixelid',
                                '!=|!=',
                                'false|'
                            ),
                            'title' => __('Choose cookie consent behavior', 'wp-sdtrk'),
                            'options' => $cookieOptions,
                            'default' => 'none', // optional
                            'style' => 'fancy' // optional
                        ),
                        array(
                            'id' => 'tt_trk_browser_cookie_id',
                            'type' => 'text',
                            'wrap_class' => 'shifted',
                            'dependency' => array(
                                'tt_trk_browser_cookie_service_borlabs|tt_trk_browser|tt_pixelid',
                                '==|!=|!=',
                                'true|false|'
                            ),
                            'title' => __('Cookie ID', 'wp-sdtrk'),
                            'description' => __('You can get this information in the Plugins Consent-Settings', 'wp-sdtrk'),
                            'after' => '<p style="color:#57b957">' . __('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk') . '</p><p><code style="font-style: italic;">' . htmlentities('<script>wp_sdtrk_backload_tt_b();</script>') . '</code></p>'
                        ),
                        array(
                            'type' => 'content',
                            'wrap_class' => 'divideHeader',
                            'title' => '<h3>' . __('Server based Tracking', 'wp-sdtrk') . '</h3>',
                            'content' => '',
                            'dependency' => array(
                                'tt_pixelid',
                                '!=',
                                ''
                            )
                        ),
                        array(
                            'id' => 'tt_trk_server',
                            'type' => 'switcher',
                            'title' => __('Activate server based tracking', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to send %s-Events server-side to the API', 'wp-sdtrk'),'TikTok'),
                            'default' => 'no',
                            'dependency' => array(
                                'tt_pixelid',
                                '!=',
                                ''
                            )
                        ),
                        array(
                            'id' => 'tt_trk_server_token',
                            'type' => 'text',
                            'dependency' => array(
                                'tt_trk_server|tt_pixelid',
                                '==|!=',
                                'true|'
                            ),
                            'title' => __('API Token', 'wp-sdtrk'),
                            'description' => sprintf( __( 'You can get the token within the %s %s settings', 'wp-sdtrk'),'TikTok','Events-Manager'),
                        ),
                        array(
                            'id' => 'tt_trk_server_cookie_service',
                            'type' => 'radio',
                            'dependency' => array(
                                'tt_trk_server|tt_pixelid',
                                '!=|!=',
                                'false|'
                            ),
                            'title' => __('Choose cookie consent behavior', 'wp-sdtrk'),
                            'options' => $cookieOptions,
                            'default' => 'none', // optional
                            'style' => 'fancy' // optional
                        ),
                        array(
                            'id' => 'tt_trk_server_cookie_id',
                            'type' => 'text',
                            'wrap_class' => 'shifted',
                            'dependency' => array(
                                'tt_trk_server_cookie_service_borlabs|tt_trk_server|tt_pixelid',
                                '==|!=|!=',
                                'true|false|'
                            ),
                            'title' => __('Cookie ID', 'wp-sdtrk'),
                            'description' => __('You can get this information in the Plugins Consent-Settings', 'wp-sdtrk'),
                            'after' => '<p style="color:#57b957">' . __('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk') . '</p><p><code style="font-style: italic;">' . htmlentities('<script>wp_sdtrk_backload_tt_s();</script>') . '</code></p>'
                        )
                    )
                ),
                array(
                    'name' => 'linkedin',
                    'title' => 'LinkedIn',
                    'icon' => 'dashicons-linkedin',
                    'fields' => array(
                        array(
                            'type' => 'content',
                            'title' => '<h3>LinkedIn Tracking</h3>',
                            'wrap_class' => 'pageHeader',
                            'content' => '',
                        ),
                        array(
                            'id' => 'lin_pixelid',
                            'type' => 'text',
                            'title' => 'LinkedIn Partner-ID',
                            'description' => sprintf( __( 'Insert your %s Pixel-ID', 'wp-sdtrk'),'LinkedIn'),
                        ),
                        array(
                            'id' => 'lin_trk_debug',
                            'type' => 'switcher',
                            'dependency' => array(
                                'lin_pixelid',
                                '!=',
                                ''
                            ),
                            'title' => __('Activate Debugging', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to activate %s debugging', 'wp-sdtrk'),'LinkedIn'),
                            'default' => 'no'
                        ),
                        array(
                            'type' => 'content',
                            'wrap_class' => 'divideHeader',
                            'title' => '<h3>' . __('Browser based Tracking', 'wp-sdtrk') . '</h3>',
                            'content' => '',
                            'dependency' => array(
                                'lin_pixelid',
                                '!=',
                                ''
                            )
                        ),
                        array(
                            'id' => 'lin_trk_browser',
                            'type' => 'switcher',
                            'title' => __('Activate browser based tracking', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to fire %s browser pixel', 'wp-sdtrk'),'LinkedIn'),
                            'default' => 'no',
                            'dependency' => array(
                                'lin_pixelid',
                                '!=',
                                ''
                            )
                        ),
                        array(
                            'id' => 'lin_trk_browser_cookie_service',
                            'type' => 'radio',
                            'dependency' => array(
                                'lin_trk_browser|lin_pixelid',
                                '!=|!=',
                                'false|'
                            ),
                            'title' => __('Choose cookie consent behavior', 'wp-sdtrk'),
                            'options' => $cookieOptions,
                            'default' => 'none', // optional
                            'style' => 'fancy' // optional
                        ),
                        array(
                            'id' => 'lin_trk_browser_cookie_id',
                            'type' => 'text',
                            'wrap_class' => 'shifted',
                            'dependency' => array(
                                'lin_trk_browser_cookie_service_borlabs|lin_trk_browser|lin_pixelid',
                                '==|!=|!=',
                                'true|false|'
                            ),
                            'title' => __('Cookie ID', 'wp-sdtrk'),
                            'description' => __('You can get this information in the Plugins Consent-Settings', 'wp-sdtrk'),
                            'after' => '<p style="color:#57b957">' . __('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk') . '</p><p><code style="font-style: italic;">' . htmlentities('<script>wp_sdtrk_backload_lin_b();</script>') . '</code></p>'
                        ),
                        array(
                            'type' => 'custom_userinputgroup',
                            'id' => 'lin_trk_map',
                            'dependency' => array(
                                'lin_pixelid|lin_trk_browser',
                                '!=|!=',
                                '|false'
                            ),
                            'title' => __('Conversion-Mapping', 'wp-sdtrk'),
                            'description' => __('Map linkedIn custom conversions', 'wp-sdtrk'),
                            'options' => array(
                                'repeater' => true,
                                'accordion' => false,
                                'button_title' => __('Map new', 'wp-sdtrk'),
                                'group_title' => __('Conversion-Mappings', 'wp-sdtrk'),
                                'limit' => 100,
                                'cloneable' => false,
                                'mode' => 'compact', // only repeater
                            ),
                            'fields' => array(                                
                                array(
                                    'id' => 'lin_trk_map_event',
                                    'type' => 'select',
                                    'title' => __('Event', 'wp-sdtrk'),
                                    'options'        => $eventList,
                                    'default_option' => __('Select event to be mapped', 'wp-sdtrk'),     // optional
                                ),
                                array(
                                    'id' => 'lin_trk_map_event_lin_convid',
                                    'type' => 'text',
                                    'title' => __('LinkedIn Conversion-ID', 'wp-sdtrk'),
                                ),  
                                array(
                                    'type' => 'custom_userinputgroup',
                                    'id' => 'lin_trk_map_event_rules',
                                    'title' => __('Mapping rules', 'wp-sdtrk'),
                                    'description' => __('Combine several parameters that must ALL exist for this event to be triggered', 'wp-sdtrk'),
                                    'options' => array(
                                        'repeater' => true,
                                        'accordion' => false,
                                        'button_title' => __('Add rule', 'wp-sdtrk'),
                                        'group_title' => __('Rules', 'wp-sdtrk'),
                                        'limit' => 100,
                                        'cloneable' => false,
                                        'mode' => 'compact', // only repeater
                                    ),
                                    'fields' => array(
                                        array(
                                                'id' => 'lin_trk_map_event_rules_param',
                                                'type' => 'select',
                                                'title' => __('Attribute', 'wp-sdtrk'),
                                                'options'        => array(
                                                    'prodid' => __('Product-ID', 'wp-sdtrk'),
                                                    'prodname' => __('Product-Name', 'wp-sdtrk')                                                    
                                                ),
                                                'default_option' => __('Please select an attribute...', 'wp-sdtrk'),     // optional                                        
                                        ),
                                        
                                        array(
                                            'id' => 'lin_trk_map_event_rules_value',
                                            'type' => 'text',
                                            'title' => __('Value', 'wp-sdtrk'),
                                            'attributes' => array(
                                                'placeholder' => __('Leave blank for all', 'wp-sdtrk')
                                            ),                                            
                                        )
                                    )
                                )
                            )
                        ),
                        array(
                            'type' => 'custom_userinputgroup',
                            'id' => 'lin_trk_btnmap',
                            'dependency' => array(
                                'lin_pixelid|lin_trk_browser',
                                '!=|!=',
                                '|false'
                            ),
                            'title' => __('Conversion-Button-Mapping', 'wp-sdtrk'),
                            'description' => __('Map linkedIn custom conversions to button-clicks', 'wp-sdtrk').'<br>'.$buttonTrackingMsg,
                            'options' => array(
                                'repeater' => true,
                                'accordion' => false,
                                'button_title' => __('Map new', 'wp-sdtrk'),
                                'group_title' => __('Conversion-Button-Mappings', 'wp-sdtrk'),
                                'limit' => 100,
                                'cloneable' => false,
                                'mode' => 'compact', // only repeater
                            ),
                            'fields' => array(
                                array(
                                    'id' => 'lin_trk_map_btnevent_lin_btnTag',
                                    'type' => 'text',
                                    'title' => __('Button-Tag', 'wp-sdtrk'),
                                ),
                                array(
                                    'id' => 'lin_trk_map_btnevent_lin_convid',
                                    'type' => 'text',
                                    'title' => __('LinkedIn Conversion-ID', 'wp-sdtrk'),
                                ),
                            )
                        ),
                        array(
                            'type' => 'custom_userinputgroup',
                            'id' => 'lin_trk_ivmap',
                            'dependency' => array(
                                'lin_pixelid|lin_trk_browser',
                                '!=|!=',
                                '|false'
                            ),
                            'title' => __('Item-Visibility-Mapping', 'wp-sdtrk'),
                            'description' => __('Map linkedIn custom conversions to item visibiliy', 'wp-sdtrk').'<br>'.$visibilityTrackingMsg,
                            'options' => array(
                                'repeater' => true,
                                'accordion' => false,
                                'button_title' => __('Map new', 'wp-sdtrk'),
                                'group_title' => __('Item-Visibility-Mappings', 'wp-sdtrk'),
                                'limit' => 100,
                                'cloneable' => false,
                                'mode' => 'compact', // only repeater
                            ),
                            'fields' => array(
                                array(
                                    'id' => 'lin_trk_map_ivevent_lin_ivTag',
                                    'type' => 'text',
                                    'title' => __('Item-Tag', 'wp-sdtrk'),
                                ),
                                array(
                                    'id' => 'lin_trk_map_ivevent_lin_convid',
                                    'type' => 'text',
                                    'title' => __('LinkedIn Conversion-ID', 'wp-sdtrk'),
                                ),
                            )
                        ),
                    )
                ),
                array(
                    'name' => 'funnelytics',
                    'title' => 'Funnelytics',
                    'icon' => 'dashicons-chart-area',
                    'fields' => array(
                        array(
                            'type' => 'content',
                            'title' => '<h3>Funnelytics Tracking</h3>',
                            'wrap_class' => 'pageHeader',
                            'content' => '',
                        ),
                        array(
                            'id' => 'fl_tracking_id',
                            'type' => 'text',
                            'title' => 'Funnelytics Pixel-ID',
                            'description' => sprintf( __( 'Insert your %s Pixel-ID', 'wp-sdtrk'),'Funnelytics'),
                            'after' => sprintf( __('%sWhat is %s?%s', 'wp-sdtrk'),'<a target="blank" href="https://funnelytics.io/">','Funnelytics','</a>'),
                        ),
                        array(
                            'id' => 'fl_trk_debug',
                            'type' => 'switcher',
                            'dependency' => array(
                                'fl_tracking_id',
                                '!=',
                                ''
                            ),
                            'title' => __('Activate Debugging', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to activate %s debugging', 'wp-sdtrk'),'Funnelytics'),
                            'default' => 'no'
                        ),
                        array(
                            'type' => 'content',
                            'wrap_class' => 'divideHeader',
                            'dependency' => array(
                                'fl_tracking_id',
                                '!=',
                                ''
                            ),
                            'title' => '<h3>' . __('Browser based Tracking', 'wp-sdtrk') . '</h3>',
                            'content' => ''
                        ),
                        array(
                            'id' => 'fl_trk_browser',
                            'type' => 'switcher',
                            'dependency' => array(
                                'fl_tracking_id',
                                '!=',
                                ''
                            ),
                            'title' => __('Activate browser based tracking', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to fire %s browser pixel', 'wp-sdtrk'),'Funnelytics'),
                            'default' => 'no'
                        ),
                        array(
                            'id' => 'fl_trk_browser_cookie_service',
                            'type' => 'radio',
                            'dependency' => array(
                                'fl_trk_browser|fl_tracking_id',
                                '!=|!=',
                                'false|'
                            ),
                            'title' => __('Choose cookie consent behavior', 'wp-sdtrk'),
                            'options' => $cookieOptions,
                            'default' => 'none', // optional
                            'style' => 'fancy' // optional
                        ),
                        array(
                            'id' => 'fl_trk_browser_cookie_id',
                            'type' => 'text',
                            'wrap_class' => 'shifted',
                            'dependency' => array(
                                'fl_trk_browser_cookie_service_borlabs|fl_trk_browser|fl_tracking_id',
                                '==|!=|!=',
                                'true|false|'
                            ),
                            'title' => __('Cookie ID', 'wp-sdtrk'),
                            'description' => __('You can get this information in the Plugins Consent-Settings', 'wp-sdtrk'),
                            'after' => '<p style="color:#57b957">' . __('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk') . '</p><p><code style="font-style: italic;">' . htmlentities('<script>wp_sdtrk_backload_fl_b();</script>') . '</code></p>'
                        )
                    )
                ),
                array(
                    'name' => 'mautic',
                    'title' => 'Mautic',
                    'icon' => 'dashicons-email-alt',
                    'fields' => array(
                        array(
                            'type' => 'content',
                            'title' => '<h3>Mautic Tracking</h3>',
                            'wrap_class' => 'pageHeader',
                            'content' => '',
                        ),
                        array(
                            'id' => 'mtc_tracking_id',
                            'type' => 'text',
                            'title' => 'Mautic Base URL',
                            'description' => __('Insert the base-url of your mautic installation', 'wp-sdtrk'),
                            'after' => sprintf( __('%sWhat is %s?%s', 'wp-sdtrk'),'<a target="blank" href="https://www.mautic.org/">','Mautic','</a>'),
                        ),
                        array(
                            'id' => 'mtc_trk_debug',
                            'type' => 'switcher',
                            'dependency' => array(
                                'mtc_tracking_id',
                                '!=',
                                ''
                            ),
                            'title' => __('Activate Debugging', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to activate %s debugging', 'wp-sdtrk'),'Mautic'),
                            'default' => 'no'
                        ),
                        array(
                            'type' => 'content',
                            'wrap_class' => 'divideHeader',
                            'dependency' => array(
                                'mtc_tracking_id',
                                '!=',
                                ''
                            ),
                            'title' => '<h3>' . __('Browser based Tracking', 'wp-sdtrk') . '</h3>',
                            'content' => ''
                        ),
                        array(
                            'id' => 'mtc_trk_browser',
                            'type' => 'switcher',
                            'dependency' => array(
                                'mtc_tracking_id',
                                '!=',
                                ''
                            ),
                            'title' => __('Activate browser based tracking', 'wp-sdtrk'),
                            'description' => sprintf( __( 'Check to fire %s browser pixel', 'wp-sdtrk'),'Mautic'),
                            'default' => 'no'
                        ),
                        array(
                            'id' => 'mtc_trk_browser_cookie_service',
                            'type' => 'radio',
                            'dependency' => array(
                                'mtc_trk_browser|mtc_tracking_id',
                                '!=|!=',
                                'false|'
                            ),
                            'title' => __('Choose cookie consent behavior', 'wp-sdtrk'),
                            'options' => $cookieOptions,
                            'default' => 'none', // optional
                            'style' => 'fancy' // optional
                        ),
                        array(
                            'id' => 'mtc_trk_browser_cookie_id',
                            'type' => 'text',
                            'wrap_class' => 'shifted',
                            'dependency' => array(
                                'mtc_trk_browser_cookie_service_borlabs|mtc_trk_browser|mtc_tracking_id',
                                '==|!=|!=',
                                'true|false|'
                            ),
                            'title' => __('Cookie ID', 'wp-sdtrk'),
                            'description' => __('You can get this information in the Plugins Consent-Settings', 'wp-sdtrk'),
                            'after' => '<p style="color:#57b957">' . __('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk') . '</p><p><code style="font-style: italic;">' . htmlentities('<script>wp_sdtrk_backload_mtc_b();</script>') . '</code></p>'
                        )
                    )
                )
            )
        );

        $fields[] = array(
            'name' => 'datasources',
            'title' => __('Data Sources', 'wp-sdtrk'),
            'icon' => 'dashicons-networking',
            'sections' => array(
                array(
                    'name' => 'digistore',
                    'title' => 'Digistore24',
                    'icon' => 'dashicons-database',
                    'fields' => array(
                        array(
                            'type' => 'content',
                            'title' => '<h3>Digistore24</h3>',
                            'wrap_class' => 'pageHeader',
                            'content' => '',
                        ),
                        array(
                            'id' => 'ds24_encrypt_data',
                            'type' => 'switcher',
                            'title' => sprintf( __('Activate %s data-decryption', 'wp-sdtrk'),'Digistore24'),
                            'description' => sprintf( __('Check to decrypt GET-Parameter from %s before handling', 'wp-sdtrk'),'Digistore24'),
                            'default' => 'no'
                        ),
                        array(
                            'id' => 'ds24_encrypt_data_key',
                            'type' => 'text',
                            'wrap_class' => 'shifted',
                            'dependency' => array(
                                'ds24_encrypt_data',
                                '==',
                                'true'
                            ),
                            'title' => __('ThankYou-Key', 'wp-sdtrk'),
                            'description' => __('Please enter the ThankYou-Key which you have set in Digistore24', 'wp-sdtrk')
                        )
                    )
                )
            )
        );

        $paramNameString = '<b style="color:white">' . __('Parameter-Name(s)', 'wp-sdtrk') . ':</b> ';
        $acceptsString = '<b style="color:white">' . __('Accepts', 'wp-sdtrk') . ':</b> ';
        $exampleString = '<b style="color:white">' . __('Example', 'wp-sdtrk') . ':</b> ';
        $exampleDomain = get_home_url();

        $fields[] = array(
            'name' => 'tutorials',
            'title' => __('Tutorials', 'wp-sdtrk'),
            'icon' => 'dashicons-sos',
            'fields' => array(
                array(
                    'type' => 'content',
                    'title' => '<h3>' . __('Tutorials', 'wp-sdtrk') . '</h3>',
                    'wrap_class' => 'pageHeader',
                    'content' => '',
                ),
                array(
                    'type' => 'content',
                    'wrap_class' => 'divideHeader',
                    'title' => '<h3>' . __('Video Tutorials', 'wp-sdtrk') . '</h3>',
                    'content' => ''
                ),
                array(
                    'type' => 'content',
                    'wrap_class' => 'divideHeader',
                    'title' => __('Setup Tutorial', 'wp-sdtrk'),
                    'content' => '<iframe src="https://player.vimeo.com/video/587429111?h=6660e0f5f6" width="640" height="360" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>'
                ),
                array(
                    'type' => 'content',
                    'wrap_class' => 'divideHeader',
                    'title' => '<h3>' . __('Supported GET-Parameters', 'wp-sdtrk') . '</h3><br><strong style="color:white">' . __('Note', 'wp-sdtrk') . ':</strong> ' . __('Enable "appending order details to thank you URL" on services like Digistore24 so that the parameters below are passed automatically.', 'wp-sdtrk'),
                    'content' => ''
                ),
                array(
                    'type' => 'content',
                    'title' => __('Transaction-ID', 'wp-sdtrk'),
                    'content' => '<p>' . $paramNameString . 'order_id</p><p>' . $acceptsString . 'string</p><p>' . $exampleString . '<i>' . $exampleDomain . '?<b>order_id=9854</b></p></i>'
                ),
                array(
                    'type' => 'content',
                    'title' => __('Product-ID', 'wp-sdtrk'),
                    'content' => '<p>' . $paramNameString . 'prodid | product_id</p><p>' . $acceptsString . 'string</p><p>' . $exampleString . '<i>' . $exampleDomain . '?<b>prodid=1337</b></p></i>'
                ),
                array(
                    'type' => 'content',
                    'title' => __('Product-Name', 'wp-sdtrk'),
                    'content' => '<p>' . $paramNameString . 'product_name</p><p>' . $acceptsString . 'string</p><p>' . $exampleString . '<i>' . $exampleDomain . '?<b>product_name=newproduct</b></p></i>'
                ),
                array(
                    'type' => 'content',
                    'title' => __('Value', 'wp-sdtrk'),
                    'content' => '<p>' . $paramNameString . 'value | net_amount | amount</p><p>' . $acceptsString . 'number</p><p>' . $exampleString . '<i>' . $exampleDomain . '?<b>value=99</b></p></i>'
                ),
                array(
                    'type' => 'content',
                    'title' => __('Type', 'wp-sdtrk'),
                    'content' => '<p>' . $paramNameString . 'type</p><p>' . $acceptsString . 'AddToCart | Purchase | CompleteRegistration | Lead | InitiateCheckout | ViewContent</p><p>' . $exampleString . '<i>' . $exampleDomain . '?<b>type=Lead</b></p></i>'
                ),
                array(
                    'type' => 'content',
                    'title' => __('UTM', 'wp-sdtrk'),
                    'content' => '<p>' . $paramNameString . 'utm_source | utm_campaign | utm_term | utm_medium | utm_content</p><p>' . $acceptsString . 'string</p><p>' . $exampleString . '<i>' . $exampleDomain . '?<b>utm_source=facebook&utm_medium=cpc</b></p></i><p><b style="color:white">' . __('Note', 'wp-sdtrk') . ':</b> ' . __('UTM parameters are stored in cookies and automatically passed on further visits!', 'wp-sdtrk') . '</p>'
                )
            )
        );
        return $fields;
    }

    public function add_style_to_admin_head()
    {
        global $post_type;
        if ('test' == $post_type) {
            ?>
<style type="text/css">
.column-thumbnail {
	width: 80px !important;
}

.column-title {
	width: 30% !important;
}
</style>
<?php
        }
    }

    /**
     * ******************************************
     * RUN CODE ON PLUGIN UPGRADE AND ADMIN NOTICE
     *
     * @tutorial run_code_on_plugin_upgrade_and_admin_notice.php
     */
    /**
     * This function runs when WordPress completes its upgrade process
     * It iterates through each plugin updated to see if ours is included
     *
     * @param $upgrader_object Array
     * @param $options Array
     * @link https://catapultthemes.com/wordpress-plugin-update-hook-upgrader_process_complete/
     */
    public function upgrader_process_complete($upgrader_object, $options)
    {

        // If an update has taken place and the updated type is plugins and the plugins element exists
        if ($options['action'] == 'update' && $options['type'] == 'plugin' && isset($options['plugins'])) {

            // Iterate through the plugins being updated and check if ours is there
            foreach ($options['plugins'] as $plugin) {
                if ($plugin == WP_SDTRK_BASE_NAME) {

                    // Shedule update message
                    Wp_Sdtrk_Helper::wp_sdtrk_sheduleNotice(__('Thanks for updating', 'wp-sdtrk'),"success");
                }
            }
        }
    }
    
    /**
     * Show sheduled Messages
     */
    public function wpsdtrk_displayNotices(){
        Wp_Sdtrk_Helper::wp_sdtrk_showNotice();
    }

    // RUN CODE ON PLUGIN UPGRADE AND ADMIN NOTICE

    /**
     * Filter saving exopite Options *
     *
     * @param string[] $data
     * @return string[]
     */
    public function exopiteCustomMenuSave($data)
    {
        // License
        $licenseKey = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind($data, "licensekey_" . WP_SDTRK_LICENSE_TYPE_PRO);
        // If the release button has been changed
        $licenseStateNew = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind($data, "licensekey_activate");
        $licenseStateOld = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", "yes"), "licensekey_activate");
        if (strcmp($licenseStateNew, $licenseStateOld) != 0) {
            // If the license shall be activated
            if (strcmp($licenseStateNew, "yes") == 0) {
                Wp_Sdtrk_License::wp_sdtrk_license_call($licenseKey, WP_SDTRK_LICENSE_TYPE_PRO, 'activate', $data);
                $data = Wp_Sdtrk_License::wp_sdtrk_license_auto_deregister(WP_SDTRK_LICENSE_TYPE_PRO, $data);
            } else {
                Wp_Sdtrk_License::wp_sdtrk_license_call($licenseKey, WP_SDTRK_LICENSE_TYPE_PRO, 'deactivate', $data);
            }
        }
        // Random Numbers
        $replacedData = Wp_Sdtrk_Helper::wp_sdtrk_replace_arrayeElement($data, "-randomid-", 'wp_sdtrk_getRandomNumberAsInt', "value");
                
        return $replacedData;
    }
    
}
