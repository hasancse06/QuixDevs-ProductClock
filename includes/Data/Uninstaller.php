<?php
namespace QuixDevs\ProductClock\Data;

use QuixDevs\ProductClock\Support\Settings;
use QuixDevs\ProductClock\Scheduler\Queue;
use QuixDevs\ProductClock\Dependencies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Uninstaller {
	public function run() {
		( new Queue() )->stop();
		if ( ! ( new Settings() )->get( 'remove_data' ) ) {
			return;
		}
		global $wpdb;
		// All plugin-owned keys, including derived indexes and bounded activity records.
		$keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_quixdevs_productclock_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $keys as $key ) {
			delete_post_meta_by_key( $key );
		}
		foreach ( array( Settings::OPTION, 'quixdevs_productclock_active', 'quixdevs_productclock_last_reconciliation', 'quixdevs_productclock_queue_cursor', 'quixdevs_productclock_recover' ) as $option ) {
			delete_option( $option );
		}
		// Remove only ProductClock's expiring administrator messages.
		$messages = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_quixdevs_productclock_notice_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $messages as $message ) {
			delete_transient( substr( $message, strlen( '_transient_' ) ) ); }
		if ( Dependencies::scheduler() ) {
			// Bounded deletions through the shared store API; never drop shared scheduler tables.
			do {
				$ids = as_get_scheduled_actions(
					array(
						'group'    => Queue::GROUP,
						'per_page' => 100,
					),
					'ids'
				);
				foreach ( $ids as $id ) {
					\ActionScheduler::store()->delete_action( $id );
				}
				$batch_size = count( $ids );
			} while ( 100 === $batch_size );
		}
	}
}
