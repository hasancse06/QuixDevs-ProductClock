<?php
namespace QuixDevs\ProductClock\Support;

use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Validator {
	public function product( $product ) {
		if ( ! $product || ! $product->is_type( array( 'simple', 'variable' ) ) || 'product' !== get_post_type( $product->get_id() ) ) {
			throw new InvalidArgumentException( esc_html__( 'Only simple products and variable parent products are supported.', 'quixdevs-productclock' ) );
		}
	}
	public function dates( $mode, $publish, $expire ) {
		if ( ! in_array( $mode, array( 'publish', 'expire', 'both' ), true ) || ( 'expire' !== $mode && ! $publish ) || ( 'publish' !== $mode && ! $expire ) ) {
			throw new InvalidArgumentException( esc_html__( 'Choose a scheduling mode and supply its required dates.', 'quixdevs-productclock' ) );
		}
		if ( $publish && $expire && $expire <= $publish ) {
			throw new InvalidArgumentException( esc_html__( 'Expiration must be later than publication.', 'quixdevs-productclock' ) );
		}
	}
}
