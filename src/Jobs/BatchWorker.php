<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Jobs;

use WPCalibrate\BulkPriceEditor\Application\PricingService;
use WPCalibrate\BulkPriceEditor\Application\RoundingService;
use WPCalibrate\BulkPriceEditor\Application\SettingsService;
use WPCalibrate\BulkPriceEditor\Database\JobRepository;
use WPCalibrate\BulkPriceEditor\Database\JobItemRepository;
use WPCalibrate\BulkPriceEditor\Domain\JobStatus;
use WPCalibrate\BulkPriceEditor\Domain\JobItemStatus;
use WPCalibrate\BulkPriceEditor\Domain\PriceAction;
use WPCalibrate\BulkPriceEditor\Domain\RoundingMode;
use WPCalibrate\BulkPriceEditor\Support\Logger;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Idempotent batch processor for executing bulk price mutations.
 */
final class BatchWorker {

	public const LOCK_TTL = 120; // Seconds

	/**
	 * Process a single batch for a job.
	 *
	 * @param int $job_id Job ID to process.
	 */
	public static function process_batch( int $job_id ): void {
		$lock_key = 'wpcalibrate_bpe_lock_job_' . $job_id;

		// 1. Concurrency Control: Acquire process lock
		if ( ! self::acquire_lock( $lock_key ) ) {
			Logger::warning( "Worker could not acquire lock for job #{$job_id}; another worker is active.", array( 'job_id' => $job_id ) );
			return;
		}

		try {
			$job = JobRepository::get_job( $job_id );
			if ( ! $job ) {
				self::release_lock( $lock_key );
				return;
			}

			// If job is already cancelled, completed, or failed, abort execution
			$current_status = JobStatus::tryFrom( $job['status'] );
			if ( ! $current_status || $current_status->is_terminal() ) {
				self::release_lock( $lock_key );
				return;
			}

			// Transition queued/scheduled job to RUNNING
			if ( in_array( $current_status, array( JobStatus::QUEUED, JobStatus::SCHEDULED ), true ) ) {
				JobRepository::set_status( $job_id, JobStatus::RUNNING );
				Logger::info( "Job #{$job_id} started execution.", array( 'job_id' => $job_id ) );
			}

			$batch_size = (int) SettingsService::get( 'batch_size', SettingsService::DEFAULT_BATCH_SIZE );
			$cursor     = (int) $job['batch_cursor'];

			// 2. Fetch next batch of pending items
			$batch_items = JobItemRepository::get_pending_batch( $job_id, $batch_size, $cursor );

			if ( empty( $batch_items ) ) {
				// No more pending items found with cursor > $cursor
				self::finalize_job( $job_id );
				self::release_lock( $lock_key );
				return;
			}

			$action        = PriceAction::tryFrom( $job['price_action'] ) ?? PriceAction::SET_REGULAR_EXACT;
			$price_value   = (float) $job['price_value'];
			$rounding_mode = RoundingMode::tryFrom( $job['rounding_mode'] ) ?? RoundingMode::NONE;
			$decimals      = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;

			$batch_processed = 0;
			$batch_changed   = 0;
			$batch_skipped   = 0;
			$batch_failed    = 0;
			$last_item_id    = $cursor;
			$affected_parents = array();

			// 3. Process items in batch
			foreach ( $batch_items as $item ) {
				$item_id    = (int) $item['id'];
				$product_id = (int) $item['product_id'];
				$last_item_id = $item_id;

				// Idempotency: skip if already processed in earlier run/retry
				if ( JobItemStatus::PENDING->value !== $item['status'] ) {
					continue;
				}

				if ( ! function_exists( 'wc_get_product' ) ) {
					JobItemRepository::update_item_status( $item_id, JobItemStatus::FAILED, null, null, 'WooCommerce unavailable.' );
					$batch_failed++;
					$batch_processed++;
					continue;
				}

				$product = wc_get_product( $product_id );
				if ( ! $product instanceof \WC_Product ) {
					// Product deleted between preview and batch execution
					JobItemRepository::update_item_status(
						$item_id,
						JobItemStatus::SKIPPED,
						null,
						null,
						__( 'Product or variation no longer exists.', 'wpcalibrate-bulk-price-editor' )
					);
					$batch_skipped++;
					$batch_processed++;
					continue;
				}

				// 4. Safe Conflict Detection: verify current prices against captured before-prices
				$current_reg  = '' !== $product->get_regular_price() ? (float) $product->get_regular_price() : null;
				$current_sale = '' !== $product->get_sale_price() ? (float) $product->get_sale_price() : null;

				$captured_reg  = null !== $item['before_regular_price'] ? (float) $item['before_regular_price'] : null;
				$captured_sale = null !== $item['before_sale_price'] ? (float) $item['before_sale_price'] : null;

				$reg_conflict  = self::has_price_conflict( $current_reg, $captured_reg );
				$sale_conflict = self::has_price_conflict( $current_sale, $captured_sale );

				if ( $reg_conflict || $sale_conflict ) {
					$reason = sprintf(
						/* translators: 1: Current regular, 2: Expected regular, 3: Current sale, 4: Expected sale */
						__( 'Conflict detected: price was modified after preview. Current: Regular=%1$s, Sale=%3$s; Previewed: Regular=%2$s, Sale=%4$s. Skipped to prevent data corruption.', 'wpcalibrate-bulk-price-editor' ),
						null !== $current_reg ? number_format( $current_reg, $decimals, '.', '' ) : 'null',
						null !== $captured_reg ? number_format( $captured_reg, $decimals, '.', '' ) : 'null',
						null !== $current_sale ? number_format( $current_sale, $decimals, '.', '' ) : 'null',
						null !== $captured_sale ? number_format( $captured_sale, $decimals, '.', '' ) : 'null'
					);
					JobItemRepository::update_item_status( $item_id, JobItemStatus::SKIPPED, null, null, $reason );
					$batch_skipped++;
					$batch_processed++;
					continue;
				}

				// 5. Calculate new prices using shared PricingService
				$calc = PricingService::calculate(
					$current_reg,
					$current_sale,
					$action,
					$price_value,
					$rounding_mode,
					$decimals
				);

				if ( ! $calc->is_valid ) {
					JobItemRepository::update_item_status( $item_id, JobItemStatus::FAILED, null, null, $calc->error ?? 'Invalid price calculation.' );
					$batch_failed++;
					$batch_processed++;
					continue;
				}

				// 6. Apply prices via WooCommerce CRUD setters
				$changed = false;
				try {
					if ( $calc->has_regular_changed && null !== $calc->proposed_regular ) {
						$product->set_regular_price( RoundingService::format_decimal( $calc->proposed_regular, $decimals ) );
						$changed = true;
					}

					if ( $calc->has_sale_changed ) {
						if ( null === $calc->proposed_sale ) {
							$product->set_sale_price( '' );
						} else {
							$product->set_sale_price( RoundingService::format_decimal( $calc->proposed_sale, $decimals ) );
						}
						$changed = true;
					}

					if ( $changed ) {
						$product->save();

						if ( function_exists( 'wc_delete_product_transients' ) ) {
							wc_delete_product_transients( $product_id );
						}

						// If variation, track parent ID to sync display price cache
						if ( $product instanceof \WC_Product_Variation ) {
							$affected_parents[] = $product->get_parent_id();
						}
						$batch_changed++;
					}

					JobItemRepository::update_item_status(
						$item_id,
						JobItemStatus::COMPLETED,
						$calc->proposed_regular,
						$calc->proposed_sale
					);
					$batch_processed++;

				} catch ( \Throwable $e ) {
					JobItemRepository::update_item_status(
						$item_id,
						JobItemStatus::FAILED,
						null,
						null,
						$e->getMessage()
					);
					$batch_failed++;
					$batch_processed++;
				}
			}

			// 7. Synchronize variable parent price caches in batches
			if ( ! empty( $affected_parents ) ) {
				$unique_parents = array_unique( $affected_parents );
				foreach ( $unique_parents as $parent_id ) {
					if ( $parent_id > 0 && class_exists( '\WC_Product_Variable' ) ) {
						\WC_Product_Variable::sync( $parent_id );
					}
					if ( function_exists( 'wc_delete_product_transients' ) ) {
						wc_delete_product_transients( $parent_id );
					}
				}
			}

			// 8. Atomically update job progress
			JobRepository::update_progress(
				$job_id,
				$batch_processed,
				$batch_changed,
				$batch_skipped,
				$batch_failed,
				$last_item_id
			);

			self::release_lock( $lock_key );

			// 9. Check if more items are pending; if yes, queue next Action Scheduler action
			$remaining = JobItemRepository::get_pending_batch( $job_id, 1, $last_item_id );
			if ( ! empty( $remaining ) ) {
				ActionSchedulerManager::queue_batch( $job_id );
			} else {
				self::finalize_job( $job_id );
			}

		} catch ( \Throwable $e ) {
			self::release_lock( $lock_key );
			Logger::error( "Fatal error in BatchWorker for job #{$job_id}: " . $e->getMessage(), array( 'exception' => $e->getTraceAsString() ) );
		}
	}

	/**
	 * Detect if price float values differ beyond minimal floating precision.
	 */
	private static function has_price_conflict( ?float $current, ?float $captured ): bool {
		$curr_empty = ( null === $current || $current <= 0.0001 );
		$capt_empty = ( null === $captured || $captured <= 0.0001 );

		if ( $curr_empty && $capt_empty ) {
			return false;
		}

		if ( $curr_empty !== $capt_empty ) {
			return true;
		}

		return abs( (float) $current - (float) $captured ) > 0.0001;
	}

	/**
	 * Finalize job status once all items are completed.
	 */
	private static function finalize_job( int $job_id ): void {
		$job = JobRepository::get_job( $job_id );
		if ( ! $job ) {
			return;
		}

		$failed_count = (int) $job['failed_count'];
		$final_status = $failed_count > 0 ? JobStatus::COMPLETED_WITH_ERRORS : JobStatus::COMPLETED;

		JobRepository::set_status( $job_id, $final_status );
		Logger::info( "Job #{$job_id} finalized with status {$final_status->value}.", array( 'job_id' => $job_id ) );

		// Check if an automatic restore date is configured
		if ( ! empty( $job['auto_restore_at'] ) && $final_status === JobStatus::COMPLETED ) {
			$restore_timestamp = strtotime( $job['auto_restore_at'] );
			if ( $restore_timestamp > time() ) {
				// Create and schedule restore job
				\WPCalibrate\BulkPriceEditor\Application\UndoService::schedule_auto_restore( $job_id, $restore_timestamp );
			}
		}

		/**
		 * Action fired when a bulk price job completes.
		 *
		 * @param int       $job_id       Job ID.
		 * @param JobStatus $final_status Final job status.
		 */
		do_action( 'wpcalibrate_bpe_job_completed', $job_id, $final_status );
	}

	/**
	 * Acquire execution lock.
	 */
	private static function acquire_lock( string $lock_key ): bool {
		$existing = get_transient( $lock_key );
		if ( false !== $existing ) {
			return false;
		}
		return set_transient( $lock_key, time(), self::LOCK_TTL );
	}

	/**
	 * Release execution lock.
	 */
	private static function release_lock( string $lock_key ): void {
		delete_transient( $lock_key );
	}
}
