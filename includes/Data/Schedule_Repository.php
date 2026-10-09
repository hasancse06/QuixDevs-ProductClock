<?php
namespace QuixDevs\ProductClock\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schedule_Repository {
	const KEY = '_quixdevs_productclock_schedule';
	public function get( $id ) {
		wp_cache_delete( $id, 'post_meta' );
		$value = get_post_meta( $id, self::KEY, true );
		return is_array( $value ) ? $value : array();
	}
	public function put( $id, array $record ) {
		$old = get_post_meta( $id, self::KEY, true );
		if ( $old !== $record && ! update_post_meta( $id, self::KEY, $record ) ) {
			throw new \RuntimeException( 'Schedule could not be persisted.' );
		}
		foreach ( array( 'state', 'enabled' ) as $field ) {
			update_post_meta( $id, '_quixdevs_productclock_' . $field, $record[ $field ] );
		}
		update_post_meta( $id, '_quixdevs_productclock_next', $this->next( $record ) );
	}
	public function next( array $s ) {
		if ( empty( $s['enabled'] ) || in_array( $s['state'], array( 'suspended', 'error' ), true ) ) {
			return 0;
		}
		$times = array();
		foreach ( array( 'publish', 'expire' ) as $action ) {
			if ( $s[ $action . '_at' ] && empty( $s[ $action . '_done' ] ) ) {
				$times[] = $s[ $action . '_at' ];
			}
		}
		return $times ? min( $times ) : 0;
	}
	/** Keyset paging visits only enrolled products, including disabled/error records. */
	public function batch( $after = 0, $limit = 50 ) {
		global $wpdb;
		// Indexed meta_key lookup and ID cursor avoid offset drift during changes.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID=m.post_id WHERE p.post_type='product' AND p.ID>%d AND m.meta_key=%s ORDER BY p.ID ASC LIMIT %d", $after, self::KEY, min( 100, max( 1, $limit ) ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	public function listing( $page = 1, $state = '', $order = 'ASC' ) {
		// phpcs:disable WordPress.DB.SlowDBQuery -- Paginated administrative queries over enrolled products only.
		$args = array(
			'post_type'      => 'product',
			'post_status'    => array( 'draft', 'publish', 'private', 'pending', 'future', 'trash' ),
			'posts_per_page' => 20,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'DESC' === $order ? 'DESC' : 'ASC',
			'meta_query'     => array(
				array(
					'key'     => self::KEY,
					'compare' => 'EXISTS',
				),
			),
		);
		if ( $state ) {
			$args['meta_query'][] = array(
				'key'   => '_quixdevs_productclock_state',
				'value' => $state,
			);
		}
		// phpcs:disable WordPress.DB.SlowDBQuery -- Bounded administrative queries use plugin-specific indexed meta keys.
		return new \WP_Query( $args );
	}
	public function counts() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT m.meta_value state, COUNT(*) total FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE m.meta_key=%s AND p.post_type='product' GROUP BY m.meta_value", '_quixdevs_productclock_state' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_column( $rows, 'total', 'state' );
	}
	/** A duplicate is a new product and must explicitly opt into automation. */
	public function exclude_duplicate_meta( $excluded, $keys ) {
		foreach ( $keys as $key ) {
			if ( 0 === strpos( $key, '_quixdevs_productclock_' ) ) {
				$excluded[] = $key; }
		}
		return array_unique( $excluded );
	}
}
