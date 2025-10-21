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

		wp_enqueue_script($this->wp_sdtrk, plugin_dir_url(__FILE__) . 'js/wp-sdtrk-public.js', array('jquery'), $this->version, false);
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
}
