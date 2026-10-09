<?php
namespace QuixDevs\ProductClock\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {
	const OPTION = 'quixdevs_productclock_settings';
	public function defaults() {
		return array(
			'enabled'     => true,
			'publishing'  => true,
			'expiration'  => true,
			'logging'     => true,
			'remove_data' => false,
			'timezone'    => 'site',
		);
	}
	public function all() {
		return wp_parse_args( get_option( self::OPTION, array() ), $this->defaults() );
	}
	public function get( $key ) {
		return $this->all()[ $key ];
	}
	public function sanitize( $input ) {
		$output = $this->defaults();
		foreach ( array( 'enabled', 'publishing', 'expiration', 'logging', 'remove_data' ) as $key ) {
			$output[ $key ] = ! empty( $input[ $key ] );
		}
		$output['timezone'] = in_array( $input['timezone'] ?? '', array( 'site', 'UTC' ), true ) ? $input['timezone'] : 'site';
		return $output;
	}
	public function timezone() {
		return 'UTC' === $this->get( 'timezone' ) ? 'UTC' : wp_timezone_string();
	}
}
