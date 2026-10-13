<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor;

use WPCalibrate\BulkPriceEditor\Database\MigrationManager;
use WPCalibrate\BulkPriceEditor\Application\SettingsService;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Plugin activation handler.
 */
final class Activator {

	public const RETENTION_ACTION = 'wpcalibrate_bpe_retention_cleanup';
	public const AS_GROUP         = 'wpcalibrate-bulk-price-editor';

	/**
	 * Run activation tasks.
	 */
	public static function activate(): void {
		// 1. Verify minimum environment requirement
		if ( ! Environment::is_php_compatible() ) {
			deactivate_plugins( plugin_basename( WPCALIBRATE_BPE_FILE ) );
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: Required PHP version, 2: Current PHP version */
						__( 'WPCalibrate Bulk Price Editor requires PHP %1$s or higher. Your server is running PHP %2$s.', 'wpcalibrate-bulk-price-editor' ),
						Environment::MIN_PHP_VERSION,
						PHP_VERSION
					)
				),
				esc_html__( 'Plugin Activation Error', 'wpcalibrate-bulk-price-editor' ),
				array( 'back_link' => true )
			);
		}

		// 2. Initialize database tables
		MigrationManager::maybe_migrate();

		// 3. Initialize default settings if not already set
		SettingsService::init_defaults_on_activation();

		// 4. Register recurring retention cleanup action in Action Scheduler
		if ( function_exists( 'as_has_scheduled_action' ) && function_exists( 'as_schedule_recurring_action' ) ) {
			if ( ! as_has_scheduled_action( self::RETENTION_ACTION, array(), self::AS_GROUP ) ) {
				as_schedule_recurring_action(
					time() + DAY_IN_SECONDS,
					DAY_IN_SECONDS,
					self::RETENTION_ACTION,
					array(),
					self::AS_GROUP
				);
			}
		}
	}
}
