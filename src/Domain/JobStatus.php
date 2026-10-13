<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Domain;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Status of a bulk price modification job.
 */
enum JobStatus: string {
	case DRAFT                 = 'draft';
	case SCHEDULED             = 'scheduled';
	case QUEUED                = 'queued';
	case RUNNING               = 'running';
	case COMPLETED             = 'completed';
	case COMPLETED_WITH_ERRORS = 'completed_with_errors';
	case FAILED                = 'failed';
	case CANCELLED             = 'cancelled';
	case RESTORING             = 'restoring';
	case RESTORED              = 'restored';

	/**
	 * Human-readable status label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::DRAFT                 => __( 'Draft', 'wpcalibrate-bulk-price-editor' ),
			self::SCHEDULED             => __( 'Scheduled', 'wpcalibrate-bulk-price-editor' ),
			self::QUEUED                => __( 'Queued', 'wpcalibrate-bulk-price-editor' ),
			self::RUNNING               => __( 'Running', 'wpcalibrate-bulk-price-editor' ),
			self::COMPLETED             => __( 'Completed', 'wpcalibrate-bulk-price-editor' ),
			self::COMPLETED_WITH_ERRORS => __( 'Completed with Errors', 'wpcalibrate-bulk-price-editor' ),
			self::FAILED                => __( 'Failed', 'wpcalibrate-bulk-price-editor' ),
			self::CANCELLED             => __( 'Cancelled', 'wpcalibrate-bulk-price-editor' ),
			self::RESTORING             => __( 'Restoring', 'wpcalibrate-bulk-price-editor' ),
			self::RESTORED              => __( 'Restored', 'wpcalibrate-bulk-price-editor' ),
		};
	}

	/**
	 * Whether this job is considered terminal (finished, not running or queued).
	 */
	public function is_terminal(): bool {
		return in_array(
			$this,
			array(
				self::COMPLETED,
				self::COMPLETED_WITH_ERRORS,
				self::FAILED,
				self::CANCELLED,
				self::RESTORED,
			),
			true
		);
	}

	/**
	 * Whether this job can be undone.
	 */
	public function can_undo(): bool {
		return in_array(
			$this,
			array(
				self::COMPLETED,
				self::COMPLETED_WITH_ERRORS,
			),
			true
		);
	}
}
