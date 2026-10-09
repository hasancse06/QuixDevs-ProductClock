<?php
namespace QuixDevs\ProductClock\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Logger {
	private $settings;
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}
	public function record( $id, $event, $scheduled = 0, $result = 'success' ) {
		if ( ! $this->settings->get( 'logging' ) ) {
			return;
		}
		$entry     = array(
			'time'       => time(),
			'product_id' => (int) $id,
			'action'     => sanitize_key( $event ),
			'scheduled'  => (int) $scheduled,
			'result'     => sanitize_key( $result ),
		);
		$history   = $this->prune( $id );
		$history[] = $entry;
		update_post_meta( $id, '_quixdevs_productclock_activity', array_slice( $history, -20 ) );
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( 'failure' === $result ? 'error' : 'info', 'ProductClock ' . sanitize_key( $event ), array_merge( array( 'source' => 'quixdevs-productclock' ), $entry ) );
		}
	}
	public function prune( $id ) {
		$history  = get_post_meta( $id, '_quixdevs_productclock_activity', true );
		$history  = is_array( $history ) ? $history : array();
		$cutoff   = time() - DAY_IN_SECONDS * 30;
		$filtered = array_values(
			array_filter(
				$history,
				static function ( $entry ) use ( $cutoff ) {
					return $entry['time'] >= $cutoff;
				}
			)
		);
		if ( $history !== $filtered ) {
			update_post_meta( $id, '_quixdevs_productclock_activity', $filtered );
		}
		return $filtered;
	}
}
