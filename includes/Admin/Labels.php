<?php
namespace QuixDevs\ProductClock\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Labels {
	public static function states() {
		return array(
			'disabled'            => __( 'Disabled', 'quixdevs-productclock' ),
			'pending_publication' => __( 'Pending Publication', 'quixdevs-productclock' ),
			'pending_expiration'  => __( 'Pending Expiration', 'quixdevs-productclock' ),
			'expired'             => __( 'Expired', 'quixdevs-productclock' ),
			'completed'           => __( 'Completed', 'quixdevs-productclock' ),
			'suspended'           => __( 'Suspended', 'quixdevs-productclock' ),
			'error'               => __( 'Error', 'quixdevs-productclock' ),
		);
	}
	public static function state( $state ) {
		return self::states()[ $state ] ?? $state;
	}
	public static function next_action( array $schedule, $timestamp, \QuixDevs\ProductClock\Support\Date_Time $dates ) {
		if ( ! $timestamp ) {
			return __( 'No pending action', 'quixdevs-productclock' );
		}
		$action = $schedule['publish_at'] === $timestamp ? __( 'Publish', 'quixdevs-productclock' ) : __( 'Expire', 'quixdevs-productclock' );
		/* translators: 1: action name, 2: scheduled date with timezone. */
		return sprintf( __( '%1$s — %2$s', 'quixdevs-productclock' ), $action, $dates->display( $timestamp, $schedule['timezone'] ) );
	}
	public static function mode( $mode ) {
		$modes = array(
			'publish' => __( 'Publish Only', 'quixdevs-productclock' ),
			'expire'  => __( 'Expire Only', 'quixdevs-productclock' ),
			'both'    => __( 'Publish & Expire', 'quixdevs-productclock' ),
		);
		return $modes[ $mode ] ?? $mode;
	}
}
