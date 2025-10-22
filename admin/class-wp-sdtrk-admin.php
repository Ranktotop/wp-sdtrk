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
class Wp_Sdtrk_Admin
{

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

	private Wp_Sdtrk_Admin_Ajax_Handler $admin_ajax_handler;
	private Wp_Sdtrk_Admin_Form_Handler $admin_form_handler;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $wp_sdtrk       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($wp_sdtrk, $version)
	{

		$this->wp_sdtrk = $wp_sdtrk;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles($hook_suffix = '')
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
		wp_enqueue_style($this->wp_sdtrk . '-redux-customize', plugin_dir_url(__FILE__) . 'css/wp-sdtrk-redux.css', array(), $this->version, 'all');
	}

	private function enqueue_custom_page_css(): void
	{
		wp_enqueue_style(
			$this->wp_sdtrk . '-custom-pages',
			plugin_dir_url(__FILE__) . 'css/wp-sdtrk-custom-pages.css',
			array(),
			$this->version
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts($hook_suffix = '')
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

		wp_enqueue_script($this->wp_sdtrk . '-modal', plugin_dir_url(__FILE__) . 'js/wpsdtrk-modal.js', array('jquery'), $this->version, false);
		wp_enqueue_script($this->wp_sdtrk, plugin_dir_url(__FILE__) . 'js/wp-sdtrk-admin.js', array('jquery'), $this->version, false);

		if ($hook_suffix === 'toplevel_page_wp_sdtrk_admin_map_linkedin') {
			// LinkedIn Mappings
			$this->enqueue_wp_sdtrk_admin_map_linkedin($hook_suffix);
		}

		/**
		 * In backend there is global ajaxurl variable defined by WordPress itself.
		 *
		 * This variable is not created by WP in frontend. It means that if you want to use AJAX calls in frontend, then you have to define such variable by yourself.
		 * Good way to do this is to use wp_localize_script.
		 *
		 * @link http://wordpress.stackexchange.com/a/190299/90212
		 *      
		 *       You could also pass this datas with the "data" attribute somewhere in your form.
		 */
		//TODO remove if not needed
		wp_localize_script($this->wp_sdtrk, 'wp_sdtrk', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			/**
			 * Create nonce for security.
			 *
			 * @link https://codex.wordpress.org/Function_Reference/wp_create_nonce
			 */
			'_nonce' => wp_create_nonce('security_wp-sdtrk'),
			'notice_success' => __('Saved successfully!', 'wp-sdtrk'),
			'notice_error' => __('Error occurred!', 'wp-sdtrk'),
			'msg_confirm_delete_mapping' => __('Do you really want to delete the mapping? This action cannot be undone!', 'wp-sdtrk'),
			'label_dropdown_select' => __('Select an option...', 'wp-sdtrk'),
			'label_product_id' => __('Product ID', 'wp-sdtrk'),
			'label_product_name' => __('Product Name', 'wp-sdtrk'),
			'label_delete' => __('Delete', 'wp-sdtrk'),
			'label_edit' => __('Edit', 'wp-sdtrk'),
			'label_save' => __('Save', 'wp-sdtrk'),
			'label_confirm' => __('Are you sure?', 'wp-sdtrk'),
			'placeholder_leave_empty_ignore' => __('Leave empty to ignore', 'wp-sdtrk'),
		));
	}

	/**
	 * Register options for Redux Admin Page
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function wp_sdtrk_register_redux_options(): void
	{
		if (!class_exists('Redux')) {
			return;
		}
		Redux::disable_demo();

		Redux::set_args('wp_sdtrk_options', [
			'opt_name'        => 'wp_sdtrk_options',
			'menu_title'      => 'Smart Serverside Tracking',
			'page_title'      => 'Smart Serverside Tracking',
			'menu_type'       => 'menu',
			'page_priority' => 80,
			'allow_sub_menu'  => true,
			'page_slug'       => 'sdtrk_settings',
			'display_version' => false,
		]);

		/**
		 * ============================================================
		 * SECTION: General Settings
		 * ============================================================
		 */
		Redux::set_section('wp_sdtrk_options', [
			'title'  => __('General', 'wp-sdtrk'),
			'id'     => 'general_section',
			'subtitle'   => __('Basic Tracking Settings', 'wp-sdtrk'),
			'icon'   => 'el el-cog',
			'fields' => [
				[
					'id'    => 'brandname',
					'type'  => 'text',
					'title' => __('Default Brand-Name', 'wp-sdtrk'),
					'subtitle'  => __('This Name is used for several services', 'wp-sdtrk'),
				],
				[
					'id'      => 'trk_fp',
					'type'    => 'switch',
					'title'   => __('Enable Fingerprinting', 'wp-sdtrk'),
					'subtitle'    => __('Check to fingerprint users (works cookie-less)', 'wp-sdtrk'),
					'default' => 0,
				],
				[
					'id'    => 'trk_time',
					'type'  => 'switch',
					'title' => __('Enable timed signal events', 'wp-sdtrk'),
					'subtitle'  => __('Check to fire signal events described below after time', 'wp-sdtrk'),
					'default' => 0,
				],
				[
					'id'         => 'trk_time_group',
					'type'       => 'repeater',
					'title'      => __('Timed signal events', 'wp-sdtrk'),
					'subtitle'       => __('Fire a signal-event after X Seconds', 'wp-sdtrk'),
					'fields'     => [
						[
							'id'      => 'trk_time_group_seconds',
							'type'    => 'spinner',
							'title'   => __('Seconds', 'wp-sdtrk'),
							'min'     => 1,
							'max'     => 18000,
							'step'    => 1,
							'default' => 10,
						],
					],
				],
				[
					'id'    => 'trk_scroll',
					'type'  => 'switch',
					'title' => __('Enable scroll signal events', 'wp-sdtrk'),
					'subtitle'  => __('Check to fire scroll events described below when reaching scroll-depth', 'wp-sdtrk'),
					'default' => 0,
				],
				[
					'id'       => 'trk_scroll_group',
					'type'     => 'repeater',
					'title'    => __('Scroll signal events', 'wp-sdtrk'),
					'subtitle'     => __('Fire signal-event if the user has scrolled to x percent of the page', 'wp-sdtrk'),
					'fields'   => [
						[
							'id'      => 'trk_scroll_group_percent',
							'type'    => 'spinner',
							'title'   => __('Percent to reach', 'wp-sdtrk'),
							'min'     => 1,
							'max'     => 100,
							'step'    => 1,
							'default' => 33,
						],
					],
				],
				[
					'id'    => 'trk_buttons',
					'type'  => 'switch',
					'title' => __('Fire signal-event on button-clicks', 'wp-sdtrk'),
					'subtitle'  => __('Check to fire a signal event after an element has been clicked', 'wp-sdtrk'),
					'default' => 0,
					'desc' => __('Attention: In order for clicks to be tracked, the element to be tracked must contain the class trkbtn-TAGNAME-trkbtn. The TAGNAME placeholder can be replaced by any word and will be passed as a parameter', 'wp-sdtrk') . '<br><b style="color:white">' . __('Example:', 'wp-sdtrk') . '</b> ' . htmlentities('<a href="https://example.com" class="trkbtn-mybutton-trkbtn">MyButton</a>'),
				],
				[
					'id'    => 'trk_visibility',
					'type'  => 'switch',
					'title' => __('Fire signal-event on visibility of items', 'wp-sdtrk'),
					'subtitle'  => __('Check to fire a signal event after an element gets visible', 'wp-sdtrk'),
					'default' => 0,
					'desc' => __('Attention: In order for tracking to work, the element to be tracked must contain the class watchitm-TAGNAME-watchitm. The TAGNAME placeholder can be replaced by any word and will be passed as a parameter', 'wp-sdtrk') . '<br><b style="color:white">' . __('Example:', 'wp-sdtrk') . '</b> ' . htmlentities('<h2 class="watchitm-mybutton-watchitm">My Headline</h2>'),
				],
			],
		]);

		/**
		 * ============================================================
		 * SECTION: Tracking Services (mit Subsections)
		 * ============================================================
		 */

		// LOCAL TRACKING
		Redux::set_section('wp_sdtrk_options', [
			'title'      => __('Tracking Services', 'wp-sdtrk'),
			'id'         => 'tracking_services',
			'icon'       => 'el el-share-alt',
			'subsection' => false,
			'fields'     => [],
		]);

		// META TRACKING
		Redux::set_section('wp_sdtrk_options', [
			'title'      => __('Meta Tracking', 'wp-sdtrk'),
			'id'         => 'meta_tracking_section',
			'parent_id'  => 'tracking_services',
			'icon'       => 'el el-facebook',
			'subsection' => true,
			'fields'     => [
				[
					'id'    => 'meta_pixelid',
					'type'  => 'text',
					'title' => __('Meta Pixel-ID', 'wp-sdtrk'),
					'subtitle'  => __('Insert your Meta Pixel-ID', 'wp-sdtrk'),
				],
				[
					'id'       => 'meta_trk_debug',
					'type'     => 'switch',
					'title'    => __('Activate Debugging', 'wp-sdtrk'),
					'subtitle'     => __('Check to activate Meta debugging', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['meta_pixelid', '!=', ''],
				],
				[
					'id'       => 'meta_trk_server_debug_code',
					'type'     => 'text',
					'title'    => __('Server Test-Code', 'wp-sdtrk'),
					'subtitle'     => __('If you want to debug the events in the Meta events-manager, enter the test-code!', 'wp-sdtrk'),
					'required' => [['meta_trk_debug', '=', '1'], ['meta_pixelid', '!=', '']],
				],
				[
					'id'       => 'meta_trk_browser',
					'type'     => 'switch',
					'title'    => __('Activate browser based tracking', 'wp-sdtrk'),
					'subtitle'     => __('Check to fire Meta browser pixel', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['meta_pixelid', '!=', ''],
				],
				[
					'id'       => 'meta_trk_browser_cookie_service',
					'type'     => 'select',
					'title'    => __('Choose cookie consent behavior', 'wp-sdtrk'),
					'options'  => [
						'none'     => __('Fire always', 'wp-sdtrk'),
						'borlabs'  => __('Borlabs Cookie', 'wp-sdtrk'),
					],
					'default'  => 'none',
					'required' => [['meta_trk_browser', '=', '1'], ['meta_pixelid', '!=', '']],
				],
				[
					'id'       => 'meta_trk_browser_cookie_id',
					'type'     => 'text',
					'title'    => __('Cookie ID', 'wp-sdtrk'),
					'subtitle' => __('You can get this information in the Plugins Consent-Settings', 'wp-sdtrk'),
					'desc' => '<p style="color:#57b957">' . __('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk') . '</p><p><code style="font-style: italic;">' . htmlentities('<script>wp_sdtrk_backload_meta_b();</script>') . '</code></p>',
					'required' => [['meta_trk_browser_cookie_service', '=', 'borlabs'], ['meta_trk_browser', '=', '1'], ['meta_pixelid', '!=', '']],
				],
				[
					'id'       => 'meta_trk_server',
					'type'     => 'switch',
					'title'    => __('Activate server based tracking', 'wp-sdtrk'),
					'subtitle'     => __('Check to send Meta-Events server-side to the API', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['meta_pixelid', '!=', ''],
				],
				[
					'id'       => 'meta_trk_server_token',
					'type'     => 'text',
					'title'    => __('API Token', 'wp-sdtrk'),
					'subtitle'     => __('You can get the token within the Meta Events-Manager settings', 'wp-sdtrk'),
					'required' => [['meta_trk_server', '=', '1'], ['meta_pixelid', '!=', '']],
				],
				[
					'id'       => 'meta_trk_server_cookie_service',
					'type'     => 'select',
					'title'    => __('Choose cookie consent behavior', 'wp-sdtrk'),
					'options'  => [
						'none'     => __('Fire always', 'wp-sdtrk'),
						'borlabs'  => __('Borlabs Cookie', 'wp-sdtrk'),
					],
					'default'  => 'none',
					'required' => [['meta_trk_server', '=', '1'], ['meta_pixelid', '!=', '']],
				],
				[
					'id'       => 'meta_trk_server_cookie_id',
					'type'     => 'text',
					'title'    => __('Cookie ID', 'wp-sdtrk'),
					'required' => [['meta_trk_server_cookie_service', '=', 'borlabs'], ['meta_trk_server', '=', '1'], ['meta_pixelid', '!=', '']],
					'desc' => '<p style="color:#57b957">' . __('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk') . '</p><p><code style="font-style: italic;">' . htmlentities('<script>wp_sdtrk_backload_meta_s();</script>') . '</code></p>',
				],
			],
		]);

		// GOOGLE ANALYTICS 4 TRACKING
		Redux::set_section('wp_sdtrk_options', [
			'title'      => __('Google Analytics 4', 'wp-sdtrk'),
			'id'         => 'google_tracking_section',
			'parent_id'  => 'tracking_services',
			'icon'       => 'el el-globe',
			'subsection' => true,
			'fields'     => [
				[
					'id'    => 'ga_measurement_id',
					'type'  => 'text',
					'title' => __('Google Measurement ID', 'wp-sdtrk'),
					'subtitle'  => __('Insert your Google Measurement ID (G-XXXXXXXXXX)', 'wp-sdtrk'),
				],
				[
					'id'       => 'ga_trk_debug',
					'type'     => 'switch',
					'title'    => __('Activate Debugging', 'wp-sdtrk'),
					'subtitle'     => __('Check to activate Google Analytics debugging', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['ga_measurement_id', '!=', ''],
				],
				[
					'id'       => 'ga_trk_debug_live',
					'type'     => 'switch',
					'title'    => __('Debug in live-view', 'wp-sdtrk'),
					'subtitle'     => __('Check to show debug-hits in the Google Analytics realtime report', 'wp-sdtrk'),
					'default'  => 0,
					'required' => [['ga_measurement_id', '!=', ''], ['ga_trk_debug', '=', '1']],
				],
				[
					'id'       => 'ga_trk_browser',
					'type'     => 'switch',
					'title'    => __('Activate browser based tracking', 'wp-sdtrk'),
					'subtitle'     => __('Check to fire Google Analytics browser pixel', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['ga_measurement_id', '!=', ''],
				],
				[
					'id'       => 'ga_trk_browser_cookie_service',
					'type'     => 'select',
					'title'    => __('Choose cookie consent behavior', 'wp-sdtrk'),
					'options'  => [
						'none'     => __('Fire always', 'wp-sdtrk'),
						'borlabs'  => __('Borlabs Cookie', 'wp-sdtrk'),
					],
					'default'  => 'none',
					'required' => [['ga_trk_browser', '=', '1'], ['ga_measurement_id', '!=', '']],
				],
				[
					'id'       => 'ga_trk_browser_cookie_id',
					'type'     => 'text',
					'title'    => __('Cookie ID', 'wp-sdtrk'),
					'required' => [['ga_trk_browser_cookie_service', '=', 'borlabs'], ['ga_trk_browser', '=', '1'], ['ga_measurement_id', '!=', '']],
					'desc' => '<p style="color:#57b957">' . __('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk') . '</p><p><code style="font-style: italic;">' . htmlentities('<script>wp_sdtrk_backload_ga_b();</script>') . '</code></p>',
				],
				[
					'id'       => 'ga_trk_server',
					'type'     => 'switch',
					'title'    => __('Activate server based tracking', 'wp-sdtrk'),
					'subtitle'     => __('Check to send Google Analytics-Events server-side to the API', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['ga_measurement_id', '!=', ''],
				],
				[
					'id'       => 'ga_trk_server_token',
					'type'     => 'text',
					'title'    => __('API Token', 'wp-sdtrk'),
					'subtitle'     => __('You can get the token within the Google Analytics Datastream settings', 'wp-sdtrk'),
					'required' => [['ga_trk_server', '=', '1'], ['ga_measurement_id', '!=', '']],
				],
				[
					'id'       => 'ga_trk_server_cookie_service',
					'type'     => 'select',
					'title'    => __('Choose cookie consent behavior', 'wp-sdtrk'),
					'options'  => [
						'none'     => __('Fire always', 'wp-sdtrk'),
						'borlabs'  => __('Borlabs Cookie', 'wp-sdtrk'),
					],
					'default'  => 'none',
					'required' => [['ga_trk_server', '=', '1'], ['ga_measurement_id', '!=', '']],
				],
				[
					'id'       => 'ga_trk_server_cookie_id',
					'type'     => 'text',
					'title'    => __('Cookie ID', 'wp-sdtrk'),
					'required' => [['ga_trk_server_cookie_service', '=', 'borlabs'], ['ga_trk_server', '=', '1'], ['ga_measurement_id', '!=', '']],
					'desc' => '<p style="color:#57b957">' . __('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk') . '</p><p><code style="font-style: italic;">' . htmlentities('<script>wp_sdtrk_backload_ga_s();</script>') . '</code></p>',
				],
			],
		]);

		// TIKTOK TRACKING
		Redux::set_section('wp_sdtrk_options', [
			'title'      => __('TikTok', 'wp-sdtrk'),
			'id'         => 'tiktok_tracking_section',
			'parent_id'  => 'tracking_services',
			'icon'       => 'el el-share',
			'subsection' => true,
			'fields'     => [
				[
					'id'    => 'tt_pixelid',
					'type'  => 'text',
					'title' => __('TikTok Pixel-ID', 'wp-sdtrk'),
					'subtitle'  => __('Insert your TikTok Pixel-ID', 'wp-sdtrk'),
				],
				[
					'id'       => 'tt_trk_debug',
					'type'     => 'switch',
					'title'    => __('Activate Debugging', 'wp-sdtrk'),
					'subtitle'     => __('Check to activate TikTok debugging', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['tt_pixelid', '!=', ''],
				],
				[
					'id'       => 'tt_trk_server_debug_code',
					'type'     => 'text',
					'title'    => __('Server Test-Code', 'wp-sdtrk'),
					'subtitle'     => __('If you want to debug the events in the TikTok events-manager, enter the test-code!', 'wp-sdtrk'),
					'required' => [['tt_trk_debug', '=', '1'], ['tt_pixelid', '!=', '']],
				],
				[
					'id'       => 'tt_trk_browser',
					'type'     => 'switch',
					'title'    => __('Activate browser based tracking', 'wp-sdtrk'),
					'subtitle'     => __('Check to fire TikTok browser pixel', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['tt_pixelid', '!=', ''],
				],
				[
					'id'       => 'tt_trk_browser_cookie_service',
					'type'     => 'select',
					'title'    => __('Choose cookie consent behavior', 'wp-sdtrk'),
					'options'  => [
						'none'     => __('Fire always', 'wp-sdtrk'),
						'borlabs'  => __('Borlabs Cookie', 'wp-sdtrk'),
					],
					'default'  => 'none',
					'required' => [['tt_trk_browser', '=', '1'], ['tt_pixelid', '!=', '']],
				],
				[
					'id'       => 'tt_trk_browser_cookie_id',
					'type'     => 'text',
					'title'    => __('Cookie ID', 'wp-sdtrk'),
					'required' => [['tt_trk_browser_cookie_service', '=', 'borlabs'], ['tt_trk_browser', '=', '1'], ['tt_pixelid', '!=', '']],
					'desc' => '<p style="color:#57b957">' . __('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk') . '</p><p><code style="font-style: italic;">' . htmlentities('<script>wp_sdtrk_backload_tt_b();</script>') . '</code></p>',
				],
				[
					'id'       => 'tt_trk_server',
					'type'     => 'switch',
					'title'    => __('Activate server based tracking', 'wp-sdtrk'),
					'subtitle'     => __('Check to send TikTok-Events server-side to the API', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['tt_pixelid', '!=', ''],
				],
				[
					'id'       => 'tt_trk_server_token',
					'type'     => 'text',
					'title'    => __('API Token', 'wp-sdtrk'),
					'subtitle'     => __('You can get the token within the TikTok Events-Manager settings', 'wp-sdtrk'),
					'required' => [['tt_trk_server', '=', '1'], ['tt_pixelid', '!=', '']],
				],
				[
					'id'       => 'tt_trk_server_cookie_service',
					'type'     => 'select',
					'title'    => __('Choose cookie consent behavior', 'wp-sdtrk'),
					'options'  => [
						'none'     => __('Fire always', 'wp-sdtrk'),
						'borlabs'  => __('Borlabs Cookie', 'wp-sdtrk'),
					],
					'default'  => 'none',
					'required' => [['tt_trk_server', '=', '1'], ['tt_pixelid', '!=', '']],
				],
				[
					'id'       => 'tt_trk_server_cookie_id',
					'type'     => 'text',
					'title'    => __('Cookie ID', 'wp-sdtrk'),
					'required' => [['tt_trk_server_cookie_service', '=', 'borlabs'], ['tt_trk_server', '=', '1'], ['tt_pixelid', '!=', '']],
					'desc' => '<p style="color:#57b957">' . __('For more accurate tracking, the following opt-in code should be stored in the cookie settings of Borlabs:', 'wp-sdtrk') . '</p><p><code style="font-style: italic;">' . htmlentities('<script>wp_sdtrk_backload_tt_s();</script>') . '</code></p>',
				],
			],
		]);

		// LINKEDIN TRACKING
		Redux::set_section('wp_sdtrk_options', [
			'title'      => __('LinkedIn', 'wp-sdtrk'),
			'id'         => 'linkedin_tracking_section',
			'parent_id'  => 'tracking_services',
			'icon'       => 'fa fa-linkedin',
			'subsection' => true,
			'fields'     => [
				[
					'id'    => 'lin_pixelid',
					'type'  => 'text',
					'title' => __('LinkedIn Partner-ID', 'wp-sdtrk'),
					'subtitle'  => __('Insert your LinkedIn Pixel-ID', 'wp-sdtrk'),
				],
				[
					'id'       => 'lin_trk_debug',
					'type'     => 'switch',
					'title'    => __('Activate Debugging', 'wp-sdtrk'),
					'subtitle'     => __('Check to activate LinkedIn debugging', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['lin_pixelid', '!=', ''],
				],
				[
					'id'       => 'lin_trk_browser',
					'type'     => 'switch',
					'title'    => __('Activate browser based tracking', 'wp-sdtrk'),
					'subtitle'     => __('Check to fire LinkedIn browser pixel', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['lin_pixelid', '!=', ''],
				],
				[
					'id'       => 'lin_trk_browser_cookie_service',
					'type'     => 'select',
					'title'    => __('Choose cookie consent behavior', 'wp-sdtrk'),
					'options'  => [
						'none'     => __('Fire always', 'wp-sdtrk'),
						'borlabs'  => __('Borlabs Cookie', 'wp-sdtrk'),
					],
					'default'  => 'none',
					'required' => [['lin_trk_browser', '=', '1'], ['lin_pixelid', '!=', '']],
				],
				[
					'id'       => 'lin_trk_browser_cookie_id',
					'type'     => 'text',
					'title'    => __('Cookie ID', 'wp-sdtrk'),
					'required' => [['lin_trk_browser_cookie_service', '=', 'borlabs'], ['lin_trk_browser', '=', '1'], ['lin_pixelid', '!=', '']],
				],

				// EVENT CONVERSION-MAPPING
				[
					'id'       => 'lin_trk_manage_mappings',
					'type'     => 'raw',
					'content'  => '<a href="' . admin_url('admin.php?page=wp_sdtrk_admin_map_linkedin') . '" class="button button-primary">' . __('Manage conversion mappings', 'wp-sdtrk') . '</a>',
				],
			],
		]);

		// FUNNELYTICS TRACKING
		Redux::set_section('wp_sdtrk_options', [
			'title'      => __('Funnelytics', 'wp-sdtrk'),
			'id'         => 'funnelytics_tracking_section',
			'parent_id'  => 'tracking_services',
			'icon'       => 'el el-glass',
			'subsection' => true,
			'fields'     => [
				[
					'id'    => 'fl_tracking_id',
					'type'  => 'text',
					'title' => __('Funnelytics Pixel-ID', 'wp-sdtrk'),
					'subtitle'  => __('Insert your Funnelytics Pixel-ID', 'wp-sdtrk'),
				],
				[
					'id'       => 'fl_trk_debug',
					'type'     => 'switch',
					'title'    => __('Activate Debugging', 'wp-sdtrk'),
					'subtitle'     => __('Check to activate Funnelytics debugging', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['fl_tracking_id', '!=', ''],
				],
				[
					'id'       => 'fl_trk_browser',
					'type'     => 'switch',
					'title'    => __('Activate browser based tracking', 'wp-sdtrk'),
					'subtitle'     => __('Check to fire Funnelytics browser pixel', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['fl_tracking_id', '!=', ''],
				],
				[
					'id'       => 'fl_trk_browser_cookie_service',
					'type'     => 'select',
					'title'    => __('Choose cookie consent behavior', 'wp-sdtrk'),
					'options'  => [
						'none'     => __('Fire always', 'wp-sdtrk'),
						'borlabs'  => __('Borlabs Cookie', 'wp-sdtrk'),
					],
					'default'  => 'none',
					'required' => [['fl_trk_browser', '=', '1'], ['fl_tracking_id', '!=', '']],
				],
				[
					'id'       => 'fl_trk_browser_cookie_id',
					'type'     => 'text',
					'title'    => __('Cookie ID', 'wp-sdtrk'),
					'required' => [['fl_trk_browser_cookie_service', '=', 'borlabs'], ['fl_trk_browser', '=', '1'], ['fl_tracking_id', '!=', '']],
				],
			],
		]);

		// MAUTIC TRACKING
		Redux::set_section('wp_sdtrk_options', [
			'title'      => __('Mautic', 'wp-sdtrk'),
			'id'         => 'mautic_tracking_section',
			'parent_id'  => 'tracking_services',
			'icon'       => 'el el-envelope',
			'subsection' => true,
			'fields'     => [
				[
					'id'    => 'mtc_tracking_id',
					'type'  => 'text',
					'title' => __('Mautic Base URL', 'wp-sdtrk'),
					'subtitle'  => __('Insert the base-url of your mautic installation', 'wp-sdtrk'),
				],
				[
					'id'       => 'mtc_trk_debug',
					'type'     => 'switch',
					'title'    => __('Activate Debugging', 'wp-sdtrk'),
					'subtitle'     => __('Check to activate Mautic debugging', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['mtc_tracking_id', '!=', ''],
				],
				[
					'id'       => 'mtc_trk_browser',
					'type'     => 'switch',
					'title'    => __('Activate browser based tracking', 'wp-sdtrk'),
					'subtitle'     => __('Check to fire Mautic browser pixel', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['mtc_tracking_id', '!=', ''],
				],
				[
					'id'       => 'mtc_trk_browser_cookie_service',
					'type'     => 'select',
					'title'    => __('Choose cookie consent behavior', 'wp-sdtrk'),
					'options'  => [
						'none'     => __('Fire always', 'wp-sdtrk'),
						'borlabs'  => __('Borlabs Cookie', 'wp-sdtrk'),
					],
					'default'  => 'none',
					'required' => [['mtc_trk_browser', '=', '1'], ['mtc_tracking_id', '!=', '']],
				],
				[
					'id'       => 'mtc_trk_browser_cookie_id',
					'type'     => 'text',
					'title'    => __('Cookie ID', 'wp-sdtrk'),
					'required' => [['mtc_trk_browser_cookie_service', '=', 'borlabs'], ['mtc_trk_browser', '=', '1'], ['mtc_tracking_id', '!=', '']],
				],
			],
		]);

		// MATOMO TRACKING
		Redux::set_section('wp_sdtrk_options', [
			'title'      => __('Matomo', 'wp-sdtrk'),
			'id'         => 'matomo_tracking_section',
			'parent_id'  => 'tracking_services',
			'icon'       => 'el el-idea',
			'subsection' => true,
			'fields'     => [
				[
					'id'    => 'mtm_tracking_id',
					'type'  => 'text',
					'title' => __('Matomo Base URL', 'wp-sdtrk'),
					'subtitle'  => __('Insert the base-url of your matomo installation', 'wp-sdtrk'),
				],
				[
					'id'    => 'mtm_site_id',
					'type'  => 'text',
					'title' => __('Matomo Site ID', 'wp-sdtrk'),
					'subtitle'  => __('Insert the site ID of your Matomo installation', 'wp-sdtrk'),
					'required' => [['mtm_tracking_id', '!=', '']],
				],
				[
					'id'    => 'mtm_api_key',
					'type'  => 'text',
					'title' => __('Matomo API Key', 'wp-sdtrk'),
					'subtitle'  => __('Insert the API key of your Matomo installation', 'wp-sdtrk'),
					'required' => ['mtm_site_id', '!=', ''],
				],
				[
					'id'       => 'mtm_trk_debug',
					'type'     => 'switch',
					'title'    => __('Activate Debugging', 'wp-sdtrk'),
					'subtitle'     => __('Check to activate Matomo debugging', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['mtm_api_key', '!=', ''],
				],
				[
					'id'       => 'mtm_trk_browser',
					'type'     => 'switch',
					'title'    => __('Activate browser based tracking', 'wp-sdtrk'),
					'subtitle'     => __('Check to fire Matomo browser pixel', 'wp-sdtrk'),
					'default'  => 0,
					'required' => ['mtm_api_key', '!=', ''],
				],
				[
					'id'       => 'mtm_trk_browser_cookie_service',
					'type'     => 'select',
					'title'    => __('Choose cookie consent behavior', 'wp-sdtrk'),
					'options'  => [
						'none'     => __('Fire always', 'wp-sdtrk'),
						'borlabs'  => __('Borlabs Cookie', 'wp-sdtrk'),
					],
					'default'  => 'none',
					'required' => ['mtm_trk_browser', '=', '1'],
				],
				[
					'id'       => 'mtm_trk_browser_cookie_id',
					'type'     => 'text',
					'title'    => __('Cookie ID', 'wp-sdtrk'),
					'required' => ['mtm_trk_browser_cookie_service', '=', 'borlabs'],
				],
			],
		]);

		/**
		 * ============================================================
		 * SECTION: Data Sources
		 * ============================================================
		 */

		Redux::set_section('wp_sdtrk_options', [
			'title'  => __('Data Sources', 'wp-sdtrk'),
			'id'     => 'data_sources_section',
			'icon'   => 'el el-network',
			'fields' => [
				[
					'id'    => 'ds24_encrypt_data',
					'type'  => 'switch',
					'title' => __('Activate Digistore24 data-decryption', 'wp-sdtrk'),
					'subtitle'  => __('Check to decrypt GET-Parameter from Digistore24 before handling', 'wp-sdtrk'),
					'default' => 0,
				],
				[
					'id'       => 'ds24_encrypt_data_key',
					'type'     => 'text',
					'title'    => __('ThankYou-Key', 'wp-sdtrk'),
					'subtitle'     => __('Please enter the ThankYou-Key which you have set in Digistore24', 'wp-sdtrk'),
					'required' => ['ds24_encrypt_data', '=', '1'],
				],
			],
		]);

		/**
		 * ============================================================
		 * SECTION: Tutorials
		 * ============================================================
		 */

		Redux::set_section('wp_sdtrk_options', [
			'title'  => __('Tutorials', 'wp-sdtrk'),
			'id'     => 'tutorials_section',
			'icon'   => 'fa fa-book',
			'fields' => [
				[
					'id'    => 'tutorial_video_setup',
					'type'  => 'raw',
					'title' => __('Setup Tutorial', 'wp-sdtrk'),
					'content' => '<iframe width="100%" height="400" src="https://player.vimeo.com/video/587429111?h=6660e0f5f6" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>',
				],
				[
					'id'    => 'tutorial_parameters',
					'type'  => 'raw',
					'title' => __('Supported GET-Parameters', 'wp-sdtrk'),
					'content' => '<p><strong>' . __('Note:', 'wp-sdtrk') . '</strong> ' . __('Enable "appending order details to thank you URL" on services like Digistore24 so that the parameters below are passed automatically.', 'wp-sdtrk') . '</p>
                <h4>' . __('Transaction-ID', 'wp-sdtrk') . '</h4>
                <p><strong>' . __('Parameter-Name(s):', 'wp-sdtrk') . '</strong> order_id</p>
                <p><strong>' . __('Type:', 'wp-sdtrk') . '</strong> string</p>
                <p><strong>' . __('Example:', 'wp-sdtrk') . '</strong> <code>' . get_home_url() . '?order_id=9854</code></p>
                
                <h4>' . __('Product-ID', 'wp-sdtrk') . '</h4>
                <p><strong>' . __('Parameter-Name(s):', 'wp-sdtrk') . '</strong> prodid | product_id</p>
                <p><strong>' . __('Type:', 'wp-sdtrk') . '</strong> string</p>
                <p><strong>' . __('Example:', 'wp-sdtrk') . '</strong> <code>' . get_home_url() . '?prodid=1337</code></p>
                
                <h4>' . __('Product-Name', 'wp-sdtrk') . '</h4>
                <p><strong>' . __('Parameter-Name(s):', 'wp-sdtrk') . '</strong> product_name</p>
                <p><strong>' . __('Type:', 'wp-sdtrk') . '</strong> string</p>
                <p><strong>' . __('Example:', 'wp-sdtrk') . '</strong> <code>' . get_home_url() . '?product_name=newproduct</code></p>
                
                <h4>' . __('Value', 'wp-sdtrk') . '</h4>
                <p><strong>' . __('Parameter-Name(s):', 'wp-sdtrk') . '</strong> value | net_amount | amount</p>
                <p><strong>' . __('Type:', 'wp-sdtrk') . '</strong> number</p>
                <p><strong>' . __('Example:', 'wp-sdtrk') . '</strong> <code>' . get_home_url() . '?value=99</code></p>
                
                <h4>' . __('Type', 'wp-sdtrk') . '</h4>
                <p><strong>' . __('Parameter-Name(s):', 'wp-sdtrk') . '</strong> type</p>
                <p><strong>' . __('Accepts:', 'wp-sdtrk') . '</strong> AddToCart | Purchase | CompleteRegistration | Lead | InitiateCheckout | ViewContent</p>
                <p><strong>' . __('Example:', 'wp-sdtrk') . '</strong> <code>' . get_home_url() . '?type=Lead</code></p>
                
                <h4>' . __('UTM', 'wp-sdtrk') . '</h4>
                <p><strong>' . __('Parameter-Name(s):', 'wp-sdtrk') . '</strong> utm_source | utm_campaign | utm_term | utm_medium | utm_content</p>
                <p><strong>' . __('Type:', 'wp-sdtrk') . '</strong> string</p>
                <p><strong>' . __('Example:', 'wp-sdtrk') . '</strong> <code>' . get_home_url() . '?utm_source=meta&utm_medium=cpc</code></p>
                <p><strong style="color: #d63638;">' . __('Note:', 'wp-sdtrk') . '</strong> ' . __('UTM parameters are stored in cookies and automatically passed on further visits!', 'wp-sdtrk') . '</p>',
				],
			],
		]);
	}

	/**
	 * Register Redux Metabox for Pages
	 */
	public function register_redux_metabox()
	{
		if (!class_exists('Redux')) {
			return;
		}

		// WICHTIG: opt_name muss mit deinem Redux Options Panel übereinstimmen
		$opt_name = 'wp_sdtrk_options';

		Redux_Metaboxes::set_box(
			$opt_name,
			array(
				'id'         => 'wp_sdtrk_page_metabox',
				'title'      => __('Smart Server Side Tracking Plugin', 'wp-sdtrk'),
				'post_types' => array('page'), // Kann erweitert werden: array('page', 'post')
				'position'   => 'advanced',    // normal, advanced, side
				'priority'   => 'default',     // high, core, default, low
				'sections'   => array(
					array(
						'title'  => __('Basic Tracking Settings', 'wp-sdtrk'),
						'id'     => 'wp_sdtrk_basic_settings',
						'icon'   => 'el-icon-cog',
						'fields' => array(
							array(
								'id'          => 'wp_sdtrk_product_id',
								'type'        => 'text',
								'title'       => __('Product ID', 'wp-sdtrk'),
								'subtitle'    => __('Please enter a product id in order to track the ViewContent-Event', 'wp-sdtrk'),
								'placeholder' => __('Product-ID', 'wp-sdtrk'),
							),
							array(
								'id'       => 'wp_sdtrk_bypass_consent',
								'type'     => 'switch',
								'title'    => __('Bypass Tracking-Consent', 'wp-sdtrk'),
								'subtitle' => __('Check to track all visitors of this page regardless of their cookie consent', 'wp-sdtrk'),
								'default'  => 0,
							),
						),
					),
				),
			)
		);
	}


	/**
	 * Lädt Skripte und Styles für die Produkte-Adminseite.
	 *
	 */
	public function enqueue_wp_sdtrk_admin_map_linkedin(): void
	{
		// JS
		wp_enqueue_script(
			$this->wp_sdtrk . '-admin-map-linkedin-js',
			plugin_dir_url(__FILE__) . 'js/wp-sdtrk-admin-map-linkedin.js',
			['jquery', $this->wp_sdtrk],
			$this->version,
			true
		);

		// Konfig für AJAX im JS
		wp_localize_script(
			$this->wp_sdtrk . '-admin-map-linkedin-js',
			'SDTRK_Linkedin',
			[
				'ajaxUrl'    => admin_url('admin-ajax.php'),
				'nonce'      => wp_create_nonce('security_wp-sdtrk'),
			]
		);
	}

	/**
	 * Register the page for mapping products.
	 *
	 * This function registers a new top-level menu page in the WordPress admin area.
	 * The page is accessible for users with the 'manage_options' capability, and is
	 * rendered by the 'render_page_map_products' method of this class.
	 *
	 * The CSS code added in the 'admin_head' action is used to hide the menu item
	 * from the admin menu, so that the page is only accessible via the link in the
	 * FluentCommunity Extreme settings page.
	 */
	public function register_page_wp_sdtrk_admin_map_linkedin(): void
	{
		add_action('admin_head', function () {
			echo '<style>#toplevel_page_wp_sdtrk_admin_map_linkedin { display: none !important; }</style>';
		});
		add_menu_page(
			'LinkedIn Conversion Mapping',           // Page Title
			'LinkedIn Conversion Mapping',           // Menu Title
			'manage_options',               // Capability
			'wp_sdtrk_admin_map_linkedin',                 // Menu Slug
			[$this, 'render_page_wp_sdtrk_admin_map_linkedin'], // Callback
			'',                             // Icon
			null                            // Position
		);
	}

	/**
	 * Register the page for managing linkedin mappings.
	 *
	 * This function registers a new top-level menu page in the WordPress admin area.
	 * The page is accessible for users with the 'manage_options' capability, and is
	 * rendered by the 'render_page_manage_linkedin_mappings' method of this class.
	 *
	 * The CSS code added in the 'admin_head' action is used to hide the menu item
	 * from the admin menu, so that the page is only accessible via the link in the
	 * FluentCommunity Extreme settings page.
	 */
	public function render_page_wp_sdtrk_admin_map_linkedin(): void
	{
		$this->enqueue_custom_page_css();
		$view = plugin_dir_path(dirname(__FILE__)) . 'templates/wp-sdtrk-admin-map-linkedin.php';
		if (file_exists($view)) {
			include $view;
		}
	}


	/**
	 * Injects global admin UI components for SDTRK pages.
	 *
	 * This function includes specific UI components, such as a notice box and
	 * a modal confirmation dialog, only on pages related to FluentCommunity Extreme (SDTRK).
	 * It checks the current screen identifier to ensure these components are
	 * loaded exclusively on SDTRK-related admin pages.
	 *
	 * @since 1.0.0
	 */

	/**
	 * Injects global admin UI components for SDTRK pages.
	 *
	 * This function includes specific UI components, such as a notice box and
	 * a modal confirmation dialog, only on pages related to FluentCommunity Extreme (SDTRK).
	 * It checks the current screen identifier to ensure these components are
	 * loaded exclusively on SDTRK-related admin pages.
	 *
	 * @since 1.0.0
	 */
	public function inject_global_admin_ui(): void
	{
		//only on SDTRK pages
		$current_screen = get_current_screen();
		if (strpos($current_screen->id, 'sdtrk_') === false) {
			return; // Nur für SDTRK-Seiten
		}

		// Hinweis-Box
		$notice_path = plugin_dir_path(dirname(__FILE__)) . 'admin/partials/wp-sdtrk-admin-notice.php';
		if (file_exists($notice_path)) {
			include $notice_path;
		}

		// Modal
		$modal_path = plugin_dir_path(dirname(__FILE__)) . 'templates/partials/html-modal-confirm.php';
		if (file_exists($modal_path)) {
			include $modal_path;
		}
	}

	public function after_redux_save($options, $changed_values)
	{
		//Do nothing if no values changed
		if (empty($changed_values)) {
			return;
		}
		// Check if linked in triggers are still valid
		$mappings = WP_SDTRK_Helper_Linkedin::get_all_mappings();
		foreach ($mappings as $mapping) {
			if (!$mapping->is_valid_event()) {
				// Delete invalid mapping
				$mapping->delete();
			}
		}
	}

	/**
	 * Registers the Ajax handler for the admin area.
	 *
	 * This function ensures that the Wp_Sdtrk_Admin_Ajax_Handler class is initialized
	 * and calls the handle_admin_ajax_callback method of that class to register the
	 * form processing callback functions.
	 *
	 * @since 1.0.0
	 */
	public function register_ajax_handler(): void
	{
		//Make sure its initialized
		if (!isset($this->admin_ajax_handler)) {
			$this->admin_ajax_handler = new Wp_Sdtrk_Admin_Ajax_Handler();
		}

		$this->admin_ajax_handler->handle_admin_ajax_callback();
	}

	/**
	 * Registers the form handler for the admin area.
	 *
	 * This function ensures that the Wp_Sdtrk_Admin_Form_Handler class is initialized
	 * and calls the handle_admin_form_callback method of that class to register the
	 * form processing callback functions.
	 *
	 * @since 1.0.0
	 */
	public function register_form_handler(): void
	{
		//Make sure its initialized
		if (!isset($this->admin_form_handler)) {
			$this->admin_form_handler = new Wp_Sdtrk_Admin_Form_Handler();
		}

		$this->admin_form_handler->handle_admin_form_callback();
	}
}
