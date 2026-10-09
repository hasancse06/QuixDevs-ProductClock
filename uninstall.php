<?php
/** Uninstall ProductClock; preserve data unless removal was explicitly selected. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; }

require_once __DIR__ . '/includes/Dependencies.php';
require_once __DIR__ . '/includes/Support/Settings.php';
require_once __DIR__ . '/includes/Scheduler/Queue.php';
require_once __DIR__ . '/includes/Data/Uninstaller.php';

// Each site opts into removal independently. Network activation is unsupported.
( new \QuixDevs\ProductClock\Data\Uninstaller() )->run();
