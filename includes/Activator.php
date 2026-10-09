<?php
namespace QuixDevs\ProductClock;

use QuixDevs\ProductClock\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {
	public static function activate( $network_wide = false ) {
		if ( $network_wide ) {
			wp_die( esc_html__( 'Activate ProductClock separately on each store; network activation is not supported.', 'quixdevs-productclock' ) );
		}
		if ( ! Dependencies::woocommerce() ) {
			wp_die( esc_html__( 'Install and activate WooCommerce 10.0 or later first.', 'quixdevs-productclock' ) );
		}
		add_option( Settings::OPTION, ( new Settings() )->defaults(), '', false );
		update_option( 'quixdevs_productclock_active', true, false );
		update_option( 'quixdevs_productclock_recover', true, false );
	}
}
