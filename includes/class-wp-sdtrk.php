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

class Wp_Sdtrk
{

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

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		if (defined('WP_SDTRK_VERSION')) {
			$this->version = WP_SDTRK_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->wp_sdtrk = 'wp-sdtrk';

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
	private function load_dependencies()
	{

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp-sdtrk-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp-sdtrk-i18n.php';

		/**
		 * Load Redux Framework
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'vendor/redux/redux-core/framework.php';

		/**
		 * The classes responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-wp-sdtrk-admin.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-wp-sdtrk-admin-ajax.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-wp-sdtrk-admin-form.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-wp-sdtrk-public.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-wp-sdtrk-public-ajax.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-wp-sdtrk-public-form.php';

		/**
		 * The model classes
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/models/class-wp-sdtrk-model-base.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/models/class-wp-sdtrk-model-linkedin.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/models/class-wp-sdtrk-model-linkedin-rule.php';

		/**
		 * The helper classes
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/helpers/class-wp-sdtrk-helper-options.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/helpers/class-wp-sdtrk-helper-base.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/helpers/class-wp-sdtrk-helper-linkedin.php';

		/**
		 * Cronjob classes
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp-sdtrk-cron.php';

		/**
		 * Tracker Event
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-wp-sdtrk-tracker-event.php';

		/**
		 * Tracker Facebook
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-wp-sdtrk-tracker-fb.php';

		/**
		 * Tracker Google
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-wp-sdtrk-tracker-ga.php';

		/**
		 * Tracker Tik Tok
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-wp-sdtrk-tracker-tt.php';

		/**
		 * Decrypter
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-wp-sdtrk-decryptor-ds24.php';

		/**
		 * Hit Manager
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-wp-sdtrk-hitContainer.php';

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
	private function set_locale()
	{

		$plugin_i18n = new Wp_Sdtrk_i18n();

		$this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks()
	{

		$plugin_admin = new Wp_Sdtrk_Admin($this->get_wp_sdtrk(), $this->get_version());

		//Register js and css
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

		//Register ajax handler
		$this->loader->add_action('wp_ajax_wp_sdtrk_handle_admin_ajax_callback', $plugin_admin, 'register_ajax_handler');
		$this->loader->add_action('wp_ajax_nopriv_wp_sdtrk_handle_admin_ajax_callback', $plugin_admin, 'register_ajax_handler');

		//Register Redux
		$this->loader->add_action('after_setup_theme', $plugin_admin, 'wp_sdtrk_register_redux_options');
		$this->loader->add_action('after_setup_theme', $plugin_admin, 'register_redux_metabox');
		$this->loader->add_action('redux/options/wp_sdtrk_options/saved', $plugin_admin, 'after_redux_save', 10, 2);

		// Register Admin Pages
		$this->loader->add_action('in_admin_footer', $plugin_admin, 'inject_global_admin_ui');
		$this->loader->add_action('admin_init', $plugin_admin, 'register_form_handler');
		$this->loader->add_action('admin_menu', $plugin_admin, 'register_page_wp_sdtrk_admin_map_linkedin');
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks()
	{

		$plugin_public = new Wp_Sdtrk_Public($this->get_wp_sdtrk(), $this->get_version());

		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');

		//Register ajax handler
		$this->loader->add_action('wp_ajax_wp_sdtrk_handle_public_ajax_callback', $plugin_public, 'register_ajax_handler');
		$this->loader->add_action('wp_ajax_nopriv_wp_sdtrk_handle_public_ajax_callback', $plugin_public, 'register_ajax_handler');

		//Register cronjob
		WP_SDTRK_Cron::register_cron_actions();

		//Register Front-End Routes#
		$this->loader->add_action('init', $plugin_public, 'register_front_end_routes');
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run()
	{
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_wp_sdtrk()
	{
		return $this->wp_sdtrk;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Wp_Sdtrk_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader()
	{
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version()
	{
		return $this->version;
	}
}
