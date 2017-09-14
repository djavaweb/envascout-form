<?php
/**
 * Plugin Name: EnvaScout Form
 * Plugin URI: http://github.com/oknoorap/envascout-form
 * Description: Integration between Envato API, Helpscout, and Caldera Forms.
 * Version: 1.0.0
 * Author: oknoorap
 * Author URI: http://github.com/oknoorap
 * License: GPLv2 or later
 * Text Domain: envascout-form
 *
 * @package envacout-form
 * @since 1.0.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'ENVASCOUT_FORM_VER', '1.0.0' );
define( 'ENVASCOUT_FORM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ENVASCOUT_FORM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_GITHUB_FORCE_UPDATE', true );

register_activation_hook( __FILE__, array( 'Envascout_Form', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'Envascout_Form', 'plugin_deactivation' ) );

if ( ! function_exists( 'is_plugin_active' ) ) {
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}

require_once( ENVASCOUT_FORM_PLUGIN_DIR . 'class-wp-github-updater.php' );
require_once( ENVASCOUT_FORM_PLUGIN_DIR . 'class-wp-envato-api.php' );
require_once( ENVASCOUT_FORM_PLUGIN_DIR . 'class-wp-helpscout-api.php' );
require_once( ENVASCOUT_FORM_PLUGIN_DIR . 'class-envascout-form.php' );
add_action( 'init', array( 'Envascout_Form', 'init' ), 0 );

if ( is_admin() && is_plugin_active( 'caldera-forms/caldera-core.php' ) ) {
	require_once( ENVASCOUT_FORM_PLUGIN_DIR . 'class-envascout-form-admin.php' );
	add_action( 'init', array( 'Envascout_Form_Admin', 'init' ) );
}
