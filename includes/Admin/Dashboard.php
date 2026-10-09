<?php
namespace QuixDevs\ProductClock\Admin;

use QuixDevs\ProductClock\Dependencies;
use QuixDevs\ProductClock\Data\Schedule_Repository;
use QuixDevs\ProductClock\Scheduler\Queue;
use QuixDevs\ProductClock\Support\Date_Time;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dashboard {
	private $repository;
	private $dates;
	public function __construct( Schedule_Repository $repository, Date_Time $dates ) {
		$this->repository = $repository;
		$this->dates      = $dates;
	}
	public function health() {
		$ready  = Dependencies::scheduler();
		$counts = array();
		foreach ( array( 'pending', 'failed' ) as $status ) {
			$counts[ $status ] = $ready ? \ActionScheduler::store()->query_actions(
				array(
					'group'  => Queue::GROUP,
					'status' => $status,
				),
				'count'
			) : 0;
		}
		echo '<h2>' . esc_html__( 'Scheduler Health', 'quixdevs-productclock' ) . '</h2><table class="widefat striped"><tbody>';
		$rows = array(
			__( 'Engine', 'quixdevs-productclock' )  => $ready ? __( 'WooCommerce Action Scheduler ready', 'quixdevs-productclock' ) : __( 'Unavailable', 'quixdevs-productclock' ),
			__( 'Pending actions', 'quixdevs-productclock' ) => $counts['pending'],
			__( 'Failed actions', 'quixdevs-productclock' ) => $counts['failed'],
			__( 'Last reconciliation', 'quixdevs-productclock' ) => $this->dates->display( (int) get_option( 'quixdevs_productclock_last_reconciliation', 0 ), wp_timezone_string() ),
			__( 'Next reconciliation', 'quixdevs-productclock' ) => $this->dates->display( $ready ? (int) as_next_scheduled_action( 'quixdevs_productclock_reconcile', array(), Queue::GROUP ) : 0, wp_timezone_string() ),
			__( 'WP-Cron', 'quixdevs-productclock' ) => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? __( 'Traffic-driven cron disabled; verify your server cron.', 'quixdevs-productclock' ) : __( 'Traffic-driven cron enabled; execution may be delayed.', 'quixdevs-productclock' ),
		);
		foreach ( $rows as $label => $value ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
		}
		echo '</tbody></table><p>' . esc_html__( 'Action Scheduler executes at or after the scheduled time, when a worker runs. ProductClock does not guarantee exact-second execution.', 'quixdevs-productclock' ) . '</p>';
		$last = (int) get_option( 'quixdevs_productclock_last_reconciliation', 0 );
		if ( $last && $last < time() - 2 * HOUR_IN_SECONDS ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Reconciliation has not run for more than two hours. Check server cron and WooCommerce Scheduled Actions.', 'quixdevs-productclock' ) . '</p></div>';
		}
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=action-scheduler&s=quixdevs_productclock' ) ) . '">' . esc_html__( 'Inspect WooCommerce Scheduled Actions', 'quixdevs-productclock' ) . '</a></p>';
	}
	public function render() {
		$counts  = $this->repository->counts();
		$metrics = array(
			__( 'Total managed products', 'quixdevs-productclock' ) => array_sum( $counts ),
			__( 'Pending publications', 'quixdevs-productclock' ) => $counts['pending_publication'] ?? 0,
			__( 'Pending expirations', 'quixdevs-productclock' ) => $counts['pending_expiration'] ?? 0,
			__( 'Completed schedules', 'quixdevs-productclock' ) => ( $counts['completed'] ?? 0 ) + ( $counts['expired'] ?? 0 ),
			__( 'Failed schedules', 'quixdevs-productclock' ) => $counts['error'] ?? 0,
		);
		$query   = $this->upcoming( true );
		$metrics[ __( 'Overdue schedules', 'quixdevs-productclock' ) ] = $query->found_posts;
		echo '<h2>' . esc_html__( 'Schedule Overview', 'quixdevs-productclock' ) . '</h2><table class="widefat striped"><tbody>';
		foreach ( $metrics as $label => $count ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $count ) . '</td></tr>';
		}
		echo '</tbody></table><h2>' . esc_html__( 'Upcoming Transitions', 'quixdevs-productclock' ) . '</h2><table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Product', 'quixdevs-productclock' ), __( 'Action', 'quixdevs-productclock' ), __( 'Scheduled Time', 'quixdevs-productclock' ), __( 'Current Status', 'quixdevs-productclock' ) ) as $label ) {
			echo '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $this->upcoming()->posts as $post ) {
			$s    = get_post_meta( $post->ID, Schedule_Repository::KEY, true );
			$next = $this->repository->next( $s );
			echo '<tr><td><a href="' . esc_url( get_edit_post_link( $post->ID ) . '#quixdevs-productclock' ) . '">' . esc_html( get_the_title( $post ) ) . '</a></td><td>' . esc_html( $next === $s['publish_at'] ? __( 'Publish', 'quixdevs-productclock' ) : __( 'Expire', 'quixdevs-productclock' ) ) . '</td><td>' . esc_html( $this->dates->display( $next, $s['timezone'] ) ) . '</td><td>' . esc_html( $post->post_status ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$this->health();
	}
	private function upcoming( $overdue = false ) {
		$meta = array(
			array(
				'key'     => '_quixdevs_productclock_next',
				'value'   => 0,
				'compare' => '>',
				'type'    => 'NUMERIC',
			),
		);
		if ( $overdue ) {
			$meta[] = array(
				'key'     => '_quixdevs_productclock_next',
				'value'   => time(),
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}
		// phpcs:disable WordPress.DB.SlowDBQuery -- Bounded administrative queries use plugin-specific indexed meta keys.
		return new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'draft', 'publish' ),
				'posts_per_page' => $overdue ? 1 : 10,
				'no_found_rows'  => ! $overdue,
				'meta_key'       => '_quixdevs_productclock_next',
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_query'     => $meta,
			)
		);
	}
}
