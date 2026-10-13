<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Application;

use WPCalibrate\BulkPriceEditor\Database\JobRepository;
use WPCalibrate\BulkPriceEditor\Database\JobItemRepository;
use WPCalibrate\BulkPriceEditor\Domain\JobStatus;
use WPCalibrate\BulkPriceEditor\Domain\PriceAction;
use WPCalibrate\BulkPriceEditor\Domain\RoundingMode;
use WPCalibrate\BulkPriceEditor\Jobs\ActionSchedulerManager;
use WPCalibrate\BulkPriceEditor\Support\Logger;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Coordinates bulk price job creation, execution, and progress tracking.
 */
final class JobService {

	/**
	 * Create and initialize a new bulk pricing job.
	 *
	 * @param array<string, mixed> $payload Job configuration payload.
	 * @param int                  $creator_id User ID of the authorized initiator.
	 * @return array<string, mixed>
	 */
	public static function create_job( array $payload, int $creator_id ): array {
		$action_str    = sanitize_text_field( $payload['action'] ?? '' );
		$action        = PriceAction::tryFrom( $action_str );
		$price_value   = isset( $payload['value'] ) ? (float) $payload['value'] : 0.0;
		$rounding_str  = sanitize_text_field( $payload['rounding_mode'] ?? 'none' );
		$rounding_mode = RoundingMode::tryFrom( $rounding_str ) ?? RoundingMode::NONE;
		$scheduled_at  = ! empty( $payload['scheduled_at'] ) ? sanitize_text_field( $payload['scheduled_at'] ) : null;
		$auto_restore_at = ! empty( $payload['auto_restore_at'] ) ? sanitize_text_field( $payload['auto_restore_at'] ) : null;

		if ( ! $action ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid price action specified.', 'wpcalibrate-bulk-price-editor' ),
			);
		}

		// Resolve Target IDs
		$target_ids = array();
		if ( ! empty( $payload['select_all_matching'] ) && ! empty( $payload['filter_criteria'] ) ) {
			$target_ids = FilterService::get_matching_target_ids( (array) $payload['filter_criteria'] );
		} elseif ( ! empty( $payload['target_ids'] ) && is_array( $payload['target_ids'] ) ) {
			$target_ids = array_map( 'intval', $payload['target_ids'] );
		}

		if ( empty( $target_ids ) ) {
			return array(
				'success' => false,
				'message' => __( 'No products or variations selected for editing.', 'wpcalibrate-bulk-price-editor' ),
			);
		}

		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;

		// 1. Snapshot Before-Prices and Precompute Expected Values
		$items_to_insert = array();
		foreach ( $target_ids as $tid ) {
			if ( ! function_exists( 'wc_get_product' ) ) {
				continue;
			}

			$product = wc_get_product( $tid );
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$parent_id   = $product instanceof \WC_Product_Variation ? $product->get_parent_id() : 0;
			$target_type = $product instanceof \WC_Product_Variation ? 'variation' : 'simple';

			$reg_price  = '' !== $product->get_regular_price() ? (float) $product->get_regular_price() : null;
			$sale_price = '' !== $product->get_sale_price() ? (float) $product->get_sale_price() : null;

			$calc = PricingService::calculate(
				$reg_price,
				$sale_price,
				$action,
				$price_value,
				$rounding_mode,
				$decimals
			);

			$items_to_insert[] = array(
				'product_id'           => $tid,
				'parent_id'            => $parent_id,
				'target_type'          => $target_type,
				'before_regular_price' => $reg_price,
				'before_sale_price'    => $sale_price,
				'after_regular_price'  => $calc->proposed_regular,
				'after_sale_price'     => $calc->proposed_sale,
			);
		}

		if ( empty( $items_to_insert ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not find any valid WooCommerce products among the selected targets.', 'wpcalibrate-bulk-price-editor' ),
			);
		}

		// Determine initial status based on scheduling
		$is_scheduled = false;
		$scheduled_timestamp = 0;
		if ( ! empty( $scheduled_at ) ) {
			// Convert WordPress site timezone datetime to UTC timestamp
			$tz = wp_timezone();
			$dt = date_create( $scheduled_at, $tz );
			if ( $dt ) {
				$scheduled_timestamp = $dt->getTimestamp();
				if ( $scheduled_timestamp > time() ) {
					$is_scheduled = true;
				}
			}
		}

		$initial_status = $is_scheduled ? JobStatus::SCHEDULED : JobStatus::QUEUED;

		// 2. Create Job Record
		$job_id = JobRepository::create_job(
			array(
				'job_type'        => 'bulk_price_edit',
				'status'          => $initial_status->value,
				'creator_id'      => $creator_id,
				'price_action'    => $action->value,
				'price_value'     => $price_value,
				'rounding_mode'   => $rounding_mode->value,
				'filter_criteria' => $payload['filter_criteria'] ?? null,
				'total_targets'   => count( $items_to_insert ),
				'scheduled_at'    => $is_scheduled ? gmdate( 'Y-m-d H:i:s', $scheduled_timestamp ) : null,
				'auto_restore_at' => ! empty( $auto_restore_at ) ? sanitize_text_field( $auto_restore_at ) : null,
				'metadata'        => array(
					'fingerprint'          => $payload['fingerprint'] ?? '',
					'timezone'             => wp_timezone_string(),
					'site_scheduled_local' => $scheduled_at,
				),
			)
		);

		if ( 0 === $job_id ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create database job record.', 'wpcalibrate-bulk-price-editor' ),
			);
		}

		// 3. Bulk insert items into custom table
		JobItemRepository::bulk_insert_items( $job_id, $items_to_insert );

		// 4. Dispatch or Schedule in Action Scheduler
		if ( $is_scheduled ) {
			ActionSchedulerManager::schedule_job( $job_id, $scheduled_timestamp );
			Logger::info( "Job #{$job_id} scheduled for future execution at timestamp {$scheduled_timestamp}.", array( 'job_id' => $job_id ) );
		} else {
			ActionSchedulerManager::queue_batch( $job_id );
			Logger::info( "Job #{$job_id} queued for execution.", array( 'job_id' => $job_id ) );
			// Run batch immediately so small to mid-sized jobs complete in real-time without delay
			\WPCalibrate\BulkPriceEditor\Jobs\BatchWorker::process_batch( $job_id );
		}

		return array(
			'success'       => true,
			'job_id'        => $job_id,
			'status'        => $initial_status->value,
			'total_targets' => count( $items_to_insert ),
			'is_scheduled'  => $is_scheduled,
		);
	}

	/**
	 * Retrieve real-time progress for job status polling.
	 *
	 * @param int $job_id Job ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_job_progress( int $job_id ): ?array {
		$job = JobRepository::get_job( $job_id );
		if ( ! $job ) {
			return null;
		}

		$total     = (int) $job['total_targets'];
		$processed = (int) $job['processed_count'];
		$percent   = $total > 0 ? min( 100.0, round( ( $processed / $total ) * 100.0, 1 ) ) : 0.0;

		$status = JobStatus::tryFrom( $job['status'] );

		return array(
			'id'              => (int) $job['id'],
			'status'          => $job['status'],
			'status_label'    => $status ? $status->label() : $job['status'],
			'total'           => $total,
			'processed'       => $processed,
			'changed'         => (int) $job['changed_count'],
			'skipped'         => (int) $job['skipped_count'],
			'failed'          => (int) $job['failed_count'],
			'percent'         => $percent,
			'started_at'      => $job['started_at'],
			'completed_at'    => $job['completed_at'],
			'is_terminal'     => $status ? $status->is_terminal() : false,
			'error_summary'   => $job['error_summary'],
		);
	}

	/**
	 * Cancel a scheduled job before execution begins.
	 */
	public static function cancel_job( int $job_id ): bool {
		$job = JobRepository::get_job( $job_id );
		if ( ! $job ) {
			return false;
		}

		$status = JobStatus::tryFrom( $job['status'] );
		if ( JobStatus::SCHEDULED !== $status && JobStatus::QUEUED !== $status ) {
			return false; // Can only cancel scheduled or queued jobs
		}

		ActionSchedulerManager::cancel_job_actions( $job_id );
		JobRepository::set_status( $job_id, JobStatus::CANCELLED );
		Logger::info( "Job #{$job_id} cancelled by administrator.", array( 'job_id' => $job_id ) );

		return true;
	}
}
