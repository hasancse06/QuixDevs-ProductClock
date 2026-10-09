<?php
namespace QuixDevs\ProductClock\Admin;

use QuixDevs\ProductClock\Data\Schedule_Repository;
use QuixDevs\ProductClock\Support\Date_Time;
use QuixDevs\ProductClock\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schedules_List {
	private $repository;
	private $dates;
	private $logger;
	public function __construct( Schedule_Repository $repository, Date_Time $dates, Logger $logger ) {
		$this->repository = $repository;
		$this->dates      = $dates;
		$this->logger     = $logger;
	}
	public function render( $activity = false ) {
		$page  = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$state = sanitize_key( wp_unslash( $_GET['state'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		if ( ! array_key_exists( $state, Labels::states() ) ) {
			$state = '';
		}
		$order = isset( $_GET['order'] ) && 'DESC' === $_GET['order'] ? 'DESC' : 'ASC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sort.
		$query = $this->repository->listing( $page, $state, $order );
		echo '<form method="get"><input type="hidden" name="page" value="quixdevs-productclock"><input type="hidden" name="tab" value="' . esc_attr( $activity ? 'activity' : 'schedules' ) . '"><label for="qpc-state">' . esc_html__( 'Schedule Status', 'quixdevs-productclock' ) . '</label> <select name="state" id="qpc-state"><option value="">' . esc_html__( 'All states', 'quixdevs-productclock' ) . '</option>';
		foreach ( Labels::states() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $state, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> <label for="qpc-order">' . esc_html__( 'Product order', 'quixdevs-productclock' ) . '</label> <select name="order" id="qpc-order"><option value="ASC">' . esc_html__( 'Oldest first', 'quixdevs-productclock' ) . '</option><option value="DESC" ' . selected( $order, 'DESC', false ) . '>' . esc_html__( 'Newest first', 'quixdevs-productclock' ) . '</option></select> ';
		submit_button( __( 'Filter', 'quixdevs-productclock' ), 'secondary', '', false );
		echo '</form><br><div class="qpc-table"><table class="widefat striped"><thead><tr>';
		$headers = $activity ? array( __( 'Product', 'quixdevs-productclock' ), __( 'Action', 'quixdevs-productclock' ), __( 'Scheduled Time', 'quixdevs-productclock' ), __( 'Actual Time', 'quixdevs-productclock' ), __( 'Result', 'quixdevs-productclock' ) ) : array( __( 'Product', 'quixdevs-productclock' ), __( 'Product Status', 'quixdevs-productclock' ), __( 'Mode', 'quixdevs-productclock' ), __( 'Publish Date', 'quixdevs-productclock' ), __( 'Expiry Date', 'quixdevs-productclock' ), __( 'Next Action', 'quixdevs-productclock' ), __( 'Schedule Status', 'quixdevs-productclock' ) );
		foreach ( $headers as $header ) {
			echo '<th scope="col">' . esc_html( $header ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $query->posts as $post ) {
			$s    = get_post_meta( $post->ID, Schedule_Repository::KEY, true );
			$link = '<a href="' . esc_url( get_edit_post_link( $post->ID ) . '#quixdevs-productclock' ) . '">' . esc_html( get_the_title( $post ) ) . '</a>';
			if ( $activity ) {
				foreach ( array_reverse( $this->logger->prune( $post->ID ) ) as $entry ) {
					echo '<tr><td>' . $link . '</td><td>' . esc_html( $entry['action'] ) . '</td><td>' . esc_html( $this->dates->display( $entry['scheduled'], $s['timezone'] ) ) . '</td><td>' . esc_html( $this->dates->display( $entry['time'], $s['timezone'] ) ) . '</td><td>' . esc_html( $entry['result'] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Link escaped above.
				}
			} else {
				echo '<tr><td>' . $link . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Link escaped above.
				foreach ( array( $post->post_status, Labels::mode( $s['mode'] ), $this->dates->display( $s['publish_at'], $s['timezone'] ), $this->dates->display( $s['expire_at'], $s['timezone'] ), Labels::next_action( $s, $this->repository->next( $s ), $this->dates ), Labels::state( $s['state'] ) ) as $cell ) {
					echo '<td>' . esc_html( $cell ) . '</td>';
				}
				echo '</tr>';
			}
		}
		if ( ! $query->posts ) {
			echo '<tr><td colspan="' . esc_attr( (string) count( $headers ) ) . '">' . esc_html__( 'No schedules found.', 'quixdevs-productclock' ) . '</td></tr>';
		}
		echo '</tbody></table></div><div class="tablenav"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'total'   => $query->max_num_pages,
					'current' => $page,
				)
			)
		);
		echo '</div></div>';
		if ( $activity ) {
			echo '<p>' . esc_html__( 'Showing up to 20 recent events per product, retained for 30 days. Full native logs follow WooCommerce log retention settings.', 'quixdevs-productclock' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ) . '">' . esc_html__( 'WooCommerce logs', 'quixdevs-productclock' ) . '</a></p>';
		}
	}
}
