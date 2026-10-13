<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Jobs;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Manages Action Scheduler hooks, scheduling, and action cleanup.
 */
final class ActionSchedulerManager {

	public const BATCH_ACTION = 'wpcalibrate_bpe_process_batch';
	public const AS_GROUP     = 'wpcalibrate-bulk-price-editor';

	/**
	 * Register Action Scheduler action hooks.
	 */
	public static function init(): void {
		add_action( self::BATCH_ACTION, array( BatchWorker::class, 'process_batch' ), 10, 1 );
	}

	/**
	 * Enqueue an asynchronous batch execution.
	 *
	 * @param int $job_id Job ID.
	 * @return int Action ID or 0 if failed.
	 */
	public static function queue_batch( int $job_id ): int {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			// Fallback: If Action Scheduler not loaded yet, run synchronously or via wp_schedule_single_event
			BatchWorker::process_batch( $job_id );
			return 1;
		}

		return (int) as_enqueue_async_action(
			self::BATCH_ACTION,
			array( 'job_id' => $job_id ),
			self::AS_GROUP
		);
	}

	/**
	 * Schedule a future job execution at a specific timestamp.
	 *
	 * @param int $job_id Job ID.
	 * @param int $timestamp Unix timestamp in GMT/UTC.
	 * @return int Action ID.
	 */
	public static function schedule_job( int $job_id, int $timestamp ): int {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return 0;
		}

		return (int) as_schedule_single_action(
			$timestamp,
			self::BATCH_ACTION,
			array( 'job_id' => $job_id ),
			self::AS_GROUP
		);
	}

	/**
	 * Cancel and unschedule all pending actions for a job.
	 */
	public static function cancel_job_actions( int $job_id ): void {
		if ( function_exists( 'as_unschedule_action' ) ) {
			as_unschedule_action( self::BATCH_ACTION, array( 'job_id' => $job_id ), self::AS_GROUP );
		}
	}
}
