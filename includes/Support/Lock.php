<?php
namespace QuixDevs\ProductClock\Support;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Connection-scoped database advisory mutex; released automatically on disconnect. */
final class Lock {
	private $held = array();
	private function name( $id ) {
		global $wpdb;
		return 'qpc_' . md5( $wpdb->prefix . ':' . $id );
	}
	public function acquire( $id ) {
		global $wpdb;
		$name = $this->name( $id );
		if ( isset( $this->held[ $name ] ) ) {
			return false;
		}
		// SQL is necessary: transients cannot provide crash-safe mutual exclusion.
		$acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( '1' !== (string) $acquired ) {
			throw new RuntimeException( 'ProductClock lock unavailable. Retry shortly.' );
		}
		$this->held[ $name ] = true;
		return true;
	}
	public function release( $id ) {
		global $wpdb;
		$name = $this->name( $id );
		if ( ! isset( $this->held[ $name ] ) ) {
			return;
		}
		unset( $this->held[ $name ] );
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	public function run( $id, callable $callback ) {
		$acquired = $this->acquire( $id );
		try {
			return $callback();
		} finally {
			if ( $acquired ) {
				$this->release( $id );
			}
		}
	}
}
