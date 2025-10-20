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

		// Seitenspezifische Skripte
		if (strpos($hook_suffix, 'sdtrk_settings') !== false) {
			// Community-API Assets
			$this->enqueue_community_api_assets($hook_suffix);
		}

		if ($hook_suffix === 'toplevel_page_sdtrk_admin_manage_products') {
			// Products Assets
			$this->enqueue_products_assets($hook_suffix);
		}

		if ($hook_suffix === 'toplevel_page_sdtrk_admin_map_products') {
			// Mappings Assets  
			$this->enqueue_mappings_assets($hook_suffix);
		}

		if ($hook_suffix === 'toplevel_page_sdtrk_admin_manage_access') {
			// Access Assets
			$this->enqueue_access_assets($hook_suffix);
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
			'msg_confirm_delete_product' => __('Do you really want to delete the product? This action cannot be undone!', 'wp-sdtrk'),
			'msg_confirm_delete_product_mapping' => __('Do you really want to delete all mappings of this product? This action cannot be undone!', 'wp-sdtrk'),
			'msg_confirm_delete_access_rule' => __('Are you sure you want to delete the rule? This action cannot be undone!', 'wp-sdtrk'),
			'notice_success' => __('Saved successfully!', 'wp-sdtrk'),
			'notice_error' => __('Error occurred!', 'wp-sdtrk'),
			'label_edit' => __('Edit', 'wp-sdtrk'),
			'label_save' => __('Save', 'wp-sdtrk'),
			'label_confirm' => __('Are you sure?', 'wp-sdtrk'),
		));
	}

	/**
	 * Creates HTML for a single API endpoint description with detailed parameter info
	 *
	 * @param string $endpoint_path The endpoint URL path
	 * @param string $method HTTP method (GET, POST, DELETE)
	 * @param string $description Description of what the endpoint does
	 * @param array $parameters Optional array of parameters with details
	 * @return string HTML content
	 */
	private function create_endpoint_desc(string $endpoint_path, string $method, string $description, array $parameters = []): string
	{
		$base_url = untrailingslashit(rest_url('wp-sdtrk/v1'));
		$url = $base_url . '/' . ltrim($endpoint_path, '/');

		$parameters_html = '';
		if (!empty($parameters)) {
			$parameters_html = '<div class="sdtrk-endpoint-parameters">';
			$parameters_html .= '<h4>' . __('Parameters:', 'wp-sdtrk') . '</h4>';
			$parameters_html .= '<table class="sdtrk-params-table">';
			$parameters_html .= '<thead><tr>';
			$parameters_html .= '<th>' . __('Name', 'wp-sdtrk') . '</th>';
			$parameters_html .= '<th>' . __('Type', 'wp-sdtrk') . '</th>';
			$parameters_html .= '<th>' . __('Location', 'wp-sdtrk') . '</th>';
			$parameters_html .= '<th>' . __('Required', 'wp-sdtrk') . '</th>';
			$parameters_html .= '<th>' . __('Description', 'wp-sdtrk') . '</th>';
			$parameters_html .= '</tr></thead><tbody>';

			foreach ($parameters as $param) {
				$required_badge = $param['required']
					? '<span class="sdtrk-required-badge">' . __('Required', 'wp-sdtrk') . '</span>'
					: '<span class="sdtrk-optional-badge">' . __('Optional', 'wp-sdtrk') . '</span>';

				$parameters_html .= sprintf(
					'<tr>
                    <td><code>%s</code></td>
                    <td><span class="sdtrk-type-badge sdtrk-type-%s">%s</span></td>
                    <td><span class="sdtrk-location-badge sdtrk-location-%s">%s</span></td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>',
					esc_html($param['name']),
					esc_attr(strtolower($param['type'])),
					esc_html($param['type']),
					esc_attr(strtolower($param['location'])),
					esc_html($param['location']),
					$required_badge,
					esc_html($param['description'] ?? '')
				);
			}

			$parameters_html .= '</tbody></table></div>';
		}

		return sprintf(
			'<div class="sdtrk-endpoint-item">
            <div class="sdtrk-endpoint-url">
                <code>%s</code>
                <span class="sdtrk-endpoint-method sdtrk-method-%s">%s</span>
            </div>
            <div class="sdtrk-endpoint-description">%s</div>
            %s
        </div>',
			esc_html($url),
			strtolower($method),
			esc_html($method),
			esc_html($description),
			$parameters_html
		);
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
			'menu_title'      => 'FluentCommunity Extreme',
			'page_title'      => 'FluentCommunity Extreme',
			'menu_type'       => 'menu',
			'page_priority' => 80,
			'allow_sub_menu'  => true,
			'page_slug'       => 'sdtrk_settings',
			'display_version' => false,
		]);

		// ► Schlüssel vorab holen (gibt '' zurück, wenn noch nicht gesetzt)
		$api_key_ipn   = Redux::get_option('wp_sdtrk_options', 'api_key_ipn');
		$api_key_admin = Redux::get_option('wp_sdtrk_options', 'api_key_admin');

		// Platzhalter einsetzen, falls leer
		$ipn_param   = $api_key_ipn   ?: '{ipn_api_key}';
		$admin_param = $api_key_admin ?: '{admin_api_key}';

		Redux::set_section('wp_sdtrk_options', [
			'title'  => __('API', 'wp-sdtrk'),
			'id'     => 'general_section',
			'desc'   => __('API-Settings', 'wp-sdtrk'),

			// ───────────────────────────────────────────────
			// 1) FELDER ZUERST
			// ───────────────────────────────────────────────
			'fields' => [
				[
					'id'    => 'api_key_ipn',
					'type'  => 'text',
					'title' => __('API Key (IPN)', 'wp-sdtrk'),
					'desc'  => __('Used to validate IPN requests.', 'wp-sdtrk'),
				],
				[
					'id'    => 'api_key_admin',
					'type'  => 'text',
					'title' => __('API Key (Admin)', 'wp-sdtrk'),
					'desc'  => __('Used to validate Admin requests to the REST API.', 'wp-sdtrk'),
				],

				// ────────────────────────────────────────────
				// 2) HINWEIS-BLOCK MIT URL-LISTE
				// ────────────────────────────────────────────
				[
					'id'      => 'api_endpoints_info',
					'type'    => 'raw',
					'title'   => __('Endpoint-Overview', 'wp-sdtrk'),
					'content' => sprintf(
						'<div class="sdtrk-endpoint-list">%s</div>',
						implode('', [
							$this->create_endpoint_desc(
								"ipn?apikey={$ipn_param}",
								'POST',
								__('Receives instant payment notifications from external payment providers (CopeCart, Digistore24) to automatically create users and grant access to products.', 'wp-sdtrk'),
								[
									[
										'name' => 'apikey',
										'type' => 'string',
										'location' => 'URL',
										'required' => true,
										'description' => __('IPN API key for authentication', 'wp-sdtrk')
									],
									[
										'name' => 'user_email',
										'type' => 'string',
										'location' => 'JSON',
										'required' => true,
										'description' => __('Email address of the customer', 'wp-sdtrk')
									],
									[
										'name' => 'product_id',
										'type' => 'string',
										'location' => 'JSON',
										'required' => true,
										'description' => __('External product identifier', 'wp-sdtrk')
									],
									[
										'name' => 'transaction_id',
										'type' => 'string',
										'location' => 'JSON',
										'required' => true,
										'description' => __('Unique transaction identifier', 'wp-sdtrk')
									],
									[
										'name' => 'source',
										'type' => 'string',
										'location' => 'JSON',
										'required' => true,
										'description' => __('Payment provider name (e.g. copecart, digistore24)', 'wp-sdtrk')
									],
								]
							),
							$this->create_endpoint_desc(
								"register-user?apikey={$admin_param}",
								'POST',
								__('Creates or updates a WordPress user and automatically grants access to all public FluentCommunity spaces and courses.', 'wp-sdtrk'),
								[
									[
										'name' => 'apikey',
										'type' => 'string',
										'location' => 'URL',
										'required' => true,
										'description' => __('Admin API key for authentication', 'wp-sdtrk')
									],
									[
										'name' => 'user_email',
										'type' => 'string',
										'location' => 'JSON',
										'required' => true,
										'description' => __('Valid email address of the user', 'wp-sdtrk')
									],
									[
										'name' => 'user_first_name',
										'type' => 'string',
										'location' => 'JSON',
										'required' => false,
										'description' => __('First name of the user', 'wp-sdtrk')
									],
									[
										'name' => 'user_last_name',
										'type' => 'string',
										'location' => 'JSON',
										'required' => false,
										'description' => __('Last name of the user', 'wp-sdtrk')
									],
									[
										'name' => 'send_welcome_email',
										'type' => 'boolean',
										'location' => 'JSON',
										'required' => false,
										'description' => __('Whether to send welcome email to new user (default: true)', 'wp-sdtrk')
									],
								]
							),
							$this->create_endpoint_desc(
								"access/status?user_id={user_id}&entity_id={entity_id}&apikey={$admin_param}",
								'GET',
								__('Checks if a specific user has access to a specific space/course. Returns boolean status.', 'wp-sdtrk'),
								[
									[
										'name' => 'user_id',
										'type' => 'integer',
										'location' => 'URL',
										'required' => true,
										'description' => __('WordPress user ID', 'wp-sdtrk')
									],
									[
										'name' => 'entity_id',
										'type' => 'integer',
										'location' => 'URL',
										'required' => true,
										'description' => __('FluentCommunity space or course ID', 'wp-sdtrk')
									],
									[
										'name' => 'apikey',
										'type' => 'string',
										'location' => 'URL',
										'required' => true,
										'description' => __('Admin API key for authentication', 'wp-sdtrk')
									],
								]
							),
							$this->create_endpoint_desc(
								"access/sources?user_id={user_id}&entity_id={entity_id}&apikey={$admin_param}",
								'GET',
								__('Returns detailed information about why access was granted or denied, including all active sources and overrides.', 'wp-sdtrk'),
								[
									[
										'name' => 'user_id',
										'type' => 'integer',
										'location' => 'URL',
										'required' => true,
										'description' => __('WordPress user ID', 'wp-sdtrk')
									],
									[
										'name' => 'entity_id',
										'type' => 'integer',
										'location' => 'URL',
										'required' => true,
										'description' => __('FluentCommunity space or course ID', 'wp-sdtrk')
									],
									[
										'name' => 'apikey',
										'type' => 'string',
										'location' => 'URL',
										'required' => true,
										'description' => __('Admin API key for authentication', 'wp-sdtrk')
									],
								]
							),
							$this->create_endpoint_desc(
								"mapping?apikey={$admin_param}",
								'POST',
								__('Creates a new mapping between a product ID and a space ID. Requires product_id and space_id in POST body.', 'wp-sdtrk'),
								[
									[
										'name' => 'apikey',
										'type' => 'string',
										'location' => 'URL',
										'required' => true,
										'description' => __('Admin API key for authentication', 'wp-sdtrk')
									],
									[
										'name' => 'product_id',
										'type' => 'integer',
										'location' => 'JSON',
										'required' => true,
										'description' => __('Internal product ID to map', 'wp-sdtrk')
									],
									[
										'name' => 'space_id',
										'type' => 'integer',
										'location' => 'JSON',
										'required' => true,
										'description' => __('FluentCommunity space or course ID', 'wp-sdtrk')
									],
								]
							),
							$this->create_endpoint_desc(
								"mapping/{product_id}?apikey={$admin_param}",
								'DELETE',
								__('Removes all mappings for a specific product ID. Also removes access for users who only had access through this product.', 'wp-sdtrk'),
								[
									[
										'name' => 'product_id',
										'type' => 'integer',
										'location' => 'URL',
										'required' => true,
										'description' => __('Internal product ID to remove mappings for', 'wp-sdtrk')
									],
									[
										'name' => 'apikey',
										'type' => 'string',
										'location' => 'URL',
										'required' => true,
										'description' => __('Admin API key for authentication', 'wp-sdtrk')
									],
								]
							),
							$this->create_endpoint_desc(
								"override?apikey={$admin_param}",
								'POST',
								__('Creates an admin override to manually grant or deny access. Requires user_id, product_id, override_type (allow/deny), and optional valid_until timestamp.', 'wp-sdtrk'),
								[
									[
										'name' => 'apikey',
										'type' => 'string',
										'location' => 'URL',
										'required' => true,
										'description' => __('Admin API key for authentication', 'wp-sdtrk')
									],
									[
										'name' => 'user_id',
										'type' => 'integer',
										'location' => 'JSON',
										'required' => true,
										'description' => __('WordPress user ID', 'wp-sdtrk')
									],
									[
										'name' => 'product_id',
										'type' => 'integer',
										'location' => 'JSON',
										'required' => true,
										'description' => __('Internal product ID', 'wp-sdtrk')
									],
									[
										'name' => 'override_type',
										'type' => 'string',
										'location' => 'JSON',
										'required' => true,
										'description' => __('Type of override: "allow" or "deny"', 'wp-sdtrk')
									],
									[
										'name' => 'valid_until',
										'type' => 'string',
										'location' => 'JSON',
										'required' => false,
										'description' => __('Expiration date/time (ISO format, optional)', 'wp-sdtrk')
									],
									[
										'name' => 'comment',
										'type' => 'string',
										'location' => 'JSON',
										'required' => false,
										'description' => __('Optional comment for the override', 'wp-sdtrk')
									],
								]
							),
							$this->create_endpoint_desc(
								"override?user_id={user_id}&product_id={product_id}&apikey={$admin_param}",
								'DELETE',
								__('Removes all admin overrides for a specific user-product combination. Restores normal access evaluation.', 'wp-sdtrk'),
								[
									[
										'name' => 'user_id',
										'type' => 'integer',
										'location' => 'URL',
										'required' => true,
										'description' => __('WordPress user ID', 'wp-sdtrk')
									],
									[
										'name' => 'product_id',
										'type' => 'integer',
										'location' => 'URL',
										'required' => true,
										'description' => __('Internal product ID', 'wp-sdtrk')
									],
									[
										'name' => 'apikey',
										'type' => 'string',
										'location' => 'URL',
										'required' => true,
										'description' => __('Admin API key for authentication', 'wp-sdtrk')
									],
								]
							),
						])
					),
				],
			],
		]);
		Redux::set_section('wp_sdtrk_options', [
			'title'  => __('Appearance', 'wp-sdtrk'),
			'id'     => 'appearance_section',
			'desc'   => __('Defines the appearance of the payments-overview page', 'wp-sdtrk'),
			'icon'   => 'el el-picture',
			'fields' => [
				[
					'id'       => 'orders_background_image',
					'type'     => 'media',
					'url'      => true,
					'title'    => __('Background Image', 'wp-sdtrk'),
					'subtitle' => __('Is shown on the payments-overview page', 'wp-sdtrk'),
					'desc'     => __('Optional. Supports PNG and JPEG', 'wp-sdtrk'),
				],
				[
					'id'    => 'login_landingpage_url',
					'type'  => 'text',
					'title' => __('Login Landing Page URL', 'wp-sdtrk'),
					'desc'  => __('URL of the page the user lands on after logging into wordpress', 'wp-sdtrk'),
					'default' => home_url('/wp-admin/'),
				],
			],
		]);
		Redux::set_section('wp_sdtrk_options', [
			'title'  => __('Manage Products', 'wp-sdtrk'),
			'id'     => 'product_admin_link',
			'desc'   => __('Manage products, mappings and access rules', 'wp-sdtrk'),
			'fields' => [
				[
					'id'       => 'product_admin_manage_products',
					'type'     => 'raw',
					'content'  => '<a href="' . admin_url('admin.php?page=sdtrk_admin_manage_products') . '" class="button button-primary">' . __('Manage products', 'wp-sdtrk') . '</a>',
				],
				[
					'id'       => 'product_admin_map_products',
					'type'     => 'raw',
					'content'  => '<a href="' . admin_url('admin.php?page=sdtrk_admin_map_products') . '" class="button button-primary">' . __('Manage mappings', 'wp-sdtrk') . '</a>',
				],
				[
					'id'       => 'product_admin_manage_access',
					'type'     => 'raw',
					'content'  => '<a href="' . admin_url('admin.php?page=sdtrk_admin_manage_access') . '" class="button button-primary">' . __('Manage access', 'wp-sdtrk') . '</a>',
				]
			],
		]);
		Redux::set_section('wp_sdtrk_options', [
			'title'  => __('Community-API', 'wp-sdtrk'),
			'id'     => 'community_api_section',
			'icon'   => 'el el-key',
			'desc'   => __('Community-API Settings', 'wp-sdtrk'),
			'fields' => [
				[
					'id'    => 'community_api_enabled',
					'type'  => 'switch',
					'title' => __('Enable Community API', 'wp-sdtrk'),
					'desc'  => __('Enable the Community API for this site', 'wp-sdtrk'),
					'default' => false,
				],
				[
					'id'    => 'community_api_url',
					'type'  => 'text',
					'title' => __('API URL', 'wp-sdtrk'),
					'desc'  => __('Base URL of the Community API server', 'wp-sdtrk'),
					'default' => 'localhost',
				],
				[
					'id'    => 'community_api_port',
					'type'  => 'text',
					'title' => __('API Port', 'wp-sdtrk'),
					'desc'  => __('Port of the Community API server', 'wp-sdtrk'),
					'default' => '8000',
				],
				[
					'id'    => 'community_api_ssl',
					'type'  => 'switch',
					'title' => __('Use SSL', 'wp-sdtrk'),
					'desc'  => __('Enable SSL/HTTPS for API connections', 'wp-sdtrk'),
					'default' => false,
				],
				[
					'id'    => 'community_api_master_token',
					'type'  => 'text',
					'title' => __('Master Token', 'wp-sdtrk'),
					'desc'  => __('Master token for administrative operations', 'wp-sdtrk'),
				],
				[
					'id'    => 'community_api_service_token',
					'type'  => 'text',
					'title' => __('Service Token', 'wp-sdtrk'),
					'desc'  => __('Service token for read-only operations', 'wp-sdtrk'),
				],
				[
					'id'      => 'community_api_test_button',
					'type'    => 'raw',
					'title'   => __('Connection Test', 'wp-sdtrk'),
					'content' => '<button type="button" class="button button-secondary" onclick="testCommunityAPIConnection()">' . __('Test Connection', 'wp-sdtrk') . '</button>
              <div id="community-api-test-result" style="margin-top: 10px;"></div>',
				],
				[
					'id'    => 'community_api_plugin_url_make',
					'type'  => 'text',
					'title' => __('make.com plugin-url', 'wp-sdtrk'),
					'desc'  => __('URL of the make.com plugin', 'wp-sdtrk')
				],
				[
					'id'    => 'community_api_plugin_url_n8n',
					'type'  => 'text',
					'title' => __('n8n plugin-url', 'wp-sdtrk'),
					'desc'  => __('URL of the n8n plugin', 'wp-sdtrk')
				],
				[
					'id'    => 'community_api_help_url',
					'type'  => 'text',
					'title' => __('Community API help page URL', 'wp-sdtrk'),
					'desc'  => __('URL of the Community API help page', 'wp-sdtrk')
				],
			],
		]);
	}

	/**
	 * Register the page for managing products.
	 *
	 * This function registers a new top-level menu page in the WordPress admin area.
	 * The page is accessible for users with the 'manage_options' capability, and is
	 * rendered by the 'render_page_manage_products' method of this class.
	 *
	 * The CSS code added in the 'admin_head' action is used to hide the menu item
	 * from the admin menu, so that the page is only accessible via the link in the
	 * FluentCommunity Extreme settings page.
	 */
	public function register_page_manage_products(): void
	{
		add_action('admin_head', function () {
			echo '<style>#toplevel_page_sdtrk_admin_manage_products { display: none !important; }</style>';
		});
		add_menu_page(
			'Produkte verwalten',           // Page Title
			'Produkte verwalten',           // Menu Title
			'manage_options',               // Capability
			'sdtrk_admin_manage_products',                 // Menu Slug
			[$this, 'render_page_manage_products'], // Callback
			'',                             // Icon
			null                            // Position
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
	public function register_page_map_products(): void
	{
		add_action('admin_head', function () {
			echo '<style>#toplevel_page_sdtrk_admin_map_products { display: none !important; }</style>';
		});
		add_menu_page(
			'Produkte zuweisen',           // Page Title
			'Produkte zuweisen',           // Menu Title
			'manage_options',               // Capability
			'sdtrk_admin_map_products',                 // Menu Slug
			[$this, 'render_page_map_products'], // Callback
			'',                             // Icon
			null                            // Position
		);
	}

	public function register_page_manage_access(): void
	{
		add_action('admin_head', function () {
			echo '<style>#toplevel_page_sdtrk_admin_manage_access { display: none !important; }</style>';
		});
		add_menu_page(
			'Zugänge verwalten',           // Page Title
			'Zugänge verwalten',           // Menu Title
			'manage_options',               // Capability
			'sdtrk_admin_manage_access',                 // Menu Slug
			[$this, 'render_page_manage_access'], // Callback
			'',                             // Icon
			null                            // Position
		);
	}

	// In admin/class-wp-sdtrk-admin.php
	/**
	 * Handle login redirect based on admin settings - only for non-admins
	 *
	 * @param string $redirect_to URL to redirect to
	 * @param string $requested_redirect_to The requested redirect destination URL passed as a parameter
	 * @param WP_User|WP_Error $user WP_User object if login was successful, WP_Error object otherwise
	 * @return string
	 */
	public function handle_login_redirect($redirect_to, $requested_redirect_to, $user)
	{
		// Nur für erfolgreiche Logins
		if (is_wp_error($user)) {
			return $redirect_to;
		}

		// Prüfe ob der Nutzer Admin-Rechte hat
		if (user_can($user, 'manage_options')) {
			// Für Admins: Standard-Verhalten beibehalten
			return $redirect_to;
		}

		// Nur für Nicht-Admins: Prüfe ob eine custom URL konfiguriert ist
		$custom_url = Redux::get_option('wp_sdtrk_options', 'login_landingpage_url');

		if (!empty($custom_url) && $custom_url !== home_url('/wp-admin/')) {
			return $custom_url;
		}

		// Fallback auf Standard-Verhalten
		return $redirect_to;
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

	/**
	 * Renders the page for managing products.
	 *
	 * This function renders the page used for managing products. It is called
	 * when the 'sdtrk_admin_manage_products' page is accessed in the WordPress admin area.
	 *
	 * The page is rendered by including the 'partials/wp-sdtrk-admin-product-ui.php'
	 * file, which contains the HTML code for the page.
	 */
	public function render_page_manage_products(): void
	{
		$view = plugin_dir_path(dirname(__FILE__)) . 'templates/wp-sdtrk-admin-manage-products.php';
		if (file_exists($view)) {
			include $view;
		}
	}

	/**
	 * Renders the page for mapping products.
	 *
	 * This function renders the page used for mapping products. It is called
	 * when the 'sdtrk_admin_map_products' page is accessed in the WordPress admin area.
	 *
	 * The page is rendered by including the 'templates/wp-sdtrk-admin-map-products.php'
	 * file, which contains the HTML code for the page.
	 */

	public function render_page_map_products(): void
	{
		$view = plugin_dir_path(dirname(__FILE__)) . 'templates/wp-sdtrk-admin-map-products.php';
		if (file_exists($view)) {
			include $view;
		}
	}

	public function render_page_manage_access(): void
	{
		$view = plugin_dir_path(dirname(__FILE__)) . 'templates/wp-sdtrk-admin-manage-access.php';
		if (file_exists($view)) {
			include $view;
		}
	}

	/**
	 * Lädt Skripte und Styles für die Community-API Einstellungen.
	 *
	 * @param string $hook_suffix Aktueller Admin-Page-Hook.
	 */
	public function enqueue_community_api_assets(string $hook_suffix): void
	{
		// Nur auf Redux-Einstellungsseiten laden
		if (strpos($hook_suffix, 'sdtrk_settings') === false) {
			return;
		}

		// JS
		wp_enqueue_script(
			$this->wp_sdtrk . '-community-api-js',
			plugin_dir_url(__FILE__) . 'js/wp-sdtrk-admin-community-api.js',
			['jquery'],
			$this->version,
			true
		);

		// Konfig für AJAX im JS
		wp_localize_script(
			$this->wp_sdtrk . '-community-api-js',
			'SDTRK_CommunityAPI',
			[
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce('security_wp-sdtrk'),
				'messages' => [
					'testing' => __('Testing...', 'wp-sdtrk'),
					'connection_failed' => __('Connection failed', 'wp-sdtrk'),
				]
			]
		);
	}

	/**
	 * Lädt Skripte und Styles für die Produkte-Adminseite.
	 *
	 */
	public function enqueue_products_assets(): void
	{
		// JS
		wp_enqueue_script(
			$this->wp_sdtrk . '-products-js',
			plugin_dir_url(__FILE__) . 'js/wp-sdtrk-admin-products.js',
			['jquery', $this->wp_sdtrk],
			$this->version,
			true
		);

		// Konfig für AJAX im JS
		wp_localize_script(
			$this->wp_sdtrk . '-products-js',
			'SDTRK_Products',
			[
				'ajaxUrl'    => admin_url('admin-ajax.php'),
				'nonce'      => wp_create_nonce('sdtrk_products_nonce'),
			]
		);
	}

	/**
	 * Lädt Skripte und Styles für die Mappings-Adminseite.
	 *
	 */
	public function enqueue_mappings_assets(): void
	{
		// JS
		wp_enqueue_script(
			$this->wp_sdtrk . '-mappings-js',
			plugin_dir_url(__FILE__) . 'js/wp-sdtrk-admin-mappings.js',
			['jquery', $this->wp_sdtrk],  // Abhängigkeit von wp-sdtrk-admin.js
			$this->version,
			true
		);

		// Konfig für AJAX im JS
		wp_localize_script(
			$this->wp_sdtrk . '-mappings-js',
			'SDTRK_Mappings',
			[
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce('security_wp-sdtrk'),
				'messages' => [
					'loading' => __('Loading...', 'wp-sdtrk'),
					'error' => __('Error occurred', 'wp-sdtrk'),
				]
			]
		);
	}

	/**
	 * Lädt Skripte und Styles für die Access-Adminseite.
	 *
	 */
	public function enqueue_access_assets(): void
	{
		// JS
		wp_enqueue_script(
			$this->wp_sdtrk . '-access-js',
			plugin_dir_url(__FILE__) . 'js/wp-sdtrk-admin-access.js',
			['jquery', $this->wp_sdtrk],  // Abhängigkeit von wp-sdtrk-admin.js
			$this->version,
			true
		);

		// Konfig für AJAX im JS
		wp_localize_script(
			$this->wp_sdtrk . '-access-js',
			'SDTRK_Access',
			[
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce('security_wp-sdtrk'),
				'messages' => [
					'loading' => __('Loading...', 'wp-sdtrk'),
					'error' => __('Error occurred', 'wp-sdtrk'),
				]
			]
		);
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
