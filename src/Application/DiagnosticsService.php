<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Application;

use WPCalibrate\BulkPriceEditor\Database\Schema;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Generates safe non-sensitive system environment diagnostics.
 */
final class DiagnosticsService {

	/**
	 * Compile system status diagnostic report.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_diagnostics(): array {
		global $wpdb, $wp_version;

		$hpos_enabled = false;
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && method_exists( '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
			$hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}

		$jobs_table = Schema::get_jobs_table();
		$items_table = Schema::get_job_items_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$jobs_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $jobs_table ) ) === $jobs_table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$items_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $items_table ) ) === $items_table;

		return array(
			'plugin_version'       => defined( 'WPCALIBRATE_BPE_VERSION' ) ? WPCALIBRATE_BPE_VERSION : '1.0.0',
			'wordpress_version'    => $wp_version ?? 'unknown',
			'woocommerce_version'  => defined( 'WC_VERSION' ) ? WC_VERSION : 'inactive',
			'php_version'          => PHP_VERSION,
			'memory_limit'         => ini_get( 'memory_limit' ),
			'mysql_version'        => $wpdb->db_version(),
			'timezone'             => wp_timezone_string(),
			'hpos_enabled'         => $hpos_enabled ? 'Yes' : 'No',
			'action_scheduler'     => class_exists( 'ActionScheduler' ) ? 'Active' : 'Unavailable',
			'jobs_table_status'    => $jobs_exists ? 'Installed' : 'Missing',
			'items_table_status'   => $items_exists ? 'Installed' : 'Missing',
			'schema_version'       => get_option( Schema::DB_VERSION_OPTION, 'not installed' ),
			'store_decimals'       => function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2,
			'store_currency'       => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
		);
	}

	/**
	 * Format diagnostics report as copyable plain text.
	 */
	public static function get_formatted_text_report(): string {
		$data = self::get_diagnostics();
		$lines = array(
			'=== WPCalibrate Bulk Price Editor System Report ===',
			'Generated At: ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC',
			'',
		);

		foreach ( $data as $k => $v ) {
			$label = ucwords( str_replace( '_', ' ', $k ) );
			$lines[] = "{$label}: {$v}";
		}

		$lines[] = '';
		$lines[] = '=== End of Report ===';

		return implode( "\n", $lines );
	}
}
