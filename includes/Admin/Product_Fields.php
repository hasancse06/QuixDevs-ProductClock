<?php
namespace QuixDevs\ProductClock\Admin;

use QuixDevs\ProductClock\Data\Schedule_Repository;
use QuixDevs\ProductClock\Scheduler\Schedule_Manager;
use QuixDevs\ProductClock\Support\Date_Time;
use QuixDevs\ProductClock\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Product_Fields {
	private $repository;
	private $manager;
	private $dates;
	private $settings;
	public function __construct( Schedule_Repository $repository, Schedule_Manager $manager, Date_Time $dates, Settings $settings ) {
		$this->repository = $repository;
		$this->manager    = $manager;
		$this->dates      = $dates;
		$this->settings   = $settings;
	}
	public function tabs( $tabs ) {
		$tabs['quixdevs_productclock'] = array(
			'label'    => __( 'ProductClock', 'quixdevs-productclock' ),
			'target'   => 'quixdevs-productclock',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 75,
		);
		return $tabs;
	}
	public function panel() {
		global $post;
		echo '<div id="quixdevs-productclock" class="panel woocommerce_options_panel hidden"><h2>' . esc_html__( 'ProductClock Scheduling', 'quixdevs-productclock' ) . '</h2>';
		$this->render( $post );
		echo '</div>';
	}
	public function render( $post ) {
		$s    = $this->repository->get( $post->ID );
		$zone = $s['timezone'] ?? $this->settings->timezone();
		wp_nonce_field( 'quixdevs_productclock_product', 'quixdevs_productclock_nonce' );
		echo '<div class="qpc-fields"><p><label><input type="checkbox" name="qpc[enabled]" value="1" ' . checked( ! empty( $s['enabled'] ), true, false ) . '> ' . esc_html__( 'Enable Automatic Scheduling', 'quixdevs-productclock' ) . '</label></p>';
		echo '<p><label for="qpc-mode">' . esc_html__( 'Scheduling Mode', 'quixdevs-productclock' ) . '</label> <select id="qpc-mode" name="qpc[mode]">';
		foreach ( array(
			'publish' => __( 'Publish Only', 'quixdevs-productclock' ),
			'expire'  => __( 'Expire Only', 'quixdevs-productclock' ),
			'both'    => __( 'Publish & Expire', 'quixdevs-productclock' ),
		) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $s['mode'] ?? 'both', $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		foreach ( array(
			'publish' => __( 'Publish Date & Time', 'quixdevs-productclock' ),
			'expire'  => __( 'Expiration Date & Time', 'quixdevs-productclock' ),
		) as $action => $label ) {
			echo '<p data-qpc-action="' . esc_attr( $action ) . '"><label for="qpc-' . esc_attr( $action ) . '">' . esc_html( $label ) . '</label> <input type="datetime-local" step="1" id="qpc-' . esc_attr( $action ) . '" name="qpc[' . esc_attr( $action ) . ']" value="' . esc_attr( $this->dates->input( $s[ $action . '_at' ] ?? 0, $zone ) ) . '" aria-describedby="qpc-time-help"></p>';
		}
		echo '<p id="qpc-time-help">' . esc_html( $this->dates->label( $zone ) ) . ' — ' . esc_html__( 'Dates retain their absolute execution time when the site timezone changes. Daylight saving gaps and repeated times are rejected.', 'quixdevs-productclock' ) . '</p>';
		echo '<p>' . esc_html__( 'Future publication requires a draft product. Move it to draft yourself first. Saving a past date queues it for the next scheduler run. Expiration wins if both dates have passed.', 'quixdevs-productclock' ) . '</p>';
		if ( $s ) {
			echo '<p><strong>' . esc_html__( 'Current Schedule Status:', 'quixdevs-productclock' ) . '</strong> ' . esc_html( Labels::state( $s['state'] ) ) . '</p><p><strong>' . esc_html__( 'Next Scheduled Action:', 'quixdevs-productclock' ) . '</strong> ' . esc_html( Labels::next_action( $s, $this->repository->next( $s ), $this->dates ) ) . '</p><p><strong>' . esc_html__( 'Last Execution:', 'quixdevs-productclock' ) . '</strong> ' . esc_html( $s['last_execution'] ? $this->dates->display( $s['last_execution'], $zone ) : __( 'Not executed yet', 'quixdevs-productclock' ) ) . '</p>';
			if ( $s['last_error'] ) {
				echo '<p role="status">' . esc_html( $s['last_error'] ) . '</p>';
			}
			if ( in_array( $s['state'], array( 'suspended', 'error' ), true ) ) {
				echo '<p><label><input type="checkbox" name="qpc[resume]" value="1"> ' . esc_html__( 'Resume this schedule using the current product status. Completed dates remain completed.', 'quixdevs-productclock' ) . '</label></p>';
			}
		}
		echo '<p class="qpc-validation" role="alert" aria-live="polite"></p></div>';
	}
	public function save( $id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $id ) || 'product' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		if ( ! isset( $_POST['quixdevs_productclock_nonce'], $_POST['qpc'] ) || ! is_array( $_POST['qpc'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['quixdevs_productclock_nonce'] ) ), 'quixdevs_productclock_product' ) ) {
			return;
		}
		$input = map_deep( wp_unslash( $_POST['qpc'] ), 'sanitize_text_field' );
		try {
			$this->manager->save( $id, $input );
		} catch ( \Throwable $e ) {
			\WC_Admin_Meta_Boxes::add_error( $e instanceof \InvalidArgumentException ? $e->getMessage() : __( 'ProductClock could not save or queue the schedule. Review scheduler health and retry.', 'quixdevs-productclock' ) );
		}
	}
	public function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_script( 'quixdevs-productclock-editor', plugins_url( 'assets/js/editor.js', QUIXDEVS_PRODUCTCLOCK_FILE ), array( 'wc-admin-product-meta-boxes' ), QUIXDEVS_PRODUCTCLOCK_VERSION, true );
		wp_localize_script( 'quixdevs-productclock-editor', 'quixdevsProductClock', array( 'invalid' => __( 'Expiration must be later than publication.', 'quixdevs-productclock' ) ) );
		wp_enqueue_style( 'quixdevs-productclock-editor', plugins_url( 'assets/css/admin.css', QUIXDEVS_PRODUCTCLOCK_FILE ), array(), QUIXDEVS_PRODUCTCLOCK_VERSION );
	}
	public function columns( $columns ) {
		$columns['quixdevs_productclock'] = __( 'ProductClock', 'quixdevs-productclock' );
		return $columns;
	}
	public function column( $column, $id ) {
		if ( 'quixdevs_productclock' !== $column ) {
			return;
		}
		// Product-list WP_Query primes meta cache; avoid repository's fresh-execution read here.
		$s = get_post_meta( $id, Schedule_Repository::KEY, true );
		if ( ! is_array( $s ) ) {
			echo '—';
			return;
		}
		echo '<a href="' . esc_url( get_edit_post_link( $id ) . '#quixdevs-productclock' ) . '">' . esc_html( Labels::state( $s['state'] ) ) . '</a>';
		foreach ( array(
			'publish' => __( 'Publish:', 'quixdevs-productclock' ),
			'expire'  => __( 'Expire:', 'quixdevs-productclock' ),
		) as $action => $label ) {
			if ( $s[ $action . '_at' ] ) {
				echo '<br>' . esc_html( $label . ' ' . $this->dates->display( $s[ $action . '_at' ], $s['timezone'] ) );
			}
		}
	}
}
