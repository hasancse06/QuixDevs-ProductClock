<?php
namespace QuixDevs\ProductClock;

use QuixDevs\ProductClock\Support\Settings;
use QuixDevs\ProductClock\Support\Date_Time;
use QuixDevs\ProductClock\Support\Validator;
use QuixDevs\ProductClock\Support\Lock;
use QuixDevs\ProductClock\Support\Logger;
use QuixDevs\ProductClock\Data\Schedule_Repository;
use QuixDevs\ProductClock\Scheduler\Queue;
use QuixDevs\ProductClock\Scheduler\Schedule_Manager;
use QuixDevs\ProductClock\Scheduler\Transition_Processor;
use QuixDevs\ProductClock\Scheduler\Reconciliation;
use QuixDevs\ProductClock\Admin\Product_Fields;
use QuixDevs\ProductClock\Admin\Dashboard;
use QuixDevs\ProductClock\Admin\Schedules_List;
use QuixDevs\ProductClock\Admin\Settings_Page;
use QuixDevs\ProductClock\Admin\Admin_Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private static $instance;
	private $reconciliation;
	private $queue;
	public static function boot() {
		if ( self::$instance ) {
			return;
		}
		if ( ! Dependencies::woocommerce() ) {
			add_action( 'admin_notices', array( Dependencies::class, 'notice' ) );
			return;
		}
		self::$instance = new self();
	}
	private function __construct() {
		$settings             = new Settings();
		$dates                = new Date_Time();
		$validator            = new Validator();
		$lock                 = new Lock();
		$logger               = new Logger( $settings );
		$repository           = new Schedule_Repository();
		$this->queue          = new Queue( $logger );
		$manager              = new Schedule_Manager( $repository, $this->queue, $dates, $validator, $settings, $lock, $logger );
		$processor            = new Transition_Processor( $repository, $manager, $this->queue, $settings, $lock, $logger, $validator );
		$this->reconciliation = new Reconciliation( $repository, $this->queue, $lock, $logger );
		add_action( 'quixdevs_productclock_publish', array( $processor, 'publish' ), 10, 2 );
		add_action( 'quixdevs_productclock_expire', array( $processor, 'expire' ), 10, 2 );
		add_action( 'quixdevs_productclock_reconcile', array( $this->reconciliation, 'start' ) );
		add_action( 'quixdevs_productclock_reconcile_batch', array( $this->reconciliation, 'batch' ) );
		$guard = new \QuixDevs\ProductClock\Scheduler\Status_Guard( $lock );
		add_filter( 'wp_insert_post_data', array( $guard, 'before_write' ), PHP_INT_MAX, 2 );
		add_action( 'wp_after_insert_post', array( $guard, 'after_write' ) );
		add_action( 'shutdown', array( $guard, 'shutdown' ) );
		add_action( 'transition_post_status', array( $processor, 'status_changed' ), 10, 3 );
		add_action( 'before_delete_post', array( $processor, 'deleting' ) );
		add_filter( 'woocommerce_duplicate_product_exclude_meta', array( $repository, 'exclude_duplicate_meta' ), 10, 2 );
		add_action( 'init', array( $this, 'ready' ), 20 );
		add_action( 'action_scheduler_ensure_recurring_actions', array( $this->queue, 'ensure_reconciliation' ) );
		add_action( 'update_option_' . Settings::OPTION, array( $this, 'settings_changed' ), 10, 2 );
		if ( is_admin() ) {
			$fields = new Product_Fields( $repository, $manager, $dates, $settings );
			add_filter( 'woocommerce_product_data_tabs', array( $fields, 'tabs' ) );
			add_action( 'woocommerce_product_data_panels', array( $fields, 'panel' ) );
			add_action( 'woocommerce_process_product_meta', array( $fields, 'save' ), 30 );
			add_action( 'admin_enqueue_scripts', array( $fields, 'assets' ) );
			add_filter( 'manage_edit-product_columns', array( $fields, 'columns' ) );
			add_action( 'manage_product_posts_custom_column', array( $fields, 'column' ), 10, 2 );
			$page    = new Settings_Page( $settings, new Dashboard( $repository, $dates ), new Schedules_List( $repository, $dates, $logger ) );
			$actions = new Admin_Actions( $this->reconciliation, $processor );
			add_action( 'admin_menu', array( $page, 'menu' ) );
			add_action( 'admin_enqueue_scripts', array( $page, 'assets' ) );
			add_action( 'admin_init', array( $page, 'register' ) );
			add_action( 'admin_post_quixdevs_productclock_tool', array( $actions, 'handle' ) );
		}
	}
	public function ready() {
		if ( ! Dependencies::scheduler() ) {
			add_action( 'admin_notices', array( Dependencies::class, 'notice' ) );
			return;
		}
		if ( is_admin() || wp_doing_cron() || get_option( 'quixdevs_productclock_recover', false ) ) {
			$this->queue->ensure_reconciliation();
		}
		if ( get_option( 'quixdevs_productclock_recover', false ) ) {
			$this->reconciliation->start();
			delete_option( 'quixdevs_productclock_recover' );
		}
	}
	public function settings_changed( $old, $new_settings ) {
		if ( $old !== $new_settings && Dependencies::scheduler() ) {
			$this->reconciliation->start();
		}
	}
}
