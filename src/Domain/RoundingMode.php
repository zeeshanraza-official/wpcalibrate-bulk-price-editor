<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Domain;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Supported rounding modes.
 */
enum RoundingMode: string {
	case NONE            = 'none';
	case NEAREST_WHOLE   = 'nearest_whole';
	case UP_WHOLE        = 'up_whole';
	case DOWN_WHOLE      = 'down_whole';
	case END_99          = 'end_99';
	case END_95          = 'end_95';
	case END_49          = 'end_49';
	case STORE_PRECISION = 'store_precision';

	/**
	 * Get human readable label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::NONE            => __( 'No additional rounding', 'wpcalibrate-bulk-price-editor' ),
			self::NEAREST_WHOLE   => __( 'Round to nearest whole amount (e.g. 19.00)', 'wpcalibrate-bulk-price-editor' ),
			self::UP_WHOLE        => __( 'Round up to whole amount (e.g. 20.00)', 'wpcalibrate-bulk-price-editor' ),
			self::DOWN_WHOLE      => __( 'Round down to whole amount (e.g. 19.00)', 'wpcalibrate-bulk-price-editor' ),
			self::END_99          => __( 'End in .99 (e.g. 19.99)', 'wpcalibrate-bulk-price-editor' ),
			self::END_95          => __( 'End in .95 (e.g. 19.95)', 'wpcalibrate-bulk-price-editor' ),
			self::END_49          => __( 'End in .49 (e.g. 19.49)', 'wpcalibrate-bulk-price-editor' ),
			self::STORE_PRECISION => __( 'Round to store decimal precision', 'wpcalibrate-bulk-price-editor' ),
		};
	}
}
