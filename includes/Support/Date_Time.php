<?php
namespace QuixDevs\ProductClock\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Date_Time {
	/** Reject DST gaps and folds; administrators choose an unambiguous minute. */
	public function parse( $value, $timezone ) {
		if ( '' === $value ) {
			return 0;
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/D', $value ) ) {
			throw new InvalidArgumentException( esc_html__( 'Use a complete date and time.', 'quixdevs-productclock' ) );
		}
		$value  = str_replace( 'T', ' ', $value );
		$value  = 16 === strlen( $value ) ? $value . ':00' : $value;
		$zone   = new DateTimeZone( $timezone );
		$date   = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, $zone );
		$errors = DateTimeImmutable::getLastErrors();
		if ( ! $date || ( $errors && ( $errors['warning_count'] || $errors['error_count'] ) ) || $date->format( 'Y-m-d H:i:s' ) !== $value ) {
			throw new InvalidArgumentException( esc_html__( 'This date is invalid or falls in a daylight saving gap.', 'quixdevs-productclock' ) );
		}
		$wall        = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) )->getTimestamp();
		$transitions = $zone->getTransitions( $wall - 172800, $wall + 172800 );
		$offsets     = array( $date->getOffset() );
		foreach ( $transitions ? $transitions : array() as $transition ) {
			$offsets[] = $transition['offset'];
		}
		$matches = array();
		foreach ( array_unique( $offsets ) as $offset ) {
			$candidate = $wall - $offset;
			if ( ( new DateTimeImmutable( '@' . $candidate ) )->setTimezone( $zone )->format( 'Y-m-d H:i:s' ) === $value ) {
				$matches[] = $candidate;
			}
		}
		if ( 1 !== count( $matches ) ) {
			throw new InvalidArgumentException( esc_html__( 'This time is ambiguous during a daylight saving change. Choose a different time or use UTC.', 'quixdevs-productclock' ) );
		}
		if ( $date->getTimestamp() <= 0 ) {
			throw new InvalidArgumentException( esc_html__( 'Choose a date after January 1, 1970.', 'quixdevs-productclock' ) );
		}
		return $date->getTimestamp();
	}
	public function input( $timestamp, $timezone ) {
		return $timestamp ? ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( $timezone ) )->format( 'Y-m-d\TH:i:s' ) : '';
	}
	public function display( $timestamp, $timezone ) {
		return $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ' T', $timestamp, new DateTimeZone( $timezone ) ) : '—';
	}
	public function label( $timezone ) {
		return $timezone . ' (UTC' . ( new DateTimeImmutable( 'now', new DateTimeZone( $timezone ) ) )->format( 'P' ) . ')';
	}
}
