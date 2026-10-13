<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Application;

use WPCalibrate\BulkPriceEditor\Database\JobRepository;
use WPCalibrate\BulkPriceEditor\Support\Logger;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Service managing asynchronous history retention cleanup.
 */
final class RetentionService {

	public const CLEANUP_HOOK = 'wpcalibrate_bpe_retention_cleanup';

	/**
	 * Register recurring retention cleanup hook.
	 */
	public static function init(): void {
		add_action( self::CLEANUP_HOOK, array( self::class, 'execute_retention_cleanup' ) );
	}

	/**
	 * Run bounded history cleanup.
	 *
	 * Purges only completed/terminal jobs beyond configured age or max limit.
	 * NEVER purges active, running, or scheduled jobs!
	 */
	public static function execute_retention_cleanup(): void {
		$retention_days = (int) SettingsService::get( 'retention_days', SettingsService::DEFAULT_RETENTION_DAYS );
		$max_jobs       = (int) SettingsService::get( 'retention_max_jobs', SettingsService::DEFAULT_RETENTION_MAX_JOBS );

		$eligible_job_ids = JobRepository::get_jobs_eligible_for_retention_cleanup( $retention_days, $max_jobs );

		if ( empty( $eligible_job_ids ) ) {
			return;
		}

		// Bound cleanup to max 25 jobs per run to avoid long locks
		$batch = array_slice( $eligible_job_ids, 0, 25 );
		$purged_count = 0;

		foreach ( $batch as $job_id ) {
			if ( JobRepository::delete_job( $job_id ) ) {
				$purged_count++;
			}
		}

		if ( $purged_count > 0 ) {
			Logger::info( "Retention cleanup purged {$purged_count} expired history jobs.", array( 'purged_count' => $purged_count ) );
		}
	}
}
