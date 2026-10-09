<?php
namespace QuixDevs\ProductClock\Admin;

use QuixDevs\ProductClock\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings_Page {
	private $settings;
	private $dashboard;
	private $schedules_list;
	public function __construct( Settings $settings, Dashboard $dashboard, Schedules_List $schedules_list ) {
		$this->settings       = $settings;
		$this->dashboard      = $dashboard;
		$this->schedules_list = $schedules_list;
	}
	public function assets( $hook ) {
		if ( 'woocommerce_page_quixdevs-productclock' === $hook ) {
			wp_enqueue_style( 'quixdevs-productclock-admin', plugins_url( 'assets/css/admin.css', QUIXDEVS_PRODUCTCLOCK_FILE ), array(), QUIXDEVS_PRODUCTCLOCK_VERSION );
		}
	}
	public function menu() {
		add_submenu_page( 'woocommerce', __( 'ProductClock', 'quixdevs-productclock' ), __( 'ProductClock', 'quixdevs-productclock' ), 'manage_woocommerce', 'quixdevs-productclock', array( $this, 'render' ) );
	}
	public function capability() {
		return current_user_can( 'manage_woocommerce' ) ? 'manage_options' : 'do_not_allow';
	}
	public function register() {
		add_filter( 'option_page_capability_quixdevs_productclock', array( $this, 'capability' ) );
		register_setting(
			'quixdevs_productclock',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->settings, 'sanitize' ),
				'default'           => $this->settings->defaults(),
			)
		);
	}
	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You cannot access ProductClock.', 'quixdevs-productclock' ) );
		}
		$tabs = array(
			'dashboard' => __( 'Dashboard', 'quixdevs-productclock' ),
			'schedules' => __( 'Schedules', 'quixdevs-productclock' ),
			'settings'  => __( 'Settings', 'quixdevs-productclock' ),
			'activity'  => __( 'Activity', 'quixdevs-productclock' ),
			'tools'     => __( 'Tools', 'quixdevs-productclock' ),
		);
		$tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? 'dashboard' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'dashboard';
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'QuixDevs ProductClock', 'quixdevs-productclock' ) . '</h1><p>' . esc_html__( 'Schedule. Publish. Expire.', 'quixdevs-productclock' ) . '</p>';
		if ( ! $this->settings->get( 'enabled' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'ProductClock is globally paused. Product schedules are retained.', 'quixdevs-productclock' ) . '</p></div>';
		}
		$message = get_transient( 'quixdevs_productclock_notice_' . get_current_user_id() );
		if ( $message ) {
			delete_transient( 'quixdevs_productclock_notice_' . get_current_user_id() );
			echo '<div class="notice notice-info"><p>' . esc_html( $message ) . '</p></div>';
		}
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'ProductClock sections', 'quixdevs-productclock' ) . '">';
		foreach ( $tabs as $key => $label ) {
			echo '<a class="nav-tab ' . ( $key === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url(
				add_query_arg(
					array(
						'page' => 'quixdevs-productclock',
						'tab'  => $key,
					),
					admin_url( 'admin.php' )
				)
			) . '" ' . ( $key === $tab ? 'aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		if ( 'dashboard' === $tab ) {
			$this->dashboard->render();
		} elseif ( 'schedules' === $tab || 'activity' === $tab ) {
			$this->schedules_list->render( 'activity' === $tab );
		} elseif ( 'settings' === $tab ) {
				$this->settings_form();
				$this->dashboard->health();
		} else {
			$this->tools();
		}
				echo '</div>';
	}
	private function settings_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			echo '<p>' . esc_html__( 'A site administrator must change these settings.', 'quixdevs-productclock' ) . '</p>';
			return;
		}
		settings_errors();
		echo '<form method="post" action="options.php">';
		settings_fields( 'quixdevs_productclock' );
		echo '<table class="form-table"><tbody>';
		$labels = array(
			'enabled'     => __( 'Enable ProductClock globally', 'quixdevs-productclock' ),
			'publishing'  => __( 'Enable scheduled publishing', 'quixdevs-productclock' ),
			'expiration'  => __( 'Enable scheduled expiration', 'quixdevs-productclock' ),
			'logging'     => __( 'Enable activity logging', 'quixdevs-productclock' ),
			'remove_data' => __( 'Delete ProductClock data on uninstall', 'quixdevs-productclock' ),
		);
		foreach ( $labels as $key => $label ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="' . esc_attr( Settings::OPTION . '[' . $key . ']' ) . '" value="1" ' . checked( $this->settings->get( $key ), true, false ) . '> ' . esc_html( $label ) . '</label></td></tr>';
		}
		echo '<tr><th scope="row"><label for="qpc-timezone">' . esc_html__( 'Timezone for new schedules', 'quixdevs-productclock' ) . '</label></th><td><select id="qpc-timezone" name="' . esc_attr( Settings::OPTION ) . '[timezone]"><option value="site">' . esc_html__( 'WordPress site timezone', 'quixdevs-productclock' ) . '</option><option value="UTC" ' . selected( $this->settings->get( 'timezone' ), 'UTC', false ) . '>UTC</option></select><p class="description">' . esc_html__( 'Existing schedules keep their saved timezone and UTC instants. Use a named site timezone to apply daylight saving rules. Paused transitions recover through reconciliation after re-enabling.', 'quixdevs-productclock' ) . '</p></td></tr></tbody></table>';
		submit_button();
		echo '</form>';
	}
	private function tools() {
		$this->dashboard->health();
		echo '<h2>' . esc_html__( 'Recovery Tools', 'quixdevs-productclock' ) . '</h2>';
		if ( current_user_can( 'manage_options' ) ) {
			foreach ( array(
				'run_due'   => __( 'Run due ProductClock tasks (up to 25)', 'quixdevs-productclock' ),
				'reconcile' => __( 'Reconcile pending schedules', 'quixdevs-productclock' ),
				'repair'    => __( 'Repair missing scheduled actions', 'quixdevs-productclock' ),
			) as $key => $label ) {
				echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post"><input type="hidden" name="action" value="quixdevs_productclock_tool"><input type="hidden" name="tool" value="' . esc_attr( $key ) . '">';
				wp_nonce_field( 'quixdevs_productclock_tool' );
				echo '<p><label><input type="checkbox" name="confirm" value="1" required> ' . esc_html__( 'I understand that this operation may process due transitions for multiple products.', 'quixdevs-productclock' ) . '</label></p>';
				submit_button( $label, 'secondary' );
				echo '</form>';
			}
		}
		echo '<h2>' . esc_html__( 'Server Cron', 'quixdevs-productclock' ) . '</h2><p>' . esc_html__( 'On your server, run WP-CLI every minute using the PHP version for your site. Replace the example path. Disable traffic-driven WP-Cron only after verifying server cron works.', 'quixdevs-productclock' ) . '</p><pre><code>* * * * * /usr/local/bin/wp --path=/path/to/wordpress cron event run --due-now &gt;/dev/null 2&gt;&amp;1</code></pre><p>' . esc_html__( 'Diagnostics are shown above. ProductClock stores no customer information and sends no telemetry.', 'quixdevs-productclock' ) . '</p>';
	}
}
