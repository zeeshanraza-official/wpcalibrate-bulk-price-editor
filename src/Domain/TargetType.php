<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Domain;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Target type for price adjustment.
 */
enum TargetType: string {
	case SIMPLE    = 'simple';
	case VARIATION = 'variation';
	case VARIABLE  = 'variable';
}
