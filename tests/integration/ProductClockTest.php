<?php
use QuixDevs\ProductClock\Dependencies;
use QuixDevs\ProductClock\Activator;
use QuixDevs\ProductClock\Deactivator;
use QuixDevs\ProductClock\Plugin;
use QuixDevs\ProductClock\Support\Settings;
use QuixDevs\ProductClock\Support\Date_Time;
use QuixDevs\ProductClock\Support\Validator;
use QuixDevs\ProductClock\Support\Logger;
use QuixDevs\ProductClock\Support\Lock;
use QuixDevs\ProductClock\Data\Schedule_Repository;
use QuixDevs\ProductClock\Data\Uninstaller;
use QuixDevs\ProductClock\Scheduler\Queue;
use QuixDevs\ProductClock\Scheduler\Schedule_Manager;
use QuixDevs\ProductClock\Scheduler\Transition_Processor;
use QuixDevs\ProductClock\Scheduler\Reconciliation;
use QuixDevs\ProductClock\Admin\Product_Fields;
use QuixDevs\ProductClock\Admin\Admin_Actions;

class ProductClockTest extends WP_UnitTestCase {
	private $repository;
	private $queue;
	private $settings;
	private $manager;
	private $processor;
	private $reconciliation;
	private $dates;
	private $logger;
	private $lock;
	private $fields;
	private $actions;
	private $ids = array();
	public function set_up() {
		parent::set_up();
		WC_Install::create_roles();
		update_option( 'quixdevs_productclock_active', true, false );
		update_option( 'timezone_string', 'Asia/Dhaka' );
		$this->settings = new Settings();
		update_option( Settings::OPTION, $this->settings->defaults(), false );
		$this->repository = new Schedule_Repository(); $this->queue = new Queue(); $this->dates = new Date_Time(); $this->lock = new Lock(); $this->logger = new Logger( $this->settings );
		$this->manager = new Schedule_Manager( $this->repository, $this->queue, $this->dates, new Validator(), $this->settings, $this->lock, $this->logger );
		$this->processor = new Transition_Processor( $this->repository, $this->manager, $this->queue, $this->settings, $this->lock, $this->logger, new Validator() );
		$this->reconciliation = new Reconciliation( $this->repository, $this->queue, $this->lock, $this->logger );
		$this->fields = new Product_Fields( $this->repository, $this->manager, $this->dates, $this->settings );
		$this->actions = new Admin_Actions( $this->reconciliation, $this->processor );
		// Use this fixture's processor for lifecycle callbacks, including the real WordPress status hook.
		global $wp_filter;
		foreach ( array( 'transition_post_status', 'before_delete_post' ) as $hook ) {
			foreach ( $wp_filter[ $hook ]->callbacks[10] ?? array() as $callback ) {
				if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Transition_Processor ) { remove_action( $hook, $callback['function'], 10 ); }
			}
		}
		add_action( 'transition_post_status', array( $this->processor, 'status_changed' ), 10, 3 );
		add_action( 'before_delete_post', array( $this->processor, 'deleting' ) );
	}
	public function tear_down() {
		$_POST = array();
		$this->queue->stop();
		foreach ( $this->ids as $id ) { wp_delete_post( $id, true ); }
		$this->ids = array();
		parent::tear_down();
	}
	private function product( $status = 'draft', $variable = false ) {
		$p = $variable ? new WC_Product_Variable() : new WC_Product_Simple();
		$p->set_name( 'Clock test product' ); $p->set_status( $status ); $p->set_regular_price( '42.50' ); $p->set_sku( 'qpc-' . wp_generate_uuid4() ); $p->set_manage_stock( true ); $p->set_stock_quantity( 7 ); $p->save();
		$this->ids[] = $p->get_id(); return $p;
	}
	private function schedule( $id, $publish = 0, $expire = 0, $extra = array() ) {
		return $this->manager->save( $id, array_merge( array( 'enabled' => true, 'mode' => $publish && $expire ? 'both' : ( $publish ? 'publish' : 'expire' ), 'publish' => $this->dates->input( $publish, 'Asia/Dhaka' ), 'expire' => $this->dates->input( $expire, 'Asia/Dhaka' ) ), $extra ) );
	}
	public function test_dependencies_and_boot_are_idempotent() {
		$this->assertInstanceOf( ActionScheduler_DBStore::class, ActionScheduler::store() );
		$this->assertTrue( Dependencies::woocommerce() ); $this->assertTrue( Dependencies::scheduler() );
		$before = has_action( 'quixdevs_productclock_publish' ); Plugin::boot(); Plugin::boot(); $this->assertSame( $before, has_action( 'quixdevs_productclock_publish' ) );
	}
	public function test_activation_deactivation_preserve_records_and_unrelated_jobs() {
		$p = $this->product(); $s = $this->schedule( $p->get_id(), time() + 3600 );
		as_schedule_single_action( time() + 3600, 'unrelated_example', array(), 'unrelated' );
		Deactivator::deactivate(); $this->assertFalse( as_has_scheduled_action( 'quixdevs_productclock_publish', array( $p->get_id(), $s['revision'] ), Queue::GROUP ) );
		$this->assertSame( $s, $this->repository->get( $p->get_id() ) ); $this->assertTrue( as_has_scheduled_action( 'unrelated_example', array(), 'unrelated' ) );
		Activator::activate(); $this->reconciliation->batch(); $this->assertTrue( as_has_scheduled_action( 'quixdevs_productclock_publish', array( $p->get_id(), $s['revision'] ), Queue::GROUP ) );
	}
	public function test_create_update_cancel_and_stale_revision() {
		$p = $this->product(); $s = $this->schedule( $p->get_id(), time() - 100 ); $n = $this->schedule( $p->get_id(), time() + 3600 );
		$this->assertNotSame( $s['revision'], $n['revision'] );
		$this->assertFalse( as_has_scheduled_action( 'quixdevs_productclock_publish', array( $p->get_id(), $s['revision'] ), Queue::GROUP ) );
		$this->processor->publish( $p->get_id(), $s['revision'] ); $this->assertSame( 'draft', get_post_status( $p->get_id() ) );
	}
	public function test_publish_only_crud_duplicate_and_no_rearm() {
		$p = $this->product(); $sku = $p->get_sku(); $at = time() - 10; $s = $this->schedule( $p->get_id(), $at );
		$this->processor->publish( $p->get_id(), $s['revision'] ); $this->processor->publish( $p->get_id(), $s['revision'] );
		$fresh = wc_get_product( $p->get_id() ); $this->assertSame( 'publish', $fresh->get_status() ); $this->assertSame( $sku, $fresh->get_sku() ); $this->assertSame( '42.50', $fresh->get_regular_price() ); $this->assertSame( 7, $fresh->get_stock_quantity() );
		$saved = $this->schedule( $p->get_id(), $at ); $this->assertTrue( $saved['publish_done'] ); $this->assertSame( 'completed', $saved['state'] );
	}
	public function test_expire_only_and_duplicate() {
		$p = $this->product( 'publish' ); $s = $this->schedule( $p->get_id(), 0, time() - 10 );
		$this->processor->expire( $p->get_id(), $s['revision'] ); $this->processor->expire( $p->get_id(), $s['revision'] );
		$this->assertSame( 'draft', get_post_status( $p->get_id() ) ); $this->assertSame( 'expired', $this->repository->get( $p->get_id() )['state'] );
	}
	public function test_combined_lifecycle_and_variable_parent() {
		$p = $this->product( 'draft', true ); $s = $this->schedule( $p->get_id(), time() - 100, time() + 3600 );
		$this->processor->publish( $p->get_id(), $s['revision'] ); $this->assertSame( 'publish', get_post_status( $p->get_id() ) );
		$s = $this->repository->get( $p->get_id() ); $this->assertSame( 'pending_expiration', $s['state'] );
		$s['expire_at'] = time() - 1; $this->repository->put( $p->get_id(), $s ); $this->processor->expire( $p->get_id(), $s['revision'] ); $this->assertSame( 'draft', get_post_status( $p->get_id() ) );
	}
	public function test_both_past_never_publishes() {
		$p = $this->product(); $s = $this->schedule( $p->get_id(), time() - 100, time() - 10 );
		$this->processor->publish( $p->get_id(), $s['revision'] ); $this->processor->expire( $p->get_id(), $s['revision'] );
		$this->assertSame( 'draft', get_post_status( $p->get_id() ) ); $this->assertTrue( $this->repository->get( $p->get_id() )['expire_done'] );
	}
	public function test_delayed_publication_cannot_override_expiry() {
		$p = $this->product(); $s = $this->schedule( $p->get_id(), time() - 100, time() + 60 ); $s['expire_at'] = time() - 1; $this->repository->put( $p->get_id(), $s );
		$this->processor->publish( $p->get_id(), $s['revision'] ); $this->assertSame( 'draft', get_post_status( $p->get_id() ) ); $this->assertTrue( $this->repository->get( $p->get_id() )['publish_done'] );
	}
	public function test_future_action_is_not_due() { $p = $this->product(); $s = $this->schedule( $p->get_id(), time() + 3600 ); $this->processor->publish( $p->get_id(), $s['revision'] ); $this->assertSame( 'draft', get_post_status( $p->get_id() ) ); }
	public function test_invalid_relationships() {
		$p = $this->product(); foreach ( array( 0, -10 ) as $offset ) { try { $this->schedule( $p->get_id(), time() + 60, time() + 60 + $offset ); $this->fail( 'Invalid date relationship accepted.' ); } catch ( InvalidArgumentException $e ) { $this->assertNotEmpty( $e->getMessage() ); } }
	}
	public function test_expiry_only_requires_published_status() { $p = $this->product(); $this->expectException( InvalidArgumentException::class ); $this->schedule( $p->get_id(), 0, time() + 60 ); }
	public function test_published_future_publication_rejected() { $p = $this->product( 'publish' ); $this->expectException( InvalidArgumentException::class ); $this->schedule( $p->get_id(), time() + 60 ); }
	public function test_disable_invalidates_actions() {
		$p = $this->product(); $s = $this->schedule( $p->get_id(), time() - 10 ); $this->manager->save( $p->get_id(), array( 'enabled' => false ) ); $this->processor->publish( $p->get_id(), $s['revision'] );
		$this->assertSame( 'disabled', $this->repository->get( $p->get_id() )['state'] ); $this->assertSame( 'draft', get_post_status( $p->get_id() ) );
	}
	public function test_manual_override_suspends_and_explicit_resume() {
		$p = $this->product(); $publish = time() - 10; $expire = time() + 100; $s = $this->schedule( $p->get_id(), $publish, $expire );
		$p->set_status( 'publish' ); $p->save(); $this->assertSame( 'suspended', $this->repository->get( $p->get_id() )['state'] );
		$this->processor->publish( $p->get_id(), $s['revision'] );
		$n = $this->schedule( $p->get_id(), $publish, $expire, array( 'resume' => true ) ); $this->processor->publish( $p->get_id(), $n['revision'] ); $this->assertSame( 'pending_expiration', $this->repository->get( $p->get_id() )['state'] );
	}
	public function test_trash_restore_stays_suspended() {
		$p = $this->product(); $s = $this->schedule( $p->get_id(), time() - 10 ); wp_trash_post( $p->get_id() ); wp_untrash_post( $p->get_id() ); $this->processor->publish( $p->get_id(), $s['revision'] ); $this->assertSame( 'suspended', $this->repository->get( $p->get_id() )['state'] );
	}
	public function test_deleted_product_stale_task_safe() { $p = $this->product(); $s = $this->schedule( $p->get_id(), time() - 10 ); wp_delete_post( $p->get_id(), true ); $this->processor->publish( $p->get_id(), $s['revision'] ); $this->assertFalse( get_post_status( $p->get_id() ) ); }
	public function test_private_product_rejected() { $p = $this->product( 'private' ); $this->expectException( InvalidArgumentException::class ); $this->schedule( $p->get_id(), time() - 10 ); }
	public function test_reconciliation_repairs_missing_actions_once() {
		$p = $this->product(); $s = $this->schedule( $p->get_id(), time() - 10 ); $this->queue->cancel( $p->get_id(), $s['revision'] ); $this->reconciliation->batch(); $this->reconciliation->batch();
		$ids = as_get_scheduled_actions( array( 'hook' => 'quixdevs_productclock_publish', 'args' => array( $p->get_id(), $s['revision'] ), 'group' => Queue::GROUP, 'status' => 'pending', 'per_page' => 10 ), 'ids' ); $this->assertCount( 1, $ids );
	}
	public function test_only_one_recurring_job() { $this->queue->ensure_reconciliation(); $this->queue->ensure_reconciliation(); $this->assertCount( 1, as_get_scheduled_actions( array( 'hook' => 'quixdevs_productclock_reconcile', 'group' => Queue::GROUP, 'status' => 'pending', 'per_page' => 10 ) ) ); }
	public function test_interrupted_status_write_recovers_intent() {
		$p = $this->product( 'publish' ); $s = $this->schedule( $p->get_id(), time() - 10 ); $s['intent'] = 'publish'; $s['expected_status'] = 'draft'; $this->repository->put( $p->get_id(), $s ); $this->processor->publish( $p->get_id(), $s['revision'] ); $this->assertTrue( $this->repository->get( $p->get_id() )['publish_done'] );
	}
	public function test_global_pause_prevents_transition() { $p = $this->product(); $s = $this->schedule( $p->get_id(), time() - 10 ); $options = $this->settings->all(); $options['enabled'] = false; update_option( Settings::OPTION, $options ); $this->processor->publish( $p->get_id(), $s['revision'] ); $this->assertSame( 'draft', get_post_status( $p->get_id() ) ); }
	public function test_dates_round_trip_and_timezone_changes() {
		$this->assertSame( 1793505600, $this->dates->parse( '2026-11-01 10:00:00', 'Asia/Dhaka' ) );
		$p = $this->product(); $s = $this->schedule( $p->get_id(), time() + 3600 ); update_option( 'timezone_string', 'America/New_York' ); $this->assertSame( $s['publish_at'], $this->repository->get( $p->get_id() )['publish_at'] ); $this->assertSame( 'Asia/Dhaka', $s['timezone'] );
	}
	/** @dataProvider invalid_dates */
	public function test_invalid_dates_and_dst( $date, $zone ) { $this->expectException( InvalidArgumentException::class ); $this->dates->parse( $date, $zone ); }
	public static function invalid_dates() { return array( array( '2026-02-30 10:00', 'UTC' ), array( 'tomorrow', 'UTC' ), array( '2026-03-08 02:30', 'America/New_York' ), array( '2026-11-01 01:30', 'America/New_York' ) ); }
	public function test_valid_dst_and_fixed_offset() { $this->assertSame( '2026-03-08T03:30:00', $this->dates->input( $this->dates->parse( '2026-03-08 03:30', 'America/New_York' ), 'America/New_York' ) ); $this->assertSame( $this->dates->parse( '2026-11-01 10:00', 'Asia/Dhaka' ), $this->dates->parse( '2026-11-01 10:00', '+06:00' ) ); }
	public function test_permission_and_nonce_field_checks() {
		$p = $this->product(); $_POST = array( 'qpc' => array( 'enabled' => '1', 'mode' => 'publish', 'publish' => '2027-01-01T10:00:00' ), 'quixdevs_productclock_nonce' => 'invalid' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) ); $this->fields->save( $p->get_id() ); $this->assertSame( array(), $this->repository->get( $p->get_id() ) );
		$_POST['quixdevs_productclock_nonce'] = wp_create_nonce( 'quixdevs_productclock_product' ); wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) ); $this->fields->save( $p->get_id() ); $this->assertSame( array(), $this->repository->get( $p->get_id() ) );
	}
	public function test_authorized_product_field_save_and_no_recursive_jobs() {
		$p = $this->product(); wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array( 'qpc' => array( 'enabled' => '1', 'mode' => 'publish', 'publish' => '2027-01-01T10:00:00' ), 'quixdevs_productclock_nonce' => wp_create_nonce( 'quixdevs_productclock_product' ) ); $this->fields->save( $p->get_id() ); $s = $this->repository->get( $p->get_id() ); $this->assertTrue( $s['enabled'] );
		$p->set_description( 'Unrelated edit' ); $p->save(); $this->assertSame( $s['revision'], $this->repository->get( $p->get_id() )['revision'] );
	}
	public function test_tools_require_capabilities_and_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) ); $this->assertFalse( $this->actions->authorized( 'bad' ) ); $this->assertNotFalse( $this->actions->authorized( wp_create_nonce( 'quixdevs_productclock_tool' ) ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) ); $this->assertFalse( $this->actions->authorized( wp_create_nonce( 'quixdevs_productclock_tool' ) ) );
	}
	public function test_logging_disable_and_bounds() {
		$p = $this->product(); for ( $i = 0; $i < 25; ++$i ) { $this->logger->record( $p->get_id(), 'schedule_created' ); } $this->assertCount( 20, $this->logger->prune( $p->get_id() ) );
		$options = $this->settings->all(); $options['logging'] = false; update_option( Settings::OPTION, $options ); $this->logger->record( $p->get_id(), 'disabled_log' ); $this->assertSame( 'schedule_created', array_slice( $this->logger->prune( $p->get_id() ), -1 )[0]['action'] );
	}
	public function test_database_lock_blocks_second_connection_and_releases() {
		global $wpdb;
		$second = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST ); $name = 'qpc_' . md5( $wpdb->prefix . ':987654' );
		$this->lock->run( 987654, function () use ( $second, $name ) { $this->assertSame( '0', (string) $second->get_var( $second->prepare( 'SELECT GET_LOCK(%s,0)', $name ) ) ); } );
		$this->assertSame( '1', (string) $second->get_var( $second->prepare( 'SELECT GET_LOCK(%s,0)', $name ) ) ); $second->get_var( $second->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); $second->close();
	}
	public function test_uninstall_retention_and_cleanup() {
		$p = $this->product(); $this->schedule( $p->get_id(), time() + 3600 ); update_post_meta( $p->get_id(), '_unrelated_meta', 'keep' ); ( new Uninstaller() )->run(); $this->assertNotEmpty( $this->repository->get( $p->get_id() ) );
		$options = $this->settings->all(); $options['remove_data'] = true; update_option( Settings::OPTION, $options ); ( new Uninstaller() )->run(); $this->assertSame( array(), $this->repository->get( $p->get_id() ) ); $this->assertSame( 'keep', get_post_meta( $p->get_id(), '_unrelated_meta', true ) ); $this->assertSame( 'draft', get_post_status( $p->get_id() ) );
	}
	public function test_actual_action_scheduler_worker_executes_product_transition() {
		$p = $this->product(); $s = $this->schedule( $p->get_id(), time() - 10 );
		remove_all_actions( 'quixdevs_productclock_publish' );
		add_action( 'quixdevs_productclock_publish', array( $this->processor, 'publish' ), 10, 2 );
		$this->queue->cancel( $p->get_id(), $s['revision'] );
		$id = as_schedule_single_action( time() - 1, 'quixdevs_productclock_publish', array( $p->get_id(), $s['revision'] ), Queue::GROUP );
		ActionScheduler::runner()->run( 'productclock-integration' );
		$this->assertSame( 'publish', get_post_status( $p->get_id() ) );
		$this->assertSame( 'complete', ActionScheduler::store()->get_status( $id ) );
	}
	public function test_reconciliation_and_admin_queries_are_bounded() {
		for ( $i = 0; $i < 52; ++$i ) { $p = $this->product(); $this->schedule( $p->get_id(), time() + 3600 ); }
		$first = $this->repository->batch(); $this->assertCount( 50, $first );
		$this->assertCount( 2, $this->repository->batch( end( $first ) ) );
		$this->assertCount( 20, $this->repository->listing()->posts );
	}
	public function test_calendar_invalid_input_does_not_replace_schedule() {
		$p = $this->product(); $s = $this->schedule( $p->get_id(), time() + 3600 );
		try { $this->manager->save( $p->get_id(), array( 'enabled' => true, 'mode' => 'publish', 'publish' => '2027-02-30T10:00:00' ) ); $this->fail( 'Invalid calendar date accepted.' ); }
		catch ( InvalidArgumentException $e ) { $this->assertSame( $s, $this->repository->get( $p->get_id() ) ); }
	}
	public function test_variation_status_is_untouched() {
		$p = $this->product( 'draft', true ); $variation = new WC_Product_Variation(); $variation->set_parent_id( $p->get_id() ); $variation->set_status( 'publish' ); $variation->set_regular_price( '10' ); $variation->save(); $this->ids[] = $variation->get_id();
		$s = $this->schedule( $p->get_id(), time() - 100, time() + 100 ); $this->processor->publish( $p->get_id(), $s['revision'] );
		$s = $this->repository->get( $p->get_id() ); $s['expire_at'] = time() - 1; $this->repository->put( $p->get_id(), $s ); $this->processor->expire( $p->get_id(), $s['revision'] );
		$this->assertSame( 'publish', get_post_status( $variation->get_id() ) );
	}
	public function test_duplicate_product_has_no_automation() {
		require_once ABSPATH . 'wp-content/plugins/woocommerce/includes/admin/class-wc-admin-duplicate-product.php';
		$p = $this->product(); $this->schedule( $p->get_id(), time() + 3600 );
		$duplicate = ( new WC_Admin_Duplicate_Product() )->product_duplicate( wc_get_product( $p->get_id() ) );
		$this->ids[] = $duplicate->get_id();
		$this->assertSame( array(), $this->repository->get( $duplicate->get_id() ) );
	}
	public function test_settings_endpoint_requires_both_capabilities() {
		$page = new QuixDevs\ProductClock\Admin\Settings_Page( $this->settings, new QuixDevs\ProductClock\Admin\Dashboard( $this->repository, $this->dates ), new QuixDevs\ProductClock\Admin\Schedules_List( $this->repository, $this->dates, $this->logger ) );
		$user = self::factory()->user->create_and_get( array( 'role' => 'administrator' ) ); wp_set_current_user( $user->ID );
		$this->assertSame( 'manage_options', $page->capability() );
		$user->add_cap( 'manage_woocommerce', false ); wp_set_current_user( 0 ); wp_set_current_user( $user->ID ); $this->assertSame( 'do_not_allow', $page->capability() );
	}
	public function test_already_claimed_callback_skips_after_deactivation() {
		$p = $this->product(); $s = $this->schedule( $p->get_id(), time() - 10 ); Deactivator::deactivate();
		$this->processor->publish( $p->get_id(), $s['revision'] ); $this->assertSame( 'draft', get_post_status( $p->get_id() ) );
	}
	public function test_expired_draft_requires_publication_before_new_expiry_only_schedule() {
		$p = $this->product( 'publish' ); $s = $this->schedule( $p->get_id(), 0, time() - 10 ); $this->processor->expire( $p->get_id(), $s['revision'] );
		$this->expectException( InvalidArgumentException::class ); $this->schedule( $p->get_id(), 0, time() + 3600 );
	}
}
