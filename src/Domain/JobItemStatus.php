<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Domain;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Status of an individual product/variation item within a bulk job.
 */
enum JobItemStatus: string {
	case PENDING    = 'pending';
	case PROCESSING = 'processing';
	case COMPLETED  = 'completed';
	case SKIPPED    = 'skipped';
	case FAILED     = 'failed';

	/**
	 * Human readable label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::PENDING    => __( 'Pending', 'wpcalibrate-bulk-price-editor' ),
			self::PROCESSING => __( 'Processing', 'wpcalibrate-bulk-price-editor' ),
			self::COMPLETED  => __( 'Completed', 'wpcalibrate-bulk-price-editor' ),
			self::SKIPPED    => __( 'Skipped', 'wpcalibrate-bulk-price-editor' ),
			self::FAILED     => __( 'Failed', 'wpcalibrate-bulk-price-editor' ),
		};
	}
}
