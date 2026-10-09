<?php
namespace QuixDevs\ProductClock\Scheduler;

use QuixDevs\ProductClock\Data\Schedule_Repository;
use QuixDevs\ProductClock\Support\Date_Time;
use QuixDevs\ProductClock\Support\Validator;
use QuixDevs\ProductClock\Support\Settings;
use QuixDevs\ProductClock\Support\Lock;
use QuixDevs\ProductClock\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schedule_Manager {
	private $repository;
	private $queue;
	private $dates;
	private $validator;
	private $settings;
	private $lock;
	private $logger;
	public function __construct( Schedule_Repository $repository, Queue $queue, Date_Time $dates, Validator $validator, Settings $settings, Lock $lock, Logger $logger ) {
		$this->repository = $repository;
		$this->queue      = $queue;
		$this->dates      = $dates;
		$this->validator  = $validator;
		$this->settings   = $settings;
		$this->lock       = $lock;
		$this->logger     = $logger;
	}
	public function save( $id, array $input ) {
		return $this->lock->run(
			$id,
			function () use ( $id, $input ) {
				$product = wc_get_product( $id );
				$this->validator->product( $product );
				$old     = $this->repository->get( $id );
				$enabled = ! empty( $input['enabled'] );
				if ( ! $old && ! $enabled ) {
					return array();
				}
				if ( ! $enabled && ! $old['enabled'] ) {
					return $old;
				}
				if ( ! $enabled ) {
					$previous_revision = $old['revision'];
					$old['enabled']    = false;
					$old['state']      = 'disabled';
					$old['revision']   = wp_generate_uuid4();
					$this->repository->put( $id, $old );
					$this->queue->cancel( $id, $previous_revision );
					$this->logger->record( $id, 'schedule_disabled' );
					return $old;
				}
				$zone    = $old['timezone'] ?? $this->settings->timezone();
				$mode    = sanitize_key( $input['mode'] ?? 'both' );
				$publish = 'expire' === $mode ? 0 : $this->dates->parse( $input['publish'] ?? '', $zone );
				$expire  = 'publish' === $mode ? 0 : $this->dates->parse( $input['expire'] ?? '', $zone );
				$this->validator->dates( $mode, $publish, $expire );
				if ( 'expire' === $mode && 'publish' !== $product->get_status() && ! ( ( $old['expire_at'] ?? null ) === $expire && ! empty( $old['expire_done'] ) ) ) {
					throw new \InvalidArgumentException( esc_html__( 'Expiration-only schedules require a published product.', 'quixdevs-productclock' ) );
				}
				$same = $old && $old['publish_at'] === $publish && $old['expire_at'] === $expire && $old['mode'] === $mode;
				if ( $same && $old['enabled'] && empty( $input['resume'] ) ) {
					$this->queue->sync( $id, $old );
					return $old;
				}
				if ( ! in_array( $product->get_status(), array( 'draft', 'publish' ), true ) ) {
					throw new \InvalidArgumentException( esc_html__( 'Scheduling can only be armed on draft or published products.', 'quixdevs-productclock' ) );
				}
				if ( $publish > time() && 'publish' === $product->get_status() && ! ( ( $old['publish_at'] ?? null ) === $publish && ! empty( $old['publish_done'] ) ) ) {
					throw new \InvalidArgumentException( esc_html__( 'To schedule a future publication, first move this product to draft yourself. ProductClock never drafts it immediately.', 'quixdevs-productclock' ) );
				}
				$s = array(
					'schema'          => 1,
					'enabled'         => true,
					'mode'            => $mode,
					'publish_at'      => $publish,
					'expire_at'       => $expire,
					'timezone'        => $zone,
					'revision'        => wp_generate_uuid4(),
					'state'           => 'pending_publication',
					'expected_status' => $product->get_status(),
					'publish_done'    => $same ? ( $old['publish_done'] ?? false ) : ( ( $old['publish_at'] ?? null ) === $publish && ! empty( $old['publish_done'] ) ),
					'expire_done'     => $same ? ( $old['expire_done'] ?? false ) : ( ( $old['expire_at'] ?? null ) === $expire && ! empty( $old['expire_done'] ) ),
					'intent'          => '',
					'last_action'     => $old['last_action'] ?? '',
					'last_execution'  => $old['last_execution'] ?? 0,
					'last_error'      => '',
				);
				if ( $expire && $expire <= time() ) {
					$s['publish_done'] = true;
				}
				$s['state'] = $this->state( $s );
				// Commit the new revision before cancellation, so a running stale event becomes harmless.
				$this->repository->put( $id, $s );
				$this->queue->cancel( $id, $old['revision'] ?? '' );
				$this->queue->sync( $id, $s );
				$this->logger->record( $id, $old ? 'schedule_changed' : 'schedule_created' );
				if ( $old ) {
					do_action( 'quixdevs_productclock_schedule_updated', $id, $s['revision'] ); } else {
					do_action( 'quixdevs_productclock_schedule_created', $id, $s['revision'] ); }
					return $s;
			}
		);
	}
	public function state( array $s ) {
		if ( ! $s['enabled'] ) {
			return 'disabled';
		}
		if ( $s['expire_done'] ) {
			return 'expired';
		}
		if ( $s['publish_at'] && ! $s['publish_done'] ) {
			return 'pending_publication';
		}
		if ( $s['expire_at'] && ! $s['expire_done'] ) {
			return 'pending_expiration';
		}
		return 'completed';
	}
	public function suspend( $id, $reason = 'manual_override' ) {
		$this->lock->run(
			$id,
			function () use ( $id, $reason ) {
				$s = $this->repository->get( $id );
				if ( ! $s || ! $s['enabled'] || 'suspended' === $s['state'] ) {
					return;
				}
				$revision        = $s['revision'];
				$s['revision']   = wp_generate_uuid4();
				$s['state']      = 'suspended';
				$s['last_error'] = sanitize_key( $reason );
				$s['intent']     = '';
				$this->repository->put( $id, $s );
				$this->queue->cancel( $id, $revision );
				$this->logger->record( $id, 'task_skipped', 0, $reason );
			}
		);
	}
}
