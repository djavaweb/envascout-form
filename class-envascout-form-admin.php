<?php
/**
 * Register menus WordPress Admin things.
 *
 * @package Envascout_Form_Admin
 * @since 1.0.0
 */

/**
 * Envascout Form Admin
 */
class Envascout_Form_Admin {
	/**
	 * Check if has been initialized or not yet.
	 *
	 * @var boolean
	 */
	private static $initiated = false;

	/**
	 * Initiaalization.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::$initiated ) {
			self::setup_all();
		}
	}

	/**
	 * Initializes All WordPress hooks.
	 *
	 * @static
	 */
	private static function setup_all() {
		self::$initiated = true;

		// Add caldera forms types: envato purchase item list.
		add_action( 'admin_menu' , array( 'Envascout_Form_Admin', 'admin_menu' ) );

		// Add stuff on admin init, e.g register settings.
		add_action( 'admin_init', array( 'Envascout_Form_Admin', 'admin_init' ) );

		// Register admin styles and javasripts.
		add_action( 'admin_enqueue_scripts', array( 'Envascout_Form_Admin', 'admin_enqueue_scripts' ) );
	}

	/**
	 * Register admin menus.
	 *
	 * @return void
	 */
	public static function admin_menu() {
		add_menu_page(
			__( 'Envanto, Helpscout and Caldera Forms Integration','envascout-form' ),
			__( 'Envascout Form', 'envascout-form' ),
			'manage_options',
			'envascout-form-setting',
			array( 'Envascout_Form_Admin', 'settings_view' ),
			'dashicons-image-filter'
		);
	}

	/**
	 * Admin init stuff, register settings, etc.
	 *
	 * @return void
	 */
	public static function admin_init() {
		register_setting( 'envascout-form', 'envascout_options' );
	}

	/**
	 * Register styles and javascripts in envascout admin menu.
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts() {
		$screen = get_current_screen();

		if ( 'toplevel_page_envascout-form-setting' === $screen->id ) {
			wp_enqueue_style( 'envascout-form', ENVASCOUT_FORM_PLUGIN_URL . 'assets/css/admin-styles.css', array(), null );
			wp_enqueue_script( 'vue', ENVASCOUT_FORM_PLUGIN_URL . 'assets/js/vue.js', array(), null, true );
			wp_enqueue_script( 'envascout-form', ENVASCOUT_FORM_PLUGIN_URL . 'assets/js/admin-scripts.js', array( 'vue' ), null, true );
		}

	}

	/**
	 * Settings renderer.
	 *
	 * @return void
	 */
	public static function settings_view() {
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['envascout_options'] ) ) { // WPCS: CSRF ok.
			update_option( 'envascout_options', wp_json_encode( $_POST['envascout_options'] ) ); // WPCS: CSRF ok.
		}

		$options = Envascout_Form::get_options();

		// Get mailbox list.
		$helpscout = new WP_Helpscout_Api( $options['helpscout_api_key'] );

		// Clear transient when refresh mailbox.
		if ( isset( $_GET['refresh_mailbox'] ) ) {
			$helpscout->clear_session();
		}

		$mailbox = $helpscout->get_mailbox_list();
		$mailboxes = array();

		if ( is_array( $mailbox ) && isset( $mailbox['items'] ) ) {
			$mailboxes  = $mailbox['items'];
		}

		Envascout_Form::view('settings', array(
			'options' => $options,
			'mailboxes' => $mailboxes,
			'template_editor_settings' => array(
				'tinymce' => false,
				'textarea_rows' => 10,
			),
		) );
	}
}
