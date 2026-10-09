<?php
namespace QuixDevs\ProductClock\Scheduler;

use QuixDevs\ProductClock\Data\Schedule_Repository;
use QuixDevs\ProductClock\Support\Settings;
use QuixDevs\ProductClock\Support\Lock;
use QuixDevs\ProductClock\Support\Logger;
use QuixDevs\ProductClock\Support\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Transition_Processor {
	private $repository;
	private $manager;
	private $queue;
	private $settings;
	private $lock;
	private $logger;
	private $validator;
	private $changing = array();
	public function __construct( Schedule_Repository $repository, Schedule_Manager $manager, Queue $queue, Settings $settings, Lock $lock, Logger $logger, Validator $validator ) {
		$this->repository = $repository;
		$this->manager    = $manager;
		$this->queue      = $queue;
		$this->settings   = $settings;
		$this->lock       = $lock;
		$this->logger     = $logger;
		$this->validator  = $validator;
	}
	public function publish( $id, $revision ) {
		$this->process( (int) $id, $revision, 'publish' );
	}
	public function expire( $id, $revision ) {
		$this->process( (int) $id, $revision, 'expire' );
	}
	public function process( $id, $revision, $action ) {
		$this->lock->run(
			$id,
			function () use ( $id, $revision, $action ) {
				$s = $this->repository->get( $id );
				if ( ! $s || $s['revision'] !== $revision || ! $s['enabled'] || in_array( $s['state'], array( 'suspended', 'error' ), true ) || ! get_option( 'quixdevs_productclock_active', false ) || ! $this->settings->get( 'enabled' ) ) {
					return;
				}
				if ( ! in_array( $action, array( 'publish', 'expire' ), true ) || ! $this->settings->get( 'publish' === $action ? 'publishing' : 'expiration' ) ) {
					return;
				}
				if ( ! $s[ $action . '_at' ] || $s[ $action . '_done' ] || $s[ $action . '_at' ] > time() ) {
					return;
				}
				$product = wc_get_product( $id );
				try {
					$this->validator->product( $product );
				} catch ( \InvalidArgumentException $e ) {
								$this->manager->suspend( $id, 'unsupported_product' );
								return;
				}
					$status = $product->get_status();
				if ( ! in_array( $status, array( 'draft', 'publish' ), true ) ) {
					$this->manager->suspend( $id, 'unsupported_status' );
					return;
				}
					// Expiration wins even when its task is claimed after the publication task.
				if ( 'publish' === $action && $s['expire_at'] && $s['expire_at'] <= time() ) {
					$s['publish_done'] = true;
					$s['state']        = $this->manager->state( $s );
					$this->repository->put( $id, $s );
					$this->queue->sync( $id, $s );
					return;
				}
					$target     = 'publish' === $action ? 'publish' : 'draft';
					$source     = 'publish' === $action ? 'draft' : 'publish';
					$recovering = $s['intent'] === $action && $status === $target;
				if ( ! $recovering && $status !== $s['expected_status'] ) {
					$this->manager->suspend( $id );
					return;
				}
					// Already published at enrollment or never published before a missed expiry: consume without mutation.
					$changed = $status === $source;
				try {
					if ( $changed ) {
						$s['intent'] = $action;
						$this->repository->put( $id, $s );
						$this->changing[ $id ] = true;
						try {
							$product->set_status( $target );
							$product->save();
						} finally {
											unset( $this->changing[ $id ] );
						}
						if ( get_post_status( $id ) !== $target ) {
							throw new \RuntimeException( 'Status write was not accepted.' );
						}
					}
					$s[ $action . '_done' ] = true;
					if ( 'expire' === $action ) {
						$s['publish_done'] = true;
					}
					$s['intent']          = '';
					$s['expected_status'] = $target;
					$s['last_action']     = $action;
					$s['last_execution']  = time();
					$s['last_error']      = '';
					$s['state']           = $this->manager->state( $s );
					$this->repository->put( $id, $s );
					$this->queue->sync( $id, $s );
					$this->logger->record( $id, $changed || $recovering ? ( 'publish' === $action ? 'product_published' : 'product_expired' ) : 'task_skipped', $s[ $action . '_at' ], $recovering ? 'recovered' : ( $changed ? 'success' : 'already_at_target' ) );
				} catch ( \Throwable $e ) {
					$s['state']      = 'error';
					$s['last_error'] = 'transition_failed';
					$this->repository->put( $id, $s );
					$this->queue->cancel( $id, $revision );
					$this->logger->record( $id, 'task_failed', $s[ $action . '_at' ], 'failure' );
					do_action( 'quixdevs_productclock_schedule_failed', $id, $revision, $action, 'transition_failed' );
					throw $e;
				}
					// Completion is committed before extensions run. External hook side effects must be idempotent.
				if ( $changed && ! $recovering ) {
					do_action( 'quixdevs_productclock_after_' . $action, $id, $revision, $s[ $action . '_at' ], $s['last_execution'] );
				}
			}
		);
	}
	public function status_changed( $new_status, $old, $post ) {
		if ( $new_status !== $old && 'product' === $post->post_type && empty( $this->changing[ $post->ID ] ) ) {
			try {
				$this->manager->suspend( $post->ID );
			} catch ( \Throwable $e ) {
				$this->logger->record( $post->ID, 'task_failed', 0, 'failure' );
			}
		}
	}
	public function deleting( $id ) {
		if ( 'product' !== get_post_type( $id ) ) {
			return;
		}
		$this->manager->suspend( $id, 'product_deleted' );
		$s = $this->repository->get( $id );
		if ( $s ) {
			$this->queue->cancel( $id, $s['revision'] );
		}
	}
}
