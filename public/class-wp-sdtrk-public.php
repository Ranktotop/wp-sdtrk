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
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Wp_Sdtrk
 * @subpackage Wp_Sdtrk/public
 * @author     Your Name <email@example.com>
 */
class Wp_Sdtrk_Public
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
	private Wp_Sdtrk_Public_Ajax_Handler $public_ajax_handler;
	private Wp_Sdtrk_Public_Form_Handler $public_form_handler;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $wp_sdtrk       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($wp_sdtrk, $version)
	{

		$this->wp_sdtrk = $wp_sdtrk;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
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
	 * @since    1.0.0
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

	public function register_front_end_routes(): void {}


	/**
	 * Liefert das Template für unsere Bestellseite
	 */
	public function load_custom_template($template)
	{
		return $template;
	}

	/**
	 * Registers the form handler for the public area.
	 *
	 * This function ensures that the Wp_Sdtrk_Public_Form_Handler class is initialized
	 * and calls the handle_public_form_callback method of that class to register the
	 * form processing callback functions.
	 *
	 * @since 1.0.0
	 */
	public function register_form_handler(): void
	{
		//Make sure its initialized
		if (!isset($this->public_form_handler)) {
			$this->public_form_handler = new Wp_Sdtrk_Public_Form_Handler();
		}

		$this->public_form_handler->handle_public_form_callback();
	}

	/**
	 * Registers the Ajax handler for the public area.
	 *
	 * This function ensures that the Wp_Sdtrk_Public_Ajax_Handler class is initialized
	 * and calls the handle_public_ajax_callback method of that class to register the
	 * form processing callback functions.
	 *
	 * @since 1.0.0
	 */
	public function register_ajax_handler(): void
	{
		//Make sure its initialized
		if (!isset($this->public_ajax_handler)) {
			$this->public_ajax_handler = new Wp_Sdtrk_Public_Ajax_Handler();
		}

		$this->public_ajax_handler->handle_public_ajax_callback();
	}

	/********************************
	 *  TRACKER SCRIPT REGISTRATION
	 *******************************/

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
		$meta_pixelId = WP_SDTRK_Helper_Options::get_string_option('meta_pixelid');

		// Browser Settings
		$browserEnabled = WP_SDTRK_Helper_Options::get_bool_option('meta_trk_browser', false);
		$browserCookieService = WP_SDTRK_Helper_Options::get_string_option('meta_trk_browser_cookie_service');
		$browserCookieId = WP_SDTRK_Helper_Options::get_string_option('meta_trk_browser_cookie_id');

		// Server Settings
		$serverEnabled = WP_SDTRK_Helper_Options::get_bool_option('meta_trk_server', false);
		$serverCookieService = WP_SDTRK_Helper_Options::get_string_option('meta_trk_server_cookie_service');
		$serverCookieId = WP_SDTRK_Helper_Options::get_string_option('meta_trk_server_cookie_id');

		// Debug
		$debugEnabled = WP_SDTRK_Helper_Options::get_bool_option('meta_trk_debug', false);

		// Merge to array
		$localizedData['pid'] = $meta_pixelId;
		$localizedData['b_e'] = $browserEnabled;
		$localizedData['b_cs'] = $browserCookieService;
		$localizedData['b_ci'] = $browserCookieId;
		$localizedData['s_e'] = $serverEnabled;
		$localizedData['s_cs'] = $serverCookieService;
		$localizedData['s_ci'] = $serverCookieId;
		$localizedData['dbg'] = $debugEnabled;

		// Register scripts
		wp_register_script($this->get_jsHandler('name', 'meta'), plugins_url("js/" . $this->get_jsHandler('file', 'meta') . $loadMinified . ".js", __FILE__), array(), $this->version, false);
		wp_localize_script($this->get_jsHandler('name', 'meta'), $this->get_jsHandler('var', 'meta'), $localizedData);
	}

	/**
	 * Collect all GA-Data and pass them to JS
	 */
	private function registerScript_gaTracker($loadMinified = "")
	{
		// Init
		$localizedData = array();

		// Measurement ID
		$google_measurement_id = WP_SDTRK_Helper_Options::get_string_option('ga_measurement_id');

		// Browser Settings
		$browserEnabled = WP_SDTRK_Helper_Options::get_bool_option('ga_trk_browser', false);
		$browserCookieService = WP_SDTRK_Helper_Options::get_string_option('ga_trk_browser_cookie_service');
		$browserCookieId = WP_SDTRK_Helper_Options::get_string_option('ga_trk_browser_cookie_id');

		// Server Settings
		$serverEnabled = WP_SDTRK_Helper_Options::get_bool_option('ga_trk_server', false);
		$serverCookieService = WP_SDTRK_Helper_Options::get_string_option('ga_trk_server_cookie_service');
		$serverCookieId = WP_SDTRK_Helper_Options::get_string_option('ga_trk_server_cookie_id');

		// Debug
		$debugEnabled = WP_SDTRK_Helper_Options::get_bool_option('ga_trk_debug', false);

		// Merge to array
		$localizedData['pid'] = $google_measurement_id;
		$localizedData['b_e'] = $browserEnabled;
		$localizedData['b_cs'] = $browserCookieService;
		$localizedData['b_ci'] = $browserCookieId;
		$localizedData['s_e'] = $serverEnabled;
		$localizedData['s_cs'] = $serverCookieService;
		$localizedData['s_ci'] = $serverCookieId;
		$localizedData['debug'] = $debugEnabled;
		$localizedData['dbg'] = $debugEnabled; // this is for frontend debug log

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
		$tiktok_pixelId = WP_SDTRK_Helper_Options::get_string_option('tt_pixelid');

		// Browser Settings
		$browserEnabled = WP_SDTRK_Helper_Options::get_bool_option('tt_trk_browser', false);
		$browserCookieService = WP_SDTRK_Helper_Options::get_string_option('tt_trk_browser_cookie_service');
		$browserCookieId = WP_SDTRK_Helper_Options::get_string_option('tt_trk_browser_cookie_id');

		// Server Settings
		$serverEnabled = WP_SDTRK_Helper_Options::get_bool_option('tt_trk_server', false);
		$serverCookieService = WP_SDTRK_Helper_Options::get_string_option('tt_trk_server_cookie_service');
		$serverCookieId = WP_SDTRK_Helper_Options::get_string_option('tt_trk_server_cookie_id');

		// Debug
		$debugEnabled = WP_SDTRK_Helper_Options::get_bool_option('tt_trk_debug', false);

		// Merge to array
		$localizedData['pid'] = $tiktok_pixelId;
		$localizedData['b_e'] = $browserEnabled;
		$localizedData['b_cs'] = $browserCookieService;
		$localizedData['b_ci'] = $browserCookieId;
		$localizedData['s_e'] = $serverEnabled;
		$localizedData['s_cs'] = $serverCookieService;
		$localizedData['s_ci'] = $serverCookieId;
		$localizedData['dbg'] = $debugEnabled;

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
		$linkedin_pixelId = WP_SDTRK_Helper_Options::get_string_option('lin_pixelid');

		// Browser Settings
		$browserEnabled = WP_SDTRK_Helper_Options::get_bool_option('lin_trk_browser', false);
		$browserCookieService = WP_SDTRK_Helper_Options::get_string_option('lin_trk_browser_cookie_service');
		$browserCookieId = WP_SDTRK_Helper_Options::get_string_option('lin_trk_browser_cookie_id');

		// Server Settings (not available for LinkedIn yet)
		$serverEnabled = false;
		$serverCookieService = false;
		$serverCookieId = false;

		// Debug
		$debugEnabled = WP_SDTRK_Helper_Options::get_bool_option('lin_trk_debug', false);


		// LinkedIn Mappings
		// Event Mappings
		$linkedinEventMap = array();
		$mappings = WP_SDTRK_Helper_Linkedin::get_all_mappings();

		foreach ($mappings as $mapping) {
			$rules = array();
			foreach ($mapping->get_rules() as $rule) {
				$rules[$rule->get_key_name()] = $rule->get_value();
			}
			array_push($linkedinEventMap, array(
				'eventName' => $mapping->get_event(),
				'convId' => $mapping->get_conversion_id(),
				'rules' => $rules
			));
		}

		// Button Mappings
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
		$localizedData['map_ev'] = $linkedinEventMap;
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

		//read metabox options
		$prodId = WP_SDTRK_Helper_Options::get_string_metabox_option($postId, 'wp_sdtrk_product_id');
		$trkOverwrite = WP_SDTRK_Helper_Options::get_bool_metabox_option($postId, 'wp_sdtrk_bypass_consent');

		// Get the brandName from Settings
		$brandName = WP_SDTRK_Helper_Options::get_string_option('brandname');
		$brandName = $brandName ? $brandName : get_bloginfo('name');

		// Get the time triggers from settings (returns empty list if time tracking is disabled)
		$time_triggers = WP_SDTRK_Helper_Options::get_time_triggers();
		if (sizeof($time_triggers) > 0) {
			$localizedData['timeTrigger'] = $time_triggers;
		}

		// Get the scroll triggers from settings (returns empty list if scroll tracking is disabled)
		$scroll_triggers = WP_SDTRK_Helper_Options::get_scroll_triggers();
		if (sizeof($scroll_triggers) > 0) {
			$localizedData['scrollTrigger'] = $scroll_triggers;
		}

		// Get click and visibility settings
		$localizedData['clickTrigger'] = WP_SDTRK_Helper_Options::get_bool_option('trk_buttons', false);
		$localizedData['visibilityTrigger'] = WP_SDTRK_Helper_Options::get_bool_option('trk_visibility', false);

		// Infos about current page
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
		$localizedData['pmap'] = Wp_Sdtrk_Helper::wp_sdtrk_getParamNames(); // for stripping data from url before sending url to service (required by meta for example)

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
			$this->get_jsHandler('name', 'meta'),
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

	/*****************************
	 *  TRACKER FUNCTIONS
	 ****************************/

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

	/*****************************
	 *  HELPER FUNCTIONS
	 ****************************/

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
}
