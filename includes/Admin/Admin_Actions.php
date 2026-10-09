<?php
namespace QuixDevs\ProductClock\Admin;

use QuixDevs\ProductClock\Dependencies;
use QuixDevs\ProductClock\Scheduler\Queue;
use QuixDevs\ProductClock\Scheduler\Reconciliation;
use QuixDevs\ProductClock\Scheduler\Transition_Processor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin_Actions {
	private $reconciliation;
	private $processor;
	public function __construct( Reconciliation $reconciliation, Transition_Processor $processor ) {
		$this->reconciliation = $reconciliation;
		$this->processor      = $processor;
	}
	public function authorized( $nonce ) {
		return current_user_can( 'manage_woocommerce' ) && current_user_can( 'manage_options' ) && wp_verify_nonce( $nonce, 'quixdevs_productclock_tool' );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- authorized() verifies the nonce and both capabilities before mutations.
	public function handle() {
		if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || ! $this->authorized( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ) ) ) {
			wp_die( esc_html__( 'You are not authorized to run this ProductClock tool.', 'quixdevs-productclock' ), '', array( 'response' => 403 ) );
		}
		$tool = sanitize_key( wp_unslash( $_POST['tool'] ?? '' ) );
		if ( ! in_array( $tool, array( 'run_due', 'reconcile', 'repair' ), true ) || empty( $_POST['confirm'] ) ) {
			wp_die( esc_html__( 'Choose a tool and confirm the operation.', 'quixdevs-productclock' ), '', array( 'response' => 400 ) );
		}
		if ( ! Dependencies::scheduler() ) {
			wp_die( esc_html__( 'Action Scheduler is unavailable.', 'quixdevs-productclock' ) );
		}
		$count = 0;
		try {
			if ( 'run_due' === $tool ) {
				$actions = as_get_scheduled_actions(
					array(
						'group'        => Queue::GROUP,
						'status'       => 'pending',
						'date'         => time(),
						'date_compare' => '<=',
						'per_page'     => 25,
						'claimed'      => false,
					)
				);
				foreach ( $actions as $action ) {
					$hook = $action->get_hook();
					if ( ! in_array( $hook, array( 'quixdevs_productclock_publish', 'quixdevs_productclock_expire' ), true ) ) {
						continue;
					}
					$args = array_values( $action->get_args() );
					$this->processor->process( (int) $args[0], $args[1], 'quixdevs_productclock_publish' === $hook ? 'publish' : 'expire' );
					++$count;
				}
			} else {
				$this->reconciliation->start();
			}
			/* translators: %d: number of inspected due actions. */
			$message = 'run_due' === $tool ? sprintf( __( 'Inspected %d due actions. Completed transitions are safe to retry; remaining work stays in the queue.', 'quixdevs-productclock' ), $count ) : __( 'Recovery queued. Batches will repair missing actions without changing suspended or completed schedules.', 'quixdevs-productclock' );
		} catch ( \Throwable $e ) {
			$message = __( 'The tool encountered a failure. Review WooCommerce logs and scheduler diagnostics.', 'quixdevs-productclock' );
		}
		set_transient( 'quixdevs_productclock_notice_' . get_current_user_id(), $message, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=quixdevs-productclock&tab=tools' ) );
		exit;
	}
}
