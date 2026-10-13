<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Admin;

use WPCalibrate\BulkPriceEditor\Application\FilterService;
use WPCalibrate\BulkPriceEditor\Application\PreviewService;
use WPCalibrate\BulkPriceEditor\Application\JobService;
use WPCalibrate\BulkPriceEditor\Application\UndoService;
use WPCalibrate\BulkPriceEditor\Database\JobRepository;
use WPCalibrate\BulkPriceEditor\Domain\PriceAction;
use WPCalibrate\BulkPriceEditor\Domain\RoundingMode;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Handles all authenticated AJAX endpoints for bulk price workflows.
 */
final class AjaxController {

	/**
	 * Register AJAX action handlers.
	 */
	public function init(): void {
		$actions = array(
			'get_targets',
			'preview',
			'start_job',
			'poll_progress',
			'cancel_job',
			'preview_undo',
			'execute_undo',
			'delete_history',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_wpcalibrate_bpe_' . $action, array( $this, 'handle_' . $action ) );
		}
	}

	/**
	 * Security guard: verify nonce and capability.
	 */
	private function verify_request( string $capability = 'manage_woocommerce' ): void {
		check_ajax_referer( 'wpcalibrate_bpe_nonce', 'nonce' );

		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpcalibrate-bulk-price-editor' ) ), 403 );
		}
	}

	/**
	 * Query targets with criteria.
	 */
	public function handle_get_targets(): void {
		$this->verify_request();

		$page     = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? min( 100, max( 5, absint( $_POST['per_page'] ) ) ) : 20;

		$criteria = array(
			'search'       => sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ),
			'sku'          => sanitize_text_field( wp_unslash( $_POST['sku'] ?? '' ) ),
			'category_id'  => absint( $_POST['category_id'] ?? 0 ),
			'tag_id'       => absint( $_POST['tag_id'] ?? 0 ),
			'product_type' => sanitize_text_field( wp_unslash( $_POST['product_type'] ?? 'all' ) ),
			'stock_status' => sanitize_text_field( wp_unslash( $_POST['stock_status'] ?? '' ) ),
			'min_regular'  => isset( $_POST['min_regular'] ) && '' !== $_POST['min_regular'] ? (float) $_POST['min_regular'] : '',
			'max_regular'  => isset( $_POST['max_regular'] ) && '' !== $_POST['max_regular'] ? (float) $_POST['max_regular'] : '',
			'min_sale'     => isset( $_POST['min_sale'] ) && '' !== $_POST['min_sale'] ? (float) $_POST['min_sale'] : '',
			'max_sale'     => isset( $_POST['max_sale'] ) && '' !== $_POST['max_sale'] ? (float) $_POST['max_sale'] : '',
		);

		$result = FilterService::query_targets( $criteria, $page, $per_page );

		// Format targets array for JSON
		$targets_data = array_map( fn( $dto ) => $dto->to_array(), $result['targets'] );

		wp_send_json_success(
			array(
				'targets'     => $targets_data,
				'total'       => $result['total'],
				'total_pages' => $result['total_pages'],
				'page'        => $result['page'],
			)
		);
	}

	/**
	 * Compute preview.
	 */
	public function handle_preview(): void {
		$this->verify_request();

		$action_str    = sanitize_text_field( wp_unslash( $_POST['action_type'] ?? '' ) );
		$action        = PriceAction::tryFrom( $action_str );
		$value         = isset( $_POST['value'] ) ? (float) $_POST['value'] : 0.0;
		$rounding_str  = sanitize_text_field( wp_unslash( $_POST['rounding_mode'] ?? 'none' ) );
		$rounding_mode = RoundingMode::tryFrom( $rounding_str ) ?? RoundingMode::NONE;
		$page          = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
		$per_page      = isset( $_POST['per_page'] ) ? min( 100, max( 5, absint( $_POST['per_page'] ) ) ) : 20;

		if ( ! $action ) {
			wp_send_json_error( array( 'message' => __( 'Invalid price action specified.', 'wpcalibrate-bulk-price-editor' ) ) );
		}

		$target_ids = array();
		if ( ! empty( $_POST['select_all_matching'] ) && ! empty( $_POST['criteria'] ) && is_array( $_POST['criteria'] ) ) {
			$criteria = array_map( 'sanitize_text_field', wp_unslash( $_POST['criteria'] ) );
			$target_ids = FilterService::get_matching_target_ids( $criteria );
		} elseif ( ! empty( $_POST['target_ids'] ) && is_array( $_POST['target_ids'] ) ) {
			$target_ids = array_map( 'intval', wp_unslash( $_POST['target_ids'] ) );
		}

		if ( empty( $target_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No products selected for preview.', 'wpcalibrate-bulk-price-editor' ) ) );
		}

		$preview = PreviewService::generate_preview(
			$target_ids,
			$action,
			$value,
			$rounding_mode,
			$page,
			$per_page
		);

		wp_send_json_success( $preview );
	}

	/**
	 * Start or schedule bulk pricing job.
	 */
	public function handle_start_job(): void {
		$this->verify_request();

		$payload = array(
			'action'              => sanitize_text_field( wp_unslash( $_POST['action_type'] ?? '' ) ),
			'value'               => isset( $_POST['value'] ) ? (float) $_POST['value'] : 0.0,
			'rounding_mode'       => sanitize_text_field( wp_unslash( $_POST['rounding_mode'] ?? 'none' ) ),
			'scheduled_at'        => sanitize_text_field( wp_unslash( $_POST['scheduled_at'] ?? '' ) ),
			'auto_restore_at'     => sanitize_text_field( wp_unslash( $_POST['auto_restore_at'] ?? '' ) ),
			'select_all_matching' => ! empty( $_POST['select_all_matching'] ),
			'target_ids'          => isset( $_POST['target_ids'] ) && is_array( $_POST['target_ids'] ) ? array_map( 'intval', wp_unslash( $_POST['target_ids'] ) ) : array(),
			'filter_criteria'     => isset( $_POST['criteria'] ) && is_array( $_POST['criteria'] ) ? wp_unslash( $_POST['criteria'] ) : array(),
			'fingerprint'         => sanitize_text_field( wp_unslash( $_POST['fingerprint'] ?? '' ) ),
		);

		$result = JobService::create_job( $payload, get_current_user_id() );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Poll job progress.
	 */
	public function handle_poll_progress(): void {
		$this->verify_request();

		$job_id = absint( $_GET['job_id'] ?? 0 );
		$progress = JobService::get_job_progress( $job_id );

		if ( $progress ) {
			wp_send_json_success( $progress );
		} else {
			wp_send_json_error( array( 'message' => __( 'Job not found.', 'wpcalibrate-bulk-price-editor' ) ) );
		}
	}

	/**
	 * Cancel scheduled or queued job.
	 */
	public function handle_cancel_job(): void {
		$this->verify_request();

		$job_id = absint( $_POST['job_id'] ?? 0 );
		$success = JobService::cancel_job( $job_id );

		if ( $success ) {
			wp_send_json_success( array( 'message' => __( 'Job cancelled successfully.', 'wpcalibrate-bulk-price-editor' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Could not cancel job (it may have already started or finished).', 'wpcalibrate-bulk-price-editor' ) ) );
		}
	}

	/**
	 * Preview undo and detect conflicts.
	 */
	public function handle_preview_undo(): void {
		$this->verify_request();

		$parent_job_id = absint( $_GET['parent_job_id'] ?? 0 );
		$preview = UndoService::preview_undo( $parent_job_id );

		if ( ! empty( $preview['success'] ) ) {
			wp_send_json_success( $preview );
		} else {
			wp_send_json_error( $preview );
		}
	}

	/**
	 * Execute restore job.
	 */
	public function handle_execute_undo(): void {
		$this->verify_request();

		$parent_job_id = absint( $_POST['parent_job_id'] ?? 0 );
		$result = UndoService::execute_undo( $parent_job_id, get_current_user_id() );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Delete a history record.
	 */
	public function handle_delete_history(): void {
		$this->verify_request( 'manage_options' );

		$job_id = absint( $_POST['job_id'] ?? 0 );
		$deleted = JobRepository::delete_job( $job_id );

		if ( $deleted ) {
			wp_send_json_success( array( 'message' => __( 'History record deleted.', 'wpcalibrate-bulk-price-editor' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete record.', 'wpcalibrate-bulk-price-editor' ) ) );
		}
	}
}
