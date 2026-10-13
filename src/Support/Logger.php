<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Support;

use WPCalibrate\BulkPriceEditor\Application\SettingsService;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Handles WooCommerce logger integration with privacy masking.
 */
final class Logger {

	public const SOURCE = 'wpcalibrate-bulk-price-editor';

	/**
	 * Log an info-level event.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Context data.
	 */
	public static function info( string $message, array $context = array() ): void {
		$level = SettingsService::get( 'log_level', 'errors' );
		if ( 'debug' !== $level ) {
			return; // Only log info messages when log_level is 'debug'
		}

		self::log( 'info', $message, $context );
	}

	/**
	 * Log a warning-level event.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Context data.
	 */
	public static function warning( string $message, array $context = array() ): void {
		$level = SettingsService::get( 'log_level', 'errors' );
		if ( 'none' === $level ) {
			return;
		}

		self::log( 'warning', $message, $context );
	}

	/**
	 * Log an error-level event.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Context data.
	 */
	public static function error( string $message, array $context = array() ): void {
		$level = SettingsService::get( 'log_level', 'errors' );
		if ( 'none' === $level ) {
			return;
		}

		self::log( 'error', $message, $context );
	}

	/**
	 * Write to WC_Logger or fallback error log.
	 *
	 * @param string               $level 'info', 'warning', 'error'.
	 * @param string               $message
	 * @param array<string, mixed> $context
	 */
	private static function log( string $level, string $message, array $context = array() ): void {
		$sanitized_context = self::mask_sensitive_data( $context );

		if ( function_exists( 'wc_get_logger' ) ) {
			$wc_logger = wc_get_logger();
			$wc_logger->log(
				$level,
				$message . ( ! empty( $sanitized_context ) ? ' | ' . wp_json_encode( $sanitized_context ) : '' ),
				array( 'source' => self::SOURCE )
			);
		} else {
			// Fallback
			error_log( sprintf( '[WPCalibrate Bulk Price Editor][%s] %s', strtoupper( $level ), $message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Mask sensitive credentials or personal data from log output.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public static function mask_sensitive_data( array $data ): array {
		$masked = array();
		foreach ( $data as $k => $v ) {
			if ( is_array( $v ) ) {
				$masked[ $k ] = self::mask_sensitive_data( $v );
				continue;
			}

			$k_lower = strtolower( (string) $k );
			if ( str_contains( $k_lower, 'key' ) || str_contains( $k_lower, 'license' ) || str_contains( $k_lower, 'secret' ) || str_contains( $k_lower, 'token' ) || str_contains( $k_lower, 'pass' ) ) {
				$str_val = (string) $v;
				$masked[ $k ] = strlen( $str_val ) > 8 ? substr( $str_val, 0, 4 ) . '...' . substr( $str_val, -4 ) : '***';
			} else {
				$masked[ $k ] = $v;
			}
		}
		return $masked;
	}
}
