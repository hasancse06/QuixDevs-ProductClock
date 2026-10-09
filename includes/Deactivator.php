<?php
namespace QuixDevs\ProductClock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Deactivator {
	public static function deactivate() {
		update_option( 'quixdevs_productclock_active', false, false );
		( new Scheduler\Queue() )->stop();
	}
}
