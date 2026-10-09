<?php
/**
 * Plugin Name: QuixDevs ProductClock – Product Scheduler for WooCommerce
 * Description: Automatically publish and expire WooCommerce products at scheduled dates and times.
 * Version: 1.0.0
 * Author: QuixDevs
 * Author URI: https://quixdevs.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: quixdevs-productclock
 * Domain Path: /languages
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 10.0
 *
 * Copyright (c) 2026 QuixDevs.
 *
 * @package QuixDevsProductClock
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QUIXDEVS_PRODUCTCLOCK_FILE', __FILE__ );
define( 'QUIXDEVS_PRODUCTCLOCK_VERSION', '1.0.0' );

// Composer is optional on customer installations.
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'QuixDevs\\ProductClock\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		if ( ! preg_match( '/^[A-Za-z_\\\\]+$/', $relative ) ) {
			return;
		}
		$file = __DIR__ . '/includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'QuixDevs\\ProductClock\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'QuixDevs\\ProductClock\\Deactivator', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'QuixDevs\\ProductClock\\Plugin', 'boot' ), 20 );
