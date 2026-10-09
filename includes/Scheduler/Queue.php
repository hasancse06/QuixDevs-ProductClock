<?php
namespace QuixDevs\ProductClock\Scheduler;

use QuixDevs\ProductClock\Dependencies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Queue {
	const GROUP = 'quixdevs-productclock';
	private $logger;
	public function __construct( ?\QuixDevs\ProductClock\Support\Logger $logger = null ) {
		$this->logger = $logger;
	}
	public function hook( $action ) {
		return 'quixdevs_productclock_' . $action;
	}
	public function cancel( $id, $revision ) {
		if ( ! Dependencies::scheduler() || ! $revision ) {
			return;
		}
		foreach ( array( 'publish', 'expire' ) as $action ) {
			as_unschedule_all_actions( $this->hook( $action ), array( $id, $revision ), self::GROUP );
		}
	}
	public function sync( $id, array $s, $recover = false ) {
		if ( ! Dependencies::scheduler() ) {
			throw new \RuntimeException( 'Action Scheduler unavailable.' );
		}
		if ( ! $s['enabled'] || in_array( $s['state'], array( 'suspended', 'error' ), true ) ) {
			$this->cancel( $id, $s['revision'] );
			return;
		}
		foreach ( array( 'publish', 'expire' ) as $action ) {
			$args = array( $id, $s['revision'] );
			if ( ! $s[ $action . '_at' ] || $s[ $action . '_done' ] ) {
				as_unschedule_all_actions( $this->hook( $action ), $args, self::GROUP );
				continue;
			}
			// The AS unique flag is hook/group-wide in the DB store. Our product mutex plus exact args provide per-product uniqueness.
			if ( ! as_has_scheduled_action( $this->hook( $action ), $args, self::GROUP ) ) {
				if ( ! as_schedule_single_action( max( time() + 1, $s[ $action . '_at' ] ), $this->hook( $action ), $args, self::GROUP, false ) ) {
					throw new \RuntimeException( 'Action could not be queued.' );
				}
				if ( $this->logger ) {
					$this->logger->record( $id, $recover ? 'reconciliation_recovered' : $action . '_queued', $s[ $action . '_at' ] );
				}
			}
		}
	}
	public function ensure_reconciliation() {
		if ( Dependencies::scheduler() && ! as_has_scheduled_action( $this->hook( 'reconcile' ), array(), self::GROUP ) ) {
			as_schedule_recurring_action( time() + 30, HOUR_IN_SECONDS, $this->hook( 'reconcile' ), array(), self::GROUP, true );
		}
	}
	public function stop() {
		if ( ! Dependencies::scheduler() ) {
			return;
		}
		foreach ( array( 'publish', 'expire', 'reconcile', 'reconcile_batch' ) as $hook ) {
			as_unschedule_all_actions( $this->hook( $hook ), null, self::GROUP );
		}
	}
}
