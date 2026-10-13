<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Application;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Manages plugin settings and configuration defaults.
 */
final class SettingsService {

	public const OPTION_NAME = 'wpcalibrate_bpe_settings';

	public const DEFAULT_BATCH_SIZE           = 50;
	public const DEFAULT_RETENTION_DAYS       = 30;
	public const DEFAULT_RETENTION_MAX_JOBS   = 50;
	public const DEFAULT_DELETE_ON_UNINSTALL  = false;
	public const DEFAULT_LOG_LEVEL            = 'errors'; // 'none', 'errors', 'debug'
	public const DEFAULT_CONFLICT_POLICY      = 'skip';   // 'skip'

	/**
	 * Get all default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return array(
			'batch_size'           => self::DEFAULT_BATCH_SIZE,
			'retention_days'       => self::DEFAULT_RETENTION_DAYS,
			'retention_max_jobs'   => self::DEFAULT_RETENTION_MAX_JOBS,
			'delete_on_uninstall'  => self::DEFAULT_DELETE_ON_UNINSTALL,
			'log_level'            => self::DEFAULT_LOG_LEVEL,
			'conflict_policy'      => self::DEFAULT_CONFLICT_POLICY,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$saved = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::get_defaults(), $saved );
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Optional default fallback.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$settings = self::get_settings();
		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}
		return $default ?? self::get_defaults()[ $key ] ?? null;
	}

	/**
	 * Sanitize and update settings.
	 *
	 * @param array<string, mixed> $input Raw input settings.
	 * @return bool True if updated or unchanged.
	 */
	public static function update_settings( array $input ): bool {
		$sanitized = array(
			'batch_size'          => max( 5, min( 500, isset( $input['batch_size'] ) ? absint( $input['batch_size'] ) : self::DEFAULT_BATCH_SIZE ) ),
			'retention_days'      => max( 1, min( 365, isset( $input['retention_days'] ) ? absint( $input['retention_days'] ) : self::DEFAULT_RETENTION_DAYS ) ),
			'retention_max_jobs'  => max( 5, min( 1000, isset( $input['retention_max_jobs'] ) ? absint( $input['retention_max_jobs'] ) : self::DEFAULT_RETENTION_MAX_JOBS ) ),
			'delete_on_uninstall' => ! empty( $input['delete_on_uninstall'] ),
			'log_level'           => in_array( $input['log_level'] ?? '', array( 'none', 'errors', 'debug' ), true ) ? $input['log_level'] : self::DEFAULT_LOG_LEVEL,
			'conflict_policy'     => 'skip', // Hardened policy
		);

		return update_option( self::OPTION_NAME, $sanitized, false );
	}

	/**
	 * Initialize settings on plugin activation without overwriting existing values.
	 */
	public static function init_defaults_on_activation(): void {
		$existing = get_option( self::OPTION_NAME, null );
		if ( null === $existing ) {
			update_option( self::OPTION_NAME, self::get_defaults(), false );
		}
	}
}
