<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Plugin deactivation handler.
 */
final class Deactivator {

	/**
	 * Run deactivation tasks.
	 */
	public static function deactivate(): void {
		// Stop recurring maintenance cleanup in Action Scheduler
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Activator::RETENTION_ACTION, array(), Activator::AS_GROUP );
		}

		// Note: We intentionally preserve custom tables, job history, and configuration settings.
		// We do not revert or alter product prices on deactivation.
	}
}
