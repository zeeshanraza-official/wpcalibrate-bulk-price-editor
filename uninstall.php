<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WPCalibrate\BulkPriceEditor
 */

declare(strict_types=1);

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load autoloader if not loaded.
$autoloader = __DIR__ . '/src/Autoloader.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
	$loader = new \WPCalibrate\BulkPriceEditor\Autoloader( __DIR__ . '/src' );
	$loader->register();
}

$settings = get_option( 'wpcalibrate_bpe_settings', array() );
$delete_on_uninstall = ! empty( $settings['delete_on_uninstall'] );

if ( ! $delete_on_uninstall ) {
	// Administrator opted to keep data and history intact.
	return;
}

// 1. Drop plugin custom tables safely
if ( class_exists( '\WPCalibrate\BulkPriceEditor\Database\MigrationManager' ) ) {
	\WPCalibrate\BulkPriceEditor\Database\MigrationManager::drop_tables();
} else {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpcalibrate_price_job_items" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpcalibrate_price_jobs" );
	delete_option( 'wpcalibrate_bpe_db_version' );
}

// 2. Remove plugin options
delete_option( 'wpcalibrate_bpe_settings' );
delete_option( 'wpcalibrate_bpe_license_data' );

// 3. Remove plugin transients
delete_transient( 'wpcalibrate_bpe_lock_batch' );

// 4. Cancel scheduled Action Scheduler actions for this plugin group
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'wpcalibrate-bulk-price-editor' );
}

// NOTE: Products, variations, orders, and storefront prices are strictly NEVER altered or deleted during uninstall.
