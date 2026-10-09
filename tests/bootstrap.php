<?php
$tests = getenv( 'WP_TESTS_DIR' );
if ( ! $tests || ! is_readable( $tests . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Set WP_TESTS_DIR to wordpress-develop/tests/phpunit (see docs/testing.md).\n" );
	exit( 1 );
}
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
require_once $tests . '/includes/functions.php';
tests_add_filter( 'action_scheduler_store_class', static function () { return 'ActionScheduler_DBStore'; } );
tests_add_filter( 'action_scheduler_logger_class', static function () { return 'ActionScheduler_DBLogger'; } );
tests_add_filter( 'init', static function () { WC_Install::create_tables(); WC_Install::create_roles(); }, -10 );
tests_add_filter( 'pre_http_request', static function () { return new WP_Error( 'test_http_disabled', 'External requests disabled in tests.' ); } );
tests_add_filter( 'muplugins_loaded', static function () {
	require_once ABSPATH . 'wp-content/plugins/woocommerce/woocommerce.php';
	require_once dirname( __DIR__ ) . '/quixdevs-productclock.php';
} );
require $tests . '/includes/bootstrap.php';
WC_Install::create_tables();
WC_Install::create_roles();
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-content/plugins/woocommerce/includes/admin/meta-boxes/class-wc-meta-box-product-data.php';
