<?php
/**
 * Plugin Name: Helpful (mySociety fork)
 * Description: A fork of Pixelbart’s plugin for adding fancy feedback forms to posts. Pinned to version 4.3.2, because the 4.4.0 update broke our site.
 * Version: 4.3.2
 * Author: mySociety
 * Author URI: https://github.com/mysociety/helpful
 * Text Domain: helpful
 * License: MIT License
 * License URI: https://opensource.org/licenses/MIT
 */

/* Prevent direct access */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load helpful.
 */
function helpful_load_plugin() {
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin = get_plugin_data( __FILE__ );

	define( 'HELPFUL_FILE', __FILE__ );
	define( 'HELPFUL_PATH', plugin_dir_path( HELPFUL_FILE ) );
	define( 'HELPFUL_VERSION', $plugin['Version'] );
	define( 'HELPFUL_PHP_MIN', '5.6.20' );

	/* Include config */
	require_once HELPFUL_PATH . 'config.php';

	/* Set custom timezone if set in the options */
	if ( get_option( 'helpful_timezone' ) && '' !== get_option( 'helpful_timezone' ) ) {
		$timezone = get_option( 'helpful_timezone' );
		date_default_timezone_set( $timezone );
	}

	include HELPFUL_PATH . 'core/autoload.php';
}

/**
 * Fires Helpful.
 */
add_action( 'wp_loaded', 'helpful_load_plugin' );