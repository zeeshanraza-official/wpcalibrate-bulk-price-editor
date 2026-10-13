<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Database;

use WPCalibrate\BulkPriceEditor\Domain\JobItemStatus;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Data Access Layer for Job Items (individual product/variation targets).
 */
final class JobItemRepository {

	/**
	 * High-efficiency chunked bulk insert for target items.
	 *
	 * @param int                  $job_id Job ID.
	 * @param array<int, array<string, mixed>> $items Array of items with target_id, parent_id, target_type, before_reg, before_sale, after_reg, after_sale.
	 * @param int                  $chunk_size Number of items to insert per query (default 250).
	 * @return int Total items inserted.
	 */
	public static function bulk_insert_items( int $job_id, array $items, int $chunk_size = 250 ): int {
		global $wpdb;
		$table = Schema::get_job_items_table();
		$total_inserted = 0;

		$chunks = array_chunk( $items, $chunk_size );

		foreach ( $chunks as $chunk ) {
			$placeholders = array();
			$values       = array();

			foreach ( $chunk as $item ) {
				$before_reg  = isset( $item['before_regular_price'] ) && null !== $item['before_regular_price'] ? (float) $item['before_regular_price'] : null;
				$before_sale = isset( $item['before_sale_price'] ) && null !== $item['before_sale_price'] ? (float) $item['before_sale_price'] : null;
				$after_reg   = isset( $item['after_regular_price'] ) && null !== $item['after_regular_price'] ? (float) $item['after_regular_price'] : null;
				$after_sale  = isset( $item['after_sale_price'] ) && null !== $item['after_sale_price'] ? (float) $item['after_sale_price'] : null;

				$row_placeholders = array(
					'%d',
					'%d',
					'%d',
					'%s',
					'%s',
					null === $before_reg ? 'NULL' : '%f',
					null === $before_sale ? 'NULL' : '%f',
					null === $after_reg ? 'NULL' : '%f',
					null === $after_sale ? 'NULL' : '%f',
					'0',
				);

				$placeholders[] = '(' . implode( ', ', $row_placeholders ) . ')';

				$values[] = $job_id;
				$values[] = (int) $item['product_id'];
				$values[] = (int) ( $item['parent_id'] ?? 0 );
				$values[] = sanitize_text_field( $item['target_type'] ?? 'simple' );
				$values[] = JobItemStatus::PENDING->value;

				if ( null !== $before_reg ) {
					$values[] = $before_reg;
				}
				if ( null !== $before_sale ) {
					$values[] = $before_sale;
				}
				if ( null !== $after_reg ) {
					$values[] = $after_reg;
				}
				if ( null !== $after_sale ) {
					$values[] = $after_sale;
				}
			}

			$query = "INSERT INTO {$table} (
				job_id, product_id, parent_id, target_type, status,
				before_regular_price, before_sale_price, after_regular_price, after_sale_price, attempts
			) VALUES " . implode( ', ', $placeholders );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$inserted = $wpdb->query( $wpdb->prepare( $query, ...$values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( false !== $inserted ) {
				$total_inserted += (int) $inserted;
			}
		}

		return $total_inserted;
	}

	/**
	 * Retrieve a cursor-based batch of pending items for a job.
	 *
	 * @param int $job_id Job ID.
	 * @param int $limit Batch size.
	 * @param int $cursor_id Last processed item ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_pending_batch( int $job_id, int $limit = 50, int $cursor_id = 0 ): array {
		global $wpdb;
		$table = Schema::get_job_items_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$sql = $wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE job_id = %d AND id > %d AND status = %s
			ORDER BY id ASC
			LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$job_id,
			$cursor_id,
			JobItemStatus::PENDING->value,
			$limit
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Mark an individual item's final execution outcome.
	 */
	public static function update_item_status(
		int $item_id,
		JobItemStatus $status,
		?float $applied_reg = null,
		?float $applied_sale = null,
		?string $error = null
	): bool {
		global $wpdb;
		$table = Schema::get_job_items_table();
		$now = current_time( 'mysql', true );

		$data = array(
			'status'       => $status->value,
			'processed_at' => $now,
			'attempts'     => 1,
		);

		if ( null !== $applied_reg ) {
			$data['applied_regular_price'] = $applied_reg;
		}
		if ( null !== $applied_sale ) {
			$data['applied_sale_price'] = $applied_sale;
		}
		if ( null !== $error ) {
			$data['error_message'] = $error;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$updated = $wpdb->update(
			$table,
			$data,
			array( 'id' => $item_id ),
			null,
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Retrieve items for a job with pagination and optional status filter.
	 *
	 * @param int         $job_id Job ID.
	 * @param int         $page Page number.
	 * @param int         $per_page Items per page.
	 * @param string|null $status Optional status filter.
	 * @return array{items: array<string, mixed>[], total: int, total_pages: int}
	 */
	public static function get_job_items(
		int $job_id,
		int $page = 1,
		int $per_page = 50,
		?string $status = null
	): array {
		global $wpdb;
		$table = Schema::get_job_items_table();

		$where = array( 'job_id = %d' );
		$params = array( $job_id );

		if ( ! empty( $status ) ) {
			$where[] = 'status = %s';
			$params[] = sanitize_text_field( $status );
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}", ...$params ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$offset = ( max( 1, $page ) - 1 ) * $per_page;
		$params[] = $offset;
		$params[] = $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY id ASC LIMIT %d, %d", ...$params ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return array(
			'items'       => is_array( $rows ) ? $rows : array(),
			'total'       => $total,
			'total_pages' => (int) ceil( $total / max( 1, $per_page ) ),
		);
	}

	/**
	 * Retrieve all successfully completed items for a job (used for Undo/Restore).
	 *
	 * @param int $job_id Job ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_completed_items( int $job_id ): array {
		global $wpdb;
		$table = Schema::get_job_items_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE job_id = %d AND status = %s ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$job_id,
				JobItemStatus::COMPLETED->value
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete all items associated with a given job ID.
	 */
	public static function delete_items_by_job( int $job_id ): int {
		global $wpdb;
		$table = Schema::get_job_items_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$deleted = $wpdb->delete( $table, array( 'job_id' => $job_id ), array( '%d' ) );
		return false !== $deleted ? (int) $deleted : 0;
	}
}
