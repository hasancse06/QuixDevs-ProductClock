<?php
namespace QuixDevs\ProductClock\Scheduler;

use QuixDevs\ProductClock\Support\Lock;
use QuixDevs\ProductClock\Data\Schedule_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Serialize supported external status writes with scheduler writes, before WordPress issues SQL. */
final class Status_Guard {
	private $lock;
	private $acquired = array();
	public function __construct( Lock $lock ) {
		$this->lock = $lock;
	}
	public function before_write( $data, $postarr ) {
		$id = (int) ( $postarr['ID'] ?? 0 );
		if ( ! $id || 'product' !== $data['post_type'] || ! get_post_meta( $id, Schedule_Repository::KEY, true ) ) {
			return $data;
		}
		try {
			if ( $this->lock->acquire( $id ) ) {
				$this->acquired[ $id ] = true;
			}
		} catch ( \RuntimeException $e ) {
			wp_die( esc_html__( 'ProductClock is processing this product. Wait a moment and retry saving.', 'quixdevs-productclock' ), '', array( 'response' => 409 ) );
		}
		return $data;
	}
	public function after_write( $id ) {
		if ( isset( $this->acquired[ $id ] ) ) {
			unset( $this->acquired[ $id ] );
			$this->lock->release( $id );
		}
	}
	public function shutdown() {
		foreach ( array_keys( $this->acquired ) as $id ) {
			$this->after_write( $id );
		}
	}
}
