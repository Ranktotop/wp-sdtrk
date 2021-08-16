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
 * @package    Wp_Sdtrk
 * @subpackage Wp_Sdtrk/admin
 * @author     Your Name <email@example.com>
 */
class Wp_Sdtrk_Admin {
    
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $wp_sdtrk    The ID of this plugin.
     */
    private $wp_sdtrk;
    
    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;
    
    /*************************************************************
     * ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE
     *
     * @tutorial access_plugin_admin_public_methodes_from_inside.php
     */
    /**
     * Store plugin main class to allow public access.
     *
     * @since    20180622
     * @var object      The main class.
     */
    public $main;
    // ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE
    
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $wp_sdtrk       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    // public function __construct( $wp_sdtrk, $version ) {
    
    // 	$this->wp_sdtrk = $wp_sdtrk;
    // 	$this->version = $version;
    
    // }
    
    /*************************************************************
     * ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE
     *
     * @tutorial access_plugin_admin_public_methodes_from_inside.php
     */
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $wp_sdtrk       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $wp_sdtrk, $version, $plugin_main ) {
        
        $this->wp_sdtrk = $wp_sdtrk;
        $this->version = $version;
        $this->main = $plugin_main;
        
    }
    // ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE
    
    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        
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
        
        wp_enqueue_style( $this->wp_sdtrk, plugin_dir_url( __FILE__ ) . 'css/wp-sdtrk-admin.css', array(), $this->version, 'all' );
        
    }
    
    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        
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
        
        wp_enqueue_script( $this->wp_sdtrk, plugin_dir_url( __FILE__ ) . 'js/wp-sdtrk-admin.js', array( 'jquery' ), $this->version, false );
        
    }
    
    public function test_sanitize_callback( $val ) {
        return sanitize_text_field($val);
    }
    
    public function create_menu() {
        
        /**
         * Create a submenu page under Plugins.
         * Framework also add "Settings" to your plugin in plugins list.
         * @link https://github.com/JoeSz/Exopite-Simple-Options-Framework
         */
        $config_submenu = array(
            
            'type'              => 'menu',                          // Required, menu or metabox
            'id'                => $this->wp_sdtrk,              // Required, meta box id, unique per page, to save: get_option( id )
            'parent'            => 'options-general.php',                   // Parent page of plugin menu (default Settings [options-general.php])
            'submenu'           => true,                            // Required for submenu
            'title'             => __( 'Server-Side-Tracking', 'wp-sdtrk' ), // The title of the options page and the name in admin menu
            'capability'        => 'manage_options',                // The capability needed to view the page
            'plugin_basename'   =>  plugin_basename( plugin_dir_path( __DIR__ ) . $this->wp_sdtrk . '.php' ),
            // 'tabbed'            => false,
            // 'multilang'         => false,                        // To turn of multilang, default on.
            
        );
        
        /*
         * To add a metabox.
         * This normally go to your functions.php or another hook
         */
        $config_metabox = array(
            
            /*
             * METABOX
             */
            'type'              => 'metabox',                       // Required, menu or metabox
            'id'                => $this->wp_sdtrk,              // Required, meta box id, unique, for saving meta: id[field-id]
            'post_types'        => array( 'page' ),                 // Post types to display meta box
            // 'post_types'        => array( 'post', 'page' ),         // Could be multiple
            'context'           => 'advanced',                      // 	The context within the screen where the boxes should display: 'normal', 'side', and 'advanced'.
            'priority'          => 'default',                       // 	The priority within the context where the boxes should show ('high', 'low').
            'title'             => __( 'Product Meta', 'wp-sdtrk' ),                  // The title of the metabox
            'capability'        => 'edit_posts',                    // The capability needed to view the page
            'tabbed'            => true,
            // 'multilang'         => false,                        // To turn of multilang, default off except if you have qTransalte-X.
            'options'           => 'simple',                        // Only for metabox, options is stored az induvidual meta key, value pair.
        );
        
        /**
         * instantiate your admin page
         */
        $options_panel = new Exopite_Simple_Options_Framework( $config_submenu, $this->getGeneralSettingFields());
        $options_panel = new Exopite_Simple_Options_Framework( $config_metabox, $this->getMetaboxSettingFields());
    }
    
    private function getMetaboxSettingFields(){
        //General
        $fields[] = array(
            'name'   => 'general',
            'title'  => __( 'Product Meta', 'wp-sdtrk' ),
            'icon'   => 'dashicons-admin-generic',
            'fields' => array(
                
                array(
                    'id'          => 'productid',
                    'type'        => 'text',
                    'title'       => __( 'Product ID', 'wp-sdtrk' ),
                    'attributes'    => array(
                        'placeholder' => __( 'Please enter a product id', 'wp-sdtrk' ),
                    ),
                ),
            )
        );
        return $fields;
    }
    
    private function getGeneralSettingFields(){
        
        //Check for activated Cookie-Plugins
        $cookieOptions = array(
            'none' => __( 'Fire always', 'wp-sdtrk' )
        );
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        //Borlabs
        if(is_plugin_active('borlabs-cookie/borlabs-cookie.php')){
            $cookieOptions['borlabs'] = __( 'Borlabs Cookie', 'wp-sdtrk' );
        }
        
        
        $fields[] = array(
            'name' => 'basic',
            'title' => __('General', 'wp-sdtrk'),
            'icon' => 'dashicons-admin-generic',
            'fields' => array(
                array(
                    'id'      => 'brandname',
                    'type'    => 'text',
                    'title'   => __('Default Brand-Name', 'wp-sdtrk'),
                    'description' => __('This Name is used for several services', 'wp-sdtrk'),
                ),
            )
        );
        $fields[] = array(
            'name' => 'trkservices',
            'title' => __('Services', 'wp-sdtrk'),
            'icon' => 'dashicons-welcome-view-site',
            'sections' => array(
                array(
                    'name' => 'facebook',
                    'title' => __('Facebook', 'wp-sdtrk'),
                    'icon' => 'dashicons-facebook',
                    'fields' => array(
                        array(
                            'id'      => 'fb_pixelid',
                            'type'    => 'text',
                            'title'   => __('Facebook Pixel-ID', 'wp-sdtrk'),
                            'description' => __('Insert your own Facebook Pixel ID', 'wp-sdtrk'),
                        ),
                        array(
                            'type' => 'content',
                            'title' => '<h3>' . __('Browser based Tracking', 'wp-sdtrk') . '</h3>',
                            'content' => ''
                        ),
                        array(
                            'id'      => 'fb_trk_browser',
                            'type'    => 'switcher',
                            'title'   => __( 'Activate browser based tracking', 'wp-sdtrk' ),
                            'description' => __('Check to fire facebook browser pixel', 'wp-sdtrk'),
                            'default' => 'no'
                        ),
                        array(
                            'id'      => 'fb_trk_browser_cookie_service',
                            'type'    => 'radio',
                            'dependency' => array(
                                'fb_trk_browser',
                                '!=',
                                'false'
                            ),
                            'title'   => __( 'Choose cookie consent behavior', 'wp-sdtrk' ),
                            'options' => $cookieOptions,
                            'default' => 'none',     // optional
                            'style'    => 'fancy', // optional
                        ),
                        array(
                            'id'      => 'fb_trk_browser_cookie_id',
                            'type'    => 'text',
                            'dependency' => array(
                                'fb_trk_browser_cookie_service_borlabs|fb_trk_browser',
                                '==|!=',
                                'true|false'
                            ),
                            'title'   => __('Cookie ID', 'wp-sdtrk'),
                            'description' => __('You can get this information in the Plugins Consent-Settings', 'wp-sdtrk'),
                            'after' => '<p>'.__('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk').'</p><p><code>'.htmlentities('<script>wp_sdtrk_backload_fb_b();</script>').'</code></p>'
                        ),
                        array(
                            'type' => 'content',
                            'title' => '<h3>' . __('Server based Tracking', 'wp-sdtrk') . '</h3>',
                            'content' => ''
                        ),
                        array(
                            'id'      => 'fb_trk_server',
                            'type'    => 'switcher',
                            'title'   => __( 'Activate server based tracking', 'wp-sdtrk' ),
                            'description' => __('Check to fire Facebook Conversion API', 'wp-sdtrk'),
                            'default' => 'no'
                        ),
                        array(
                            'id'      => 'fb_trk_server_token',
                            'type'    => 'text',
                            'dependency' => array(
                                'fb_trk_server',
                                '==',
                                'true'
                            ),
                            'title'   => __('Conversion-API Token', 'wp-sdtrk'),
                            'description' => __('You can get the token within the facebook events-manager', 'wp-sdtrk'),
                        ),
                        array(
                            'id'      => 'fb_trk_server_cookie_service',
                            'type'    => 'radio',
                            'dependency' => array(
                                'fb_trk_server',
                                '!=',
                                'false'
                            ),
                            'title'   => __( 'Choose cookie consent behavior', 'wp-sdtrk' ),
                            'options' => $cookieOptions,
                            'default' => 'none',     // optional
                            'style'    => 'fancy', // optional
                        ),
                        array(
                            'id'      => 'fb_trk_server_cookie_id',
                            'type'    => 'text',
                            'dependency' => array(
                                'fb_trk_server_cookie_service_borlabs|fb_trk_server',
                                '==|!=',
                                'true|false'
                            ),
                            'title'   => __('Cookie ID', 'wp-sdtrk'),
                            'description' => __('You can get this information in the Plugins Consent-Settings', 'wp-sdtrk'),
                            'after' => '<p>'.__('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk').'</p><p><code>'.htmlentities('<script>wp_sdtrk_backload_fb_s();</script>').'</code></p>'
                        ),                        
                        array(
                            'id'      => 'fb_trk_server_debug',
                            'type'    => 'switcher',
                            'dependency' => array(
                                'fb_trk_server',
                                '==',
                                'true'
                            ),
                            'title'   => __( 'Activate Debugging', 'wp-sdtrk' ),
                            'description' => __('Check to activate CAPI debugging', 'wp-sdtrk'),
                            'default' => 'no'
                        ),
                        array(
                            'id'      => 'fb_trk_server_debug_code',
                            'type'    => 'text',
                            'dependency' => array(
                                'fb_trk_server|fb_trk_server_debug',
                                '==|==',
                                'true|true'
                            ),
                            'title'   => __('Test-Code', 'wp-sdtrk'),
                            'description' => __('You can get the Test-Code within the facebook events-manager', 'wp-sdtrk'),
                        ),
                    )
                ),
                array(
                    'name' => 'google',
                    'title' => __('Google', 'wp-sdtrk'),
                    'icon' => 'dashicons-google',
                    'fields' => array(
                        array(
                            'id'      => 'ga_measurement_id',
                            'type'    => 'text',
                            'title'   => __('Google Analytics ID', 'wp-sdtrk'),
                            'description' => __('Insert your own Measurement-ID / Universal Analytics ID', 'wp-sdtrk'),
                        ),
                        array(
                            'type' => 'content',
                            'dependency' => array(
                                'fb_pixelid',
                                '!=',
                                ''
                            ),
                            'title' => '<h3>' . __('Browser based Tracking', 'wp-sdtrk') . '</h3>',
                            'content' => ''
                        ),
                        array(
                            'id'      => 'ga_trk_browser',
                            'type'    => 'switcher',
                            'dependency' => array(
                                'ga_measurement_id',
                                '!=',
                                ''
                            ),
                            'title'   => __( 'Activate browser based tracking', 'wp-sdtrk' ),
                            'description' => __('Check to fire analytics browser tracking', 'wp-sdtrk'),
                            'default' => 'no'
                        ),
                        array(
                            'id'      => 'ga_trk_browser_cookie_service',
                            'type'    => 'radio',
                            'dependency' => array(
                                'ga_trk_browser',
                                '!=',
                                'false'
                            ),
                            'title'   => __( 'Choose cookie consent behavior', 'wp-sdtrk' ),
                            'options' => $cookieOptions,
                            'default' => 'none',     // optional
                            'style'    => 'fancy', // optional
                        ),
                        array(
                            'id'      => 'ga_trk_browser_cookie_id',
                            'type'    => 'text',
                            'dependency' => array(
                                'ga_trk_browser_cookie_service_borlabs|ga_trk_browser',
                                '==|!=',
                                'true|false'
                            ),
                            'title'   => __('Cookie ID', 'wp-sdtrk'),
                            'description' => __('You can get this information in the Plugins Consent-Settings', 'wp-sdtrk'),
                            'after' => '<p>'.__('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk').'</p><p><code>'.htmlentities('<script>wp_sdtrk_backload_ga_b();</script>').'</code></p>'
                        ),
                        array(
                            'id'      => 'ga_trk_debug',
                            'type'    => 'switcher',
                            'dependency' => array(
                                'ga_trk_browser',
                                '==',
                                'true'
                            ),
                            'title'   => __( 'Activate Debugging', 'wp-sdtrk' ),
                            'description' => __('Check to activate GA debugging', 'wp-sdtrk'),
                            'default' => 'no'
                        )
                    )
                )
            )
        );
        
        $paramNameString = '<b>'.__('Parameter-Name(s)', 'wp-sdtrk').':</b> ';
        $acceptsString = '<b>'.__('Accepts', 'wp-sdtrk').':</b> ';
        $exampleString = '<b>'.__('Example', 'wp-sdtrk').':</b> ';
        $exampleDomain = get_home_url();
        
        $fields[] = array(
            'name' => 'tutorials',
            'title' => __('Tutorials', 'wp-sdtrk'),
            'icon' => 'dashicons-sos',
            'fields' => array(
                array(
                    'type' => 'content',
                    'title' => '<h3>' . __('Setup Tutorial', 'wp-sdtrk') . '</h3>',
                    'content' => '<iframe src="https://player.vimeo.com/video/587429111?h=6660e0f5f6" width="640" height="360" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>'
                ),
                array(
                    'type' => 'content',
                    'title' => '<h3>' . __('Supported GET-Parameters', 'wp-sdtrk') . '</h3>',
                    'content' => '<b>'.__('Note', 'wp-sdtrk').':</b> '.__('Enable "appending order details to thank you URL" on services like Digistore24 so that the parameters below are passed automatically.', 'wp-sdtrk')
                ),
                array(
                    'type' => 'content',
                    'title' => __('Transaction-ID', 'wp-sdtrk'),
                    'content' => '<p>'.$paramNameString.'order_id</p><p>'.$acceptsString.'string</p><p>'.$exampleString.'<i>'.$exampleDomain.'?<b>order_id=9854</b></p></i>'
                ),
                array(
                    'type' => 'content',
                    'title' => __('Product-ID', 'wp-sdtrk'),
                    'content' => '<p>'.$paramNameString.'prodid | product_id</p><p>'.$acceptsString.'string</p><p>'.$exampleString.'<i>'.$exampleDomain.'?<b>prodid=1337</b></p></i>'
                ),
                array(
                    'type' => 'content',
                    'title' => __('Product-Name', 'wp-sdtrk'),
                    'content' => '<p>'.$paramNameString.'product_name</p><p>'.$acceptsString.'string</p><p>'.$exampleString.'<i>'.$exampleDomain.'?<b>product_name=newproduct</b></p></i>'
                ),
                array(
                    'type' => 'content',
                    'title' => __('Value', 'wp-sdtrk'),
                    'content' => '<p>'.$paramNameString.'value | net_amount | amount</p><p>'.$acceptsString.'number</p><p>'.$exampleString.'<i>'.$exampleDomain.'?<b>value=99</b></p></i>'
                ),
                array(
                    'type' => 'content',
                    'title' => __('Type', 'wp-sdtrk'),
                    'content' => '<p>'.$paramNameString.'type</p><p>'.$acceptsString.'AddToCart | Purchase | CompleteRegistration | Lead | InitiateCheckout | ViewContent</p><p>'.$exampleString.'<i>'.$exampleDomain.'?<b>type=Lead</b></p></i>'
                ),  
                array(
                    'type' => 'content',
                    'title' => __('UTM', 'wp-sdtrk'),
                    'content' => '<p>'.$paramNameString.'utm_source | utm_campaign | utm_term | utm_medium | utm_content</p><p>'.$acceptsString.'string</p><p>'.$exampleString.'<i>'.$exampleDomain.'?<b>utm_source=facebook&utm_medium=cpc</b></p></i><p><b>'.__('Note', 'wp-sdtrk').':</b> '.__('UTM parameters are stored in cookies and automatically passed on further visits', 'wp-sdtrk').'</p>'
                ),  
            )
        );
        return $fields;
    }
    
    public function add_style_to_admin_head() {
        global $post_type;
        if ( 'test' == $post_type ) {
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
     * To sort, Exopite Simple Options Framework need 'options' => 'simple'.
     * Simple options is stored az induvidual meta key, value pair, otherwise it is stored in an array.
     *
     *
     * Meta key value paars need to sort as induvidual.
     *
     * I implemented this option because it is possible to search in serialized (array) post meta:
     * @link https://wordpress.stackexchange.com/questions/16709/meta-query-with-meta-values-as-serialize-arrays
     * @link https://stackoverflow.com/questions/15056407/wordpress-search-serialized-meta-data-with-custom-query
     * @link https://www.simonbattersby.com/blog/2013/03/querying-wordpress-serialized-custom-post-data/
     *
     * but there is no way to sort them with wp_query or SQL.
     * @link https://wordpress.stackexchange.com/questions/87265/order-by-meta-value-serialized-array/87268#87268
     * "Not in any reliable way. You can certainly ORDER BY that value but the sorting will use the whole serialized string,
     * which will give * you technically accurate results but not the results you want. You can't extract part of the string
     * for sorting within the query itself. Even if you wrote raw SQL, which would give you access to database functions like
     * SUBSTRING, I can't think of a dependable way to do it. You'd need a MySQL function that would unserialize the value--
     * you'd have to write it yourself.
     * Basically, if you need to sort on a meta_value you can't store it serialized. Sorry."
     *
     * It is possible to get all required posts and store them in an array and then sort them as an array,
     * but what if you want multiple keys/value pair to be sorted?
     *
     * UPDATE
     * it is maybe possible:
     * @link http://www.russellengland.com/2012/07/how-to-unserialize-data-using-mysql.html
     * but it is waaay more complicated and less documented as meta query sort and search.
     * It should be not an excuse to use it, but it is not as reliable as it should be.
     *
     * @link https://wpquestions.com/Order_by_meta_key_where_value_is_serialized/7908
     * "...meta info serialized is not a good idea. But you really are going to lose the ability to query your
     * data in any efficient manner when serializing entries into the WP database.
     *
     * The overall performance saving and gain you think you are achieving by serialization is not going to be noticeable to
     * any major extent. You might obtain a slightly smaller database size but the cost of SQL transactions is going to be
     * heavy if you ever query those fields and try to compare them in any useful, meaningful manner.
     *
     * Instead, save serialization for data that you do not intend to query in that nature, but instead would only access in
     * a passive fashion by the direct WP API call get_post_meta() - from that function you can unpack a serialized entry
     * to access its array properties too."
     */
    public function manage_sortable_columns( $columns ) {

        $columns['text_1'] = 'text_1';
        $columns['color_2'] = 'color_2';
        $columns['date_2'] = 'date_2';

        return $columns;

    }

    public function manage_posts_orderby( $query ) {

        if( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        /**
         * meta_types:
         * Possible values are 'NUMERIC', 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED'.
         * Default value is 'CHAR'.
         *
         * @link https://codex.wordpress.org/Class_Reference/WP_Meta_Query
         */
        $columns = array(
            'text_1'  => 'char',
            'color_2' => 'char',
            'date_2'  => 'date',
        );

        foreach ( $columns as $key => $type ) {

            if ( $key === $query->get( 'orderby') ) {
                $query->set( 'orderby', 'meta_value' );
                $query->set( 'meta_key', $key );
                $query->set( 'meta_type', $type );
                break;
            }

        }

    }
    // END ADD/REMOVE/REORDER/SORT CUSTOM POST TYPE LIST COLUMNS (test)

    /********************************************
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
    public function upgrader_process_complete( $upgrader_object, $options ) {

        // If an update has taken place and the updated type is plugins and the plugins element exists
        if( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {

            // Iterate through the plugins being updated and check if ours is there
            foreach( $options['plugins'] as $plugin ) {
                if( $plugin == WP_SDTRK_BASE_NAME ) {

                    // Set a transient to record that our plugin has just been updated
                    set_transient( 'exopite_sof_updated', 1 );
                    set_transient( 'exopite_sof_updated_message', esc_html__( 'Thanks for updating', 'exopite_sof' ) );

                }
            }
        }
    }

    /**
     * Show a notice to anyone who has just updated this plugin
     * This notice shouldn't display to anyone who has just installed the plugin for the first time
     */
    public function display_update_notice() {
        // Check the transient to see if we've just activated the plugin
        if( get_transient( 'exopite_sof_updated' ) ) {

            // @link https://digwp.com/2016/05/wordpress-admin-notices/
            echo '<div class="notice notice-success is-dismissible"><p><strong>' . get_transient( 'exopite_sof_updated_message' ) . '</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';

            // Delete the transient so we don't keep displaying the activation message
            delete_transient( 'exopite_sof_updated' );
            delete_transient( 'exopite_sof_updated_message' );
        }
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
                Wp_Sdtrk_License::wp_sdtrk_license_call($licenseKey, WP_SDTRK_LICENSE_TYPE_PRO, 'activate',$data);
                $data = Wp_Sdtrk_License::wp_sdtrk_license_auto_deregister(WP_SDTRK_LICENSE_TYPE_PRO,$data);
            } else {
                Wp_Sdtrk_License::wp_sdtrk_license_call($licenseKey, WP_SDTRK_LICENSE_TYPE_PRO, 'deactivate',$data);
            }
        }
        // Random Numbers
        $replacedData = Wp_Sdtrk_Helper::wp_sdtrk_replace_arrayeElement($data, "-randomid-", 'wp_sdtrk_getRandomNumberAsInt', "value");
        return $replacedData;
    }
}
