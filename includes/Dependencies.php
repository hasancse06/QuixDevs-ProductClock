<?php
namespace QuixDevs\ProductClock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dependencies {
	public static function woocommerce() {
		return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '10.0', '>=' ) && function_exists( 'wc_get_product' );
	}
	public static function scheduler() {
		return function_exists( 'as_has_scheduled_action' ) && class_exists( 'ActionScheduler' ) && \ActionScheduler::is_initialized();
	}
	public static function notice() {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'ProductClock needs WooCommerce 10.0 or later and its initialized Action Scheduler. Scheduling is unavailable until these dependencies are ready.', 'quixdevs-productclock' ) . '</p></div>';
		}
	}
}
