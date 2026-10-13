<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Application;

use WPCalibrate\BulkPriceEditor\Database\JobRepository;
use WPCalibrate\BulkPriceEditor\Database\JobItemRepository;
use WPCalibrate\BulkPriceEditor\Domain\JobStatus;
use WPCalibrate\BulkPriceEditor\Domain\JobItemStatus;
use WPCalibrate\BulkPriceEditor\Jobs\ActionSchedulerManager;
use WPCalibrate\BulkPriceEditor\Support\Logger;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Handles safe Undo and Restore operations with strict conflict detection.
 */
final class UndoService {

	/**
	 * Preview the undo outcome for a completed job, identifying safe targets and conflicts.
	 *
	 * @param int $parent_job_id Original job ID.
	 * @return array<string, mixed>
	 */
	public static function preview_undo( int $parent_job_id ): array {
		$parent_job = JobRepository::get_job( $parent_job_id );
		if ( ! $parent_job ) {
			return array(
				'success' => false,
				'message' => __( 'Original job not found.', 'wpcalibrate-bulk-price-editor' ),
			);
		}

		$status = JobStatus::tryFrom( $parent_job['status'] );
		if ( ! $status || ! $status->can_undo() ) {
			return array(
				'success' => false,
				'message' => __( 'This job is not in a completed status eligible for undo.', 'wpcalibrate-bulk-price-editor' ),
			);
		}

		$completed_items = JobItemRepository::get_completed_items( $parent_job_id );
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;

		$preview_items  = array();
		$eligible_count = 0;
		$conflict_count = 0;
		$missing_count  = 0;

		foreach ( $completed_items as $item ) {
			$product_id = (int) $item['product_id'];

			if ( ! function_exists( 'wc_get_product' ) ) {
				continue;
			}

			$product = wc_get_product( $product_id );
			if ( ! $product instanceof \WC_Product ) {
				$missing_count++;
				$preview_items[] = array(
					'product_id'   => $product_id,
					'title'        => __( '[Deleted Product]', 'wpcalibrate-bulk-price-editor' ),
					'status'       => 'missing',
					'warning'      => __( 'Product was deleted and cannot be restored.', 'wpcalibrate-bulk-price-editor' ),
				);
				continue;
			}

			$current_reg  = '' !== $product->get_regular_price() ? (float) $product->get_regular_price() : null;
			$current_sale = '' !== $product->get_sale_price() ? (float) $product->get_sale_price() : null;

			$written_reg  = null !== $item['applied_regular_price'] ? (float) $item['applied_regular_price'] : ( null !== $item['after_regular_price'] ? (float) $item['after_regular_price'] : null );
			$written_sale = null !== $item['applied_sale_price'] ? (float) $item['applied_sale_price'] : ( null !== $item['after_sale_price'] ? (float) $item['after_sale_price'] : null );

			$restore_target_reg  = null !== $item['before_regular_price'] ? (float) $item['before_regular_price'] : null;
			$restore_target_sale = null !== $item['before_sale_price'] ? (float) $item['before_sale_price'] : null;

			// Conflict detection: did price change since this job ran?
			$reg_conflict  = self::has_conflict( $current_reg, $written_reg );
			$sale_conflict = self::has_conflict( $current_sale, $written_sale );

			if ( $reg_conflict || $sale_conflict ) {
				$conflict_count++;
				$preview_items[] = array(
					'product_id'          => $product_id,
					'title'               => $product->get_name(),
					'status'              => 'conflict',
					'current_regular'     => $current_reg,
					'current_sale'        => $current_sale,
					'restore_target_reg'  => $restore_target_reg,
					'restore_target_sale' => $restore_target_sale,
					'warning'             => __( 'Conflict: current price differs from value written by this job. Will be skipped to protect newer changes.', 'wpcalibrate-bulk-price-editor' ),
				);
			} else {
				$eligible_count++;
				$preview_items[] = array(
					'product_id'          => $product_id,
					'title'               => $product->get_name(),
					'status'              => 'eligible',
					'current_regular'     => $current_reg,
					'current_sale'        => $current_sale,
					'restore_target_reg'  => $restore_target_reg,
					'restore_target_sale' => $restore_target_sale,
					'warning'             => null,
				);
			}
		}

		return array(
			'success'        => true,
			'parent_job_id'  => $parent_job_id,
			'total_items'    => count( $completed_items ),
			'eligible_count' => $eligible_count,
			'conflict_count' => $conflict_count,
			'missing_count'  => $missing_count,
			'items'          => array_slice( $preview_items, 0, 50 ), // Preview sample
		);
	}

	/**
	 * Create and execute an Undo/Restore job.
	 *
	 * Restores original captured pre-change values without overwriting conflicting newer values.
	 *
	 * @param int $parent_job_id Original job ID to undo.
	 * @param int $user_id User initiating undo.
	 * @return array<string, mixed>
	 */
	public static function execute_undo( int $parent_job_id, int $user_id ): array {
		$parent_job = JobRepository::get_job( $parent_job_id );
		if ( ! $parent_job ) {
			return array(
				'success' => false,
				'message' => __( 'Original job not found.', 'wpcalibrate-bulk-price-editor' ),
			);
		}

		$completed_items = JobItemRepository::get_completed_items( $parent_job_id );
		if ( empty( $completed_items ) ) {
			return array(
				'success' => false,
				'message' => __( 'No successfully completed items to restore.', 'wpcalibrate-bulk-price-editor' ),
			);
		}

		$items_to_restore = array();

		foreach ( $completed_items as $item ) {
			$product_id = (int) $item['product_id'];
			$product = wc_get_product( $product_id );
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$current_reg  = '' !== $product->get_regular_price() ? (float) $product->get_regular_price() : null;
			$current_sale = '' !== $product->get_sale_price() ? (float) $product->get_sale_price() : null;

			$written_reg  = null !== $item['applied_regular_price'] ? (float) $item['applied_regular_price'] : ( null !== $item['after_regular_price'] ? (float) $item['after_regular_price'] : null );
			$written_sale = null !== $item['applied_sale_price'] ? (float) $item['applied_sale_price'] : ( null !== $item['after_sale_price'] ? (float) $item['after_sale_price'] : null );

			// Skip conflicting items
			if ( self::has_conflict( $current_reg, $written_reg ) || self::has_conflict( $current_sale, $written_sale ) ) {
				continue;
			}

			$items_to_restore[] = array(
				'product_id'           => $product_id,
				'parent_id'            => (int) $item['parent_id'],
				'target_type'          => $item['target_type'],
				'before_regular_price' => $current_reg,
				'before_sale_price'    => $current_sale,
				'after_regular_price'  => $item['before_regular_price'],
				'after_sale_price'     => $item['before_sale_price'],
			);
		}

		if ( empty( $items_to_restore ) ) {
			return array(
				'success' => false,
				'message' => __( 'No eligible targets to restore (all targets have conflicts or no longer exist).', 'wpcalibrate-bulk-price-editor' ),
			);
		}

		// Create Restore Job Record
		$restore_job_id = JobRepository::create_job(
			array(
				'job_type'             => 'restore',
				'status'               => JobStatus::QUEUED->value,
				'creator_id'           => $user_id,
				'price_action'         => 'restore_exact',
				'price_value'          => 0.0,
				'rounding_mode'        => 'none',
				'total_targets'        => count( $items_to_restore ),
				'restore_parent_job_id' => $parent_job_id,
				'metadata'             => array(
					'undo_initiated_at' => current_time( 'mysql', true ),
					'undo_by_user'      => $user_id,
				),
			)
		);

		if ( 0 === $restore_job_id ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create restore job.', 'wpcalibrate-bulk-price-editor' ),
			);
		}

		JobItemRepository::bulk_insert_items( $restore_job_id, $items_to_restore );

		// Update parent job status to RESTORING
		JobRepository::set_status( $parent_job_id, JobStatus::RESTORING );

		// Dispatch worker for restore job
		ActionSchedulerManager::queue_batch( $restore_job_id );
		\WPCalibrate\BulkPriceEditor\Jobs\BatchWorker::process_batch( $restore_job_id );

		Logger::info( "Undo job #{$restore_job_id} created for parent job #{$parent_job_id} by user #{$user_id}.", array( 'restore_job_id' => $restore_job_id, 'parent_job_id' => $parent_job_id ) );

		return array(
			'success'        => true,
			'restore_job_id' => $restore_job_id,
			'restoring_count' => count( $items_to_restore ),
		);
	}

	/**
	 * Schedule an automatic restore job for a future timestamp.
	 */
	public static function schedule_auto_restore( int $parent_job_id, int $timestamp ): int {
		$parent_job = JobRepository::get_job( $parent_job_id );
		if ( ! $parent_job ) {
			return 0;
		}

		$completed_items = JobItemRepository::get_completed_items( $parent_job_id );
		if ( empty( $completed_items ) ) {
			return 0;
		}

		$items_to_restore = array();
		foreach ( $completed_items as $item ) {
			$items_to_restore[] = array(
				'product_id'           => (int) $item['product_id'],
				'parent_id'            => (int) $item['parent_id'],
				'target_type'          => $item['target_type'],
				'before_regular_price' => $item['after_regular_price'],
				'before_sale_price'    => $item['after_sale_price'],
				'after_regular_price'  => $item['before_regular_price'],
				'after_sale_price'     => $item['before_sale_price'],
			);
		}

		$restore_job_id = JobRepository::create_job(
			array(
				'job_type'             => 'restore',
				'status'               => JobStatus::SCHEDULED->value,
				'creator_id'           => (int) $parent_job['creator_id'],
				'price_action'         => 'restore_exact',
				'price_value'          => 0.0,
				'rounding_mode'        => 'none',
				'total_targets'        => count( $items_to_restore ),
				'scheduled_at'         => gmdate( 'Y-m-d H:i:s', $timestamp ),
				'restore_parent_job_id' => $parent_job_id,
				'metadata'             => array(
					'auto_restore' => true,
				),
			)
		);

		if ( $restore_job_id > 0 ) {
			JobItemRepository::bulk_insert_items( $restore_job_id, $items_to_restore );
			ActionSchedulerManager::schedule_job( $restore_job_id, $timestamp );
			Logger::info( "Auto restore job #{$restore_job_id} scheduled for timestamp {$timestamp}.", array( 'restore_job_id' => $restore_job_id ) );
		}

		return $restore_job_id;
	}

	/**
	 * Conflict check helper.
	 */
	private static function has_conflict( ?float $current, ?float $expected ): bool {
		$curr_empty = ( null === $current || $current <= 0.0001 );
		$exp_empty  = ( null === $expected || $expected <= 0.0001 );

		if ( $curr_empty && $exp_empty ) {
			return false;
		}

		if ( $curr_empty !== $exp_empty ) {
			return true;
		}

		return abs( (float) $current - (float) $expected ) > 0.0001;
	}
}
