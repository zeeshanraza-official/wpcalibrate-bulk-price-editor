<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Database;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Custom database tables schema definitions.
 */
final class Schema {

	public const DB_VERSION = '1.0.0';
	public const DB_VERSION_OPTION = 'wpcalibrate_bpe_db_version';

	/**
	 * Get jobs table name with WordPress prefix.
	 */
	public static function get_jobs_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpcalibrate_price_jobs';
	}

	/**
	 * Get job items table name with WordPress prefix.
	 */
	public static function get_job_items_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpcalibrate_price_job_items';
	}

	/**
	 * Generate SQL schemas formatted for dbDelta().
	 *
	 * Note: dbDelta requires two spaces after PRIMARY KEY and strict syntax.
	 *
	 * @return string[] Array of table SQL statements.
	 */
	public static function get_schema_sql(): array {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$jobs_table     = self::get_jobs_table();
		$job_items_table = self::get_job_items_table();

		$sql_jobs = "CREATE TABLE {$jobs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_type varchar(32) NOT NULL DEFAULT 'bulk_price_edit',
			status varchar(32) NOT NULL DEFAULT 'draft',
			creator_id bigint(20) unsigned NOT NULL DEFAULT 0,
			price_action varchar(64) NOT NULL DEFAULT '',
			price_value decimal(18,4) NOT NULL DEFAULT 0.0000,
			rounding_mode varchar(32) NOT NULL DEFAULT 'none',
			filter_criteria longtext NULL,
			total_targets int(11) unsigned NOT NULL DEFAULT 0,
			processed_count int(11) unsigned NOT NULL DEFAULT 0,
			changed_count int(11) unsigned NOT NULL DEFAULT 0,
			skipped_count int(11) unsigned NOT NULL DEFAULT 0,
			failed_count int(11) unsigned NOT NULL DEFAULT 0,
			batch_cursor bigint(20) unsigned NOT NULL DEFAULT 0,
			scheduled_at datetime NULL DEFAULT NULL,
			auto_restore_at datetime NULL DEFAULT NULL,
			restore_parent_job_id bigint(20) unsigned NOT NULL DEFAULT 0,
			started_at datetime NULL DEFAULT NULL,
			completed_at datetime NULL DEFAULT NULL,
			error_summary text NULL,
			metadata longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status_scheduled (status, scheduled_at),
			KEY creator_id (creator_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_items = "CREATE TABLE {$job_items_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			target_type varchar(20) NOT NULL DEFAULT 'simple',
			status varchar(20) NOT NULL DEFAULT 'pending',
			before_regular_price decimal(18,4) NULL DEFAULT NULL,
			before_sale_price decimal(18,4) NULL DEFAULT NULL,
			after_regular_price decimal(18,4) NULL DEFAULT NULL,
			after_sale_price decimal(18,4) NULL DEFAULT NULL,
			applied_regular_price decimal(18,4) NULL DEFAULT NULL,
			applied_sale_price decimal(18,4) NULL DEFAULT NULL,
			error_message text NULL,
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			processed_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY job_id_status (job_id, status),
			KEY job_id_id (job_id, id),
			KEY product_id (product_id)
		) {$charset_collate};";

		return array( $sql_jobs, $sql_items );
	}
}
