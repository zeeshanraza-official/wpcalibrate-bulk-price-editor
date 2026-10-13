<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Database;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Handles database table installation, versioning, and migrations.
 */
final class MigrationManager {

	/**
	 * Run migrations if installed version differs from current schema version.
	 */
	public static function maybe_migrate(): bool {
		$installed_version = get_option( Schema::DB_VERSION_OPTION, '0.0.0' );

		if ( version_compare( (string) $installed_version, Schema::DB_VERSION, '<' ) ) {
			return self::run_migration();
		}

		return true;
	}

	/**
	 * Execute migration using dbDelta.
	 */
	public static function run_migration(): bool {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			$upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
			if ( file_exists( $upgrade_file ) ) {
				require_once $upgrade_file;
			} else {
				return false;
			}
		}

		$sqls = Schema::get_schema_sql();
		foreach ( $sqls as $sql ) {
			\dbDelta( $sql );
		}

		// Verify tables actually exist
		$jobs_table      = Schema::get_jobs_table();
		$job_items_table = Schema::get_job_items_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$jobs_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $jobs_table ) ) === $jobs_table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$items_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $job_items_table ) ) === $job_items_table;

		if ( $jobs_exists && $items_exists ) {
			update_option( Schema::DB_VERSION_OPTION, Schema::DB_VERSION, false );
			return true;
		}

		return false;
	}

	/**
	 * Drop plugin tables (used during uninstall when data removal is explicitly configured).
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$jobs_table      = Schema::get_jobs_table();
		$job_items_table = Schema::get_job_items_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$job_items_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$jobs_table}" );

		delete_option( Schema::DB_VERSION_OPTION );
	}
}
