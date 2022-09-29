<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Wp_Sdtrk
 * @subpackage Wp_Sdtrk/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Wp_Sdtrk
 * @subpackage Wp_Sdtrk/includes
 * @author     Your Name <email@example.com>
 */
class Wp_Sdtrk {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Wp_Sdtrk_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $wp_sdtrk    The string used to uniquely identify this plugin.
	 */
	protected $wp_sdtrk;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/*************************************************************
	 * ACCESS PLUGIN AND ITS METHODES LATER FROM OUTSIDE OF PLUGIN
	 *
	 * @tutorial access_plugin_and_its_methodes_later_from_outside_of_plugin.php
	 */
	/**
	 * Store plugin admin class to allow public access.
	 *
	 * @since    20180622
	 * @var object      The admin class.
	 */
	public $admin;


	/**
	 * Store plugin public class to allow public access.
	 *
	 * @since    20180622
	 * @var object      The admin class.
	 */
	public $public;
	// END ACCESS PLUGIN AND ITS METHODES LATER FROM OUTSIDE OF PLUGIN

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
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		$this->wp_sdtrk = 'wp-sdtrk';
		$this->version = '1.0.0';

		/*************************************************************
		 * ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE
		 *
		 * @tutorial access_plugin_admin_public_methodes_from_inside.php
		 */
		$this->main = $this;
		// ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Wp_Sdtrk_Loader. Orchestrates the hooks of the plugin.
	 * - Wp_Sdtrk_i18n. Defines internationalization functionality.
	 * - Wp_Sdtrk_Admin. Defines all hooks for the admin area.
	 * - Wp_Sdtrk_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-sdtrk-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-sdtrk-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wp-sdtrk-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wp-sdtrk-public.php';
		
		/**
		 * The Helper-Classes
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-sdtrk-helper.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-sdtrk-dbhelper.php';
		
		/**
		 * The License-Class
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-sdtrk-license.php';

        /**
         * The class responsible for defining all actions for AJAX
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-sdtrk-ajax.php';        
        
        /**
         * Tracker Event
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wp-sdtrk-tracker-event.php';
        
        /**
         * Tracker Local
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wp-sdtrk-tracker-local.php';
        
        /**
         * Tracker Facebook
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wp-sdtrk-tracker-fb.php';
        
        /**
         * Tracker Google
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wp-sdtrk-tracker-ga.php';
        
        /**
         * Tracker Tik Tok
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wp-sdtrk-tracker-tt.php';
        
        /**
         * Tracker LinkedIn
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wp-sdtrk-tracker-lin.php';
        
        /**
         * Decrypter
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wp-sdtrk-decryptor-ds24.php';
        
        /**
         * Hit Manager
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wp-sdtrk-hitContainer.php';
        

		/**************************************
		 * EXOPITE SIMPLE OPTIONS FRAMEWORK
		 *
		 * Get Exopite Simple Options Framework
		 *
		 * @link https://github.com/JoeSz/Exopite-Simple-Options-Framework
		 * @link https://www.joeszalai.org/exopite/exopite-simple-options-framework/
		 *
		 * @tutorial app_option_page_for_plugin_with_options_framework.php
		 */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/exopite-simple-options/exopite-simple-options-framework-class.php';
		// END EXOPITE SIMPLE OPTIONS FRAMEWORK

		$this->loader = new Wp_Sdtrk_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Wp_Sdtrk_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Wp_Sdtrk_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		// $plugin_admin = new Wp_Sdtrk_Admin( $this->get_wp_sdtrk(), $this->get_version() );

		// $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		// $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		/*************************************************************
		 * ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE
		 * (COMBINED WITH ACCESS PLUGIN AND ITS METHODES LATER FROM OUTSIDE OF PLUGIN)
		 *
		 *
		 * @tutorial access_plugin_admin_public_methodes_from_inside.php
		 */
		$this->admin = new Wp_Sdtrk_Admin( $this->get_wp_sdtrk(), $this->get_version(), $this->main );
		// END ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE

		/*************************************************************
		 * ACCESS PLUGIN AND ITS METHODES LATER FROM OUTSIDE OF PLUGIN
		 *
		 * @tutorial access_plugin_and_its_methodes_later_from_outside_of_plugin.php
		 */
		// $this->admin = new Wp_Sdtrk_Admin( $this->get_wp_sdtrk(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_scripts' );
		// END ACCESS PLUGIN AND ITS METHODES LATER FROM OUTSIDE OF PLUGIN
       
		/***********************************
		 * EXOPITE SIMPLE OPTIONS FRAMEWORK
		 *
		 * Save/Update our plugin options
		 *
		 * @tutorial app_option_page_for_plugin_with_options_framework.php
		 */
        $this->loader->add_action( 'init', $this->admin, 'create_menu' );
		// END EXOPITE SIMPLE OPTIONS FRAMEWORK

		/********************************************
		 * RUN CODE ON PLUGIN UPGRADE AND ADMIN NOTICE
		 *
		 * @tutorial run_code_on_plugin_upgrade_and_admin_notice.php
		 */
		/**
		* This function runs when WordPress completes its upgrade process
		* It iterates through each plugin updated to see if ours is included
		* @param $upgrader_object Array
		* @param $options Array
		*/
		$this->loader->add_action( 'upgrader_process_complete', $this->admin, 'upgrader_process_complete', 10, 2 );
		
		//Display sheduled notices
		$this->loader->add_action( 'admin_notices', $this->admin, 'wpsdtrk_displayNotices' );

		$this->loader->add_action( 'admin_head', $this->admin, 'add_style_to_admin_head' );	
		
		/*************************************************************
		 * Filter custom manipulations of exopite Menu Data
		 *
		 */
		$this->loader->add_filter( 'exopite_sof_save_menu_options', $this->admin, 'exopiteCustomMenuSave',10);	

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		// $plugin_public = new Wp_Sdtrk_Public( $this->get_wp_sdtrk(), $this->get_version() );

		// $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		// $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		/*************************************************************
		 * ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE
		 * (COMBINED WITH ACCESS PLUGIN AND ITS METHODES LATER FROM OUTSIDE OF PLUGIN)
		 *
		 * @tutorial access_plugin_admin_public_methodes_from_inside.php
		 */
		$this->public = new Wp_Sdtrk_Public( $this->get_wp_sdtrk(), $this->get_version(), $this->main );
		// END ACCESS PLUGIN ADMIN PUBLIC METHODES FROM INSIDE

		/*************************************************************
		 * ACCESS PLUGIN AND ITS METHODES LATER FROM OUTSIDE OF PLUGIN
		 *
		 * @tutorial access_plugin_and_its_methodes_later_from_outside_of_plugin.php
		 */
    	// $this->public = new Wp_Sdtrk_Public( $this->get_wp_sdtrk(), $this->get_version() );
		$this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_scripts' );
		// END ACCESS PLUGIN AND ITS METHODES LATER FROM OUTSIDE OF PLUGIN
		
		/*************************************************************
		 * License Check
		 *
		 */		
		$this->loader->add_action( 'wp_sdtrk_licensecheck_cron', $this->public, 'licensecheck' );
		
		/*************************************************************
		 * Local GSync Cron
		 *
		 */
		$this->loader->add_action( 'wp_sdtrk_gsync_cron', $this->public, 'local_gsync' );
		$this->loader->add_action( 'wp_sdtrk_csvsync_cron', $this->public, 'local_csv_feed' );
		
		/*************************************************************
		 * The wp_ajax_ is telling wordpress to use ajax and the prefix_ajax_first is the hook name to use in JavaScript or in URL.
		 *
		 * Call AJAX function via URL: https://www.yourwebsite.com/wp-admin/admin-ajax.php?action=prefix_ajax_first&post_id=23&other_param=something
		 *
		 * The ajax_first is the callback function.
		 * wp_ajax_ is for authenticated users
		 * wp_ajax_nopriv_ is for NOT authenticated users
		 */
		$this->loader->add_action('wp_ajax_wp_sdtrk_handleAjaxCallback', $this->public, 'handleAjaxCallback');
		$this->loader->add_action('wp_ajax_nopriv_wp_sdtrk_handleAjaxCallback', $this->public, 'handleAjaxCallback');
	   
		/**
		 * Create a redirecton to a custom php file
		 *
		 * Use: <website>/?<plugin_name>=my_value&[bla=bla]
		 *
		 * @link http://wordpress.stackexchange.com/a/38990/90212
		 * @link http://wordpress.stackexchange.com/questions/81850/external-rewrite-rules
		 * @link http://stackoverflow.com/questions/25310665/wordpress-how-to-create-a-rewrite-rule-for-a-file-in-a-custom-plugin
		 * @link https://premium.wpmudev.org/blog/building-customized-urls-wordpress/?nhtz=b&utm_expid=3606929-91.15T0nlf8TFCqo1W_BlZjGg.1&utm_referrer=https%3A%2F%2Fwww.google.de%2F
		 */
		// add blackout to the whitelist of variables it wordpress knows and allows
		$this->loader->add_filter( 'query_vars', $this->public, 'whitelist_query_variable' );
		
		// If this is done, we can access it later
		// This example checks very early in the process:
		// if the variable is set, we include our page and stop execution after it
		$this->loader->add_action( 'parse_request', $this->public, 'redirect_to_file' );
	
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_wp_sdtrk() {
		return $this->wp_sdtrk;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Wp_Sdtrk_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
