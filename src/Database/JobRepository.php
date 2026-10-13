<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Database;

use WPCalibrate\BulkPriceEditor\Domain\JobStatus;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Data Access Layer for Bulk Price Jobs.
 */
final class JobRepository {

	/**
	 * Allowlisted order-by columns for SQL safety.
	 */
	private const ALLOWED_ORDER_BY = array(
		'id'            => 'id',
		'status'        => 'status',
		'created_at'    => 'created_at',
		'scheduled_at'  => 'scheduled_at',
		'total_targets' => 'total_targets',
	);

	/**
	 * Insert a new job record.
	 *
	 * @param array<string, mixed> $data
	 * @return int Job ID on success, 0 on failure.
	 */
	public static function create_job( array $data ): int {
		global $wpdb;
		$table = Schema::get_jobs_table();
		$now = current_time( 'mysql', true );

		$insert_data = array(
			'job_type'             => $data['job_type'] ?? 'bulk_price_edit',
			'status'               => $data['status'] ?? JobStatus::QUEUED->value,
			'creator_id'           => absint( $data['creator_id'] ?? get_current_user_id() ),
			'price_action'         => sanitize_text_field( $data['price_action'] ?? '' ),
			'price_value'          => isset( $data['price_value'] ) ? (float) $data['price_value'] : 0.0,
			'rounding_mode'        => sanitize_text_field( $data['rounding_mode'] ?? 'none' ),
			'filter_criteria'      => ! empty( $data['filter_criteria'] ) ? wp_json_encode( $data['filter_criteria'] ) : null,
			'total_targets'        => absint( $data['total_targets'] ?? 0 ),
			'processed_count'      => 0,
			'changed_count'        => 0,
			'skipped_count'        => 0,
			'failed_count'         => 0,
			'batch_cursor'         => 0,
			'scheduled_at'         => ! empty( $data['scheduled_at'] ) ? $data['scheduled_at'] : null,
			'auto_restore_at'      => ! empty( $data['auto_restore_at'] ) ? $data['auto_restore_at'] : null,
			'restore_parent_job_id' => absint( $data['restore_parent_job_id'] ?? 0 ),
			'metadata'             => ! empty( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : null,
			'created_at'           => $now,
			'updated_at'           => $now,
		);

		$format = array(
			'%s', '%s', '%d', '%s', '%f', '%s', '%s', '%d',
			'%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d',
			'%s', '%s', '%s',
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $insert_data, $format );
		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Retrieve a job by its ID.
	 *
	 * @param int $id Job ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_job( int $id ): ?array {
		global $wpdb;
		$table = Schema::get_jobs_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		if ( ! empty( $row['filter_criteria'] ) ) {
			$row['filter_criteria'] = json_decode( $row['filter_criteria'], true );
		}
		if ( ! empty( $row['metadata'] ) ) {
			$row['metadata'] = json_decode( $row['metadata'], true );
		}

		return $row;
	}

	/**
	 * Update job record fields.
	 *
	 * @param int                  $id Job ID.
	 * @param array<string, mixed> $data Data to update.
	 * @return bool
	 */
	public static function update_job( int $id, array $data ): bool {
		global $wpdb;
		$table = Schema::get_jobs_table();
		$data['updated_at'] = current_time( 'mysql', true );

		if ( isset( $data['filter_criteria'] ) && is_array( $data['filter_criteria'] ) ) {
			$data['filter_criteria'] = wp_json_encode( $data['filter_criteria'] );
		}
		if ( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
			$data['metadata'] = wp_json_encode( $data['metadata'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$updated = $wpdb->update(
			$table,
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Atomic update of progress counts and cursor.
	 */
	public static function update_progress(
		int $id,
		int $processed_delta,
		int $changed_delta,
		int $skipped_delta,
		int $failed_delta,
		int $new_cursor
	): bool {
		global $wpdb;
		$table = Schema::get_jobs_table();
		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET processed_count = processed_count + %d,
					changed_count   = changed_count + %d,
					skipped_count   = skipped_count + %d,
					failed_count    = failed_count + %d,
					batch_cursor    = %d,
					updated_at      = %s
				WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$processed_delta,
				$changed_delta,
				$skipped_delta,
				$failed_delta,
				$new_cursor,
				$now,
				$id
			)
		);

		return false !== $updated;
	}

	/**
	 * Update job status with optional error summary.
	 */
	public static function set_status( int $id, JobStatus $status, ?string $error_summary = null ): bool {
		$data = array( 'status' => $status->value );
		$now = current_time( 'mysql', true );

		if ( JobStatus::RUNNING === $status ) {
			$data['started_at'] = $now;
		} elseif ( $status->is_terminal() ) {
			$data['completed_at'] = $now;
		}

		if ( null !== $error_summary ) {
			$data['error_summary'] = $error_summary;
		}

		return self::update_job( $id, $data );
	}

	/**
	 * List jobs with allowlisted sorting and pagination.
	 *
	 * @param array<string, mixed> $filters
	 * @param int                  $page
	 * @param int                  $per_page
	 * @param string               $order_by
	 * @param string               $order_dir
	 * @return array{jobs: array<string, mixed>[], total: int, total_pages: int}
	 */
	public static function list_jobs(
		array $filters = array(),
		int $page = 1,
		int $per_page = 20,
		string $order_by = 'id',
		string $order_dir = 'DESC'
	): array {
		global $wpdb;
		$table = Schema::get_jobs_table();

		// Strictly allowlist ORDER BY and direction to prevent SQL injection
		$col = self::ALLOWED_ORDER_BY[ $order_by ] ?? 'id';
		$dir = strtoupper( $order_dir ) === 'ASC' ? 'ASC' : 'DESC';

		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$params[] = sanitize_text_field( $filters['status'] );
		}

		if ( ! empty( $filters['job_type'] ) ) {
			$where[] = 'job_type = %s';
			$params[] = sanitize_text_field( $filters['job_type'] );
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$total_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$total = (int) $wpdb->get_var( $wpdb->prepare( $total_query, ...$params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$total = (int) $wpdb->get_var( $total_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$offset = ( max( 1, $page ) - 1 ) * $per_page;
		$params[] = $offset;
		$params[] = $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$col} {$dir} LIMIT %d, %d";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$jobs = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! empty( $row['filter_criteria'] ) ) {
					$row['filter_criteria'] = json_decode( $row['filter_criteria'], true );
				}
				if ( ! empty( $row['metadata'] ) ) {
					$row['metadata'] = json_decode( $row['metadata'], true );
				}
				$jobs[] = $row;
			}
		}

		return array(
			'jobs'        => $jobs,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / max( 1, $per_page ) ),
		);
	}

	/**
	 * Retrieve completed jobs eligible for history retention cleanup.
	 *
	 * Preserves all active/running/scheduled jobs!
	 *
	 * @param int $retention_days Days after which completed jobs can be purged.
	 * @param int $max_jobs_to_keep Cap on max completed jobs.
	 * @return int[] IDs of jobs to delete.
	 */
	public static function get_jobs_eligible_for_retention_cleanup( int $retention_days = 30, int $max_jobs_to_keep = 50 ): array {
		global $wpdb;
		$table = Schema::get_jobs_table();

		// Only terminal jobs (completed, completed_with_errors, failed, cancelled, restored)
		$terminal_statuses = array(
			JobStatus::COMPLETED->value,
			JobStatus::COMPLETED_WITH_ERRORS->value,
			JobStatus::FAILED->value,
			JobStatus::CANCELLED->value,
			JobStatus::RESTORED->value,
		);
		$in_placeholders = implode( ',', array_fill( 0, count( $terminal_statuses ), '%s' ) );

		$cutoff_date = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );

		// 1. Find jobs older than cutoff date
		$query_by_date = "SELECT id FROM {$table} WHERE status IN ({$in_placeholders}) AND completed_at < %s";
		$params_date = array_merge( $terminal_statuses, array( $cutoff_date ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ids_by_date = $wpdb->get_col( $wpdb->prepare( $query_by_date, ...$params_date ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$ids_to_purge = array_map( 'intval', (array) $ids_by_date );

		// 2. Find jobs exceeding max completed jobs limit (ordered by completed_at DESC, skip first N)
		$query_excess = "SELECT id FROM {$table} WHERE status IN ({$in_placeholders}) ORDER BY id DESC LIMIT 10000 OFFSET %d";
		$params_excess = array_merge( $terminal_statuses, array( $max_jobs_to_keep ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ids_by_excess = $wpdb->get_col( $wpdb->prepare( $query_excess, ...$params_excess ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $ids_by_excess ) ) {
			$ids_to_purge = array_merge( $ids_to_purge, array_map( 'intval', $ids_by_excess ) );
		}

		return array_values( array_unique( $ids_to_purge ) );
	}

	/**
	 * Delete a job and its associated job items.
	 */
	public static function delete_job( int $id ): bool {
		global $wpdb;
		$table_jobs = Schema::get_jobs_table();
		JobItemRepository::delete_items_by_job( $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$deleted = $wpdb->delete( $table_jobs, array( 'id' => $id ), array( '%d' ) );
		return false !== $deleted;
	}
}
