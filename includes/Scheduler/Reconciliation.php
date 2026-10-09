<?php
namespace QuixDevs\ProductClock\Scheduler;

use QuixDevs\ProductClock\Data\Schedule_Repository;
use QuixDevs\ProductClock\Support\Lock;
use QuixDevs\ProductClock\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reconciliation {
	private $repository;
	private $queue;
	private $lock;
	private $logger;
	public function __construct( Schedule_Repository $repository, Queue $queue, Lock $lock, Logger $logger ) {
		$this->repository = $repository;
		$this->queue      = $queue;
		$this->lock       = $lock;
		$this->logger     = $logger;
	}
	public function start() {
		$this->queue->ensure_reconciliation();
		$this->lock->run(
			'reconcile',
			function () {
				if ( ! as_has_scheduled_action( 'quixdevs_productclock_reconcile_batch', null, Queue::GROUP ) ) {
					as_schedule_single_action( time() + 1, 'quixdevs_productclock_reconcile_batch', array( 0 ), Queue::GROUP, false );
				}
			}
		);
	}
	public function batch( $after = 0 ) {
		$ids = $this->repository->batch( (int) $after );
		foreach ( $ids as $id ) {
			try {
				$this->lock->run(
					$id,
					function () use ( $id ) {
						$s = $this->repository->get( $id );
						if ( $s ) {
							$this->repository->put( $id, $s );
							$this->queue->sync( $id, $s, true );
						}
						$this->logger->prune( $id );
					}
				);
			} catch ( \Throwable $e ) {
				$this->logger->record( $id, 'reconciliation_failed', 0, 'failure' );
			}
		}
		update_option( 'quixdevs_productclock_last_reconciliation', time(), false );
		if ( 50 === count( $ids ) ) {
			$args = array( end( $ids ) );
			$this->lock->run(
				'reconcile',
				function () use ( $args ) {
					if ( ! as_has_scheduled_action( 'quixdevs_productclock_reconcile_batch', $args, Queue::GROUP ) ) {
						as_schedule_single_action( time() + 5, 'quixdevs_productclock_reconcile_batch', $args, Queue::GROUP, false ); }
				}
			);
		}
		$this->clean_stale();
	}
	private function clean_stale() {
		$cursor  = (int) get_option( 'quixdevs_productclock_queue_cursor', 0 );
		$actions = as_get_scheduled_actions(
			array(
				'group'    => Queue::GROUP,
				'status'   => 'pending',
				'per_page' => 50,
				'offset'   => $cursor,
				'orderby'  => 'date',
				'order'    => 'ASC',
			)
		);
		$removed = 0;
		foreach ( $actions as $task ) {
			if ( ! in_array( $task->get_hook(), array( 'quixdevs_productclock_publish', 'quixdevs_productclock_expire' ), true ) ) {
				continue;
			}
			$args = array_values( $task->get_args() );
			$s    = $this->repository->get( (int) $args[0] );
			if ( ! $s || $s['revision'] !== $args[1] || ! $s['enabled'] || in_array( $s['state'], array( 'suspended', 'error' ), true ) ) {
				as_unschedule_all_actions( $task->get_hook(), $task->get_args(), Queue::GROUP );
				++$removed;
			}
		}
		update_option( 'quixdevs_productclock_queue_cursor', 50 === count( $actions ) ? max( 0, $cursor + 50 - $removed ) : 0, false );
	}
}
