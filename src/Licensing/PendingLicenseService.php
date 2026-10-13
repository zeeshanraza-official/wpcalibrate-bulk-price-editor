<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Licensing;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Clean development adapter for pending licensing provider implementation.
 *
 * Explicitly displays "License integration not configured" without simulating fake activation.
 */
final class PendingLicenseService implements LicenseServiceInterface {

	public const OPTION_NAME = 'wpcalibrate_bpe_license_data';

	/**
	 * Retrieve stored license data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_license_data(): array {
		$data = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		return array_merge(
			array(
				'key'         => '',
				'status'      => 'unconfigured', // 'unconfigured', 'inactive', 'active', 'expired'
				'expires_at'  => '',
				'customer'    => '',
				'updated_at'  => '',
			),
			$data
		);
	}

	/**
	 * Save stored license key safely.
	 */
	public function save_license_key( string $key ): bool {
		$sanitized = sanitize_text_field( trim( $key ) );
		$data = $this->get_license_data();
		$data['key'] = $sanitized;
		$data['status'] = 'unconfigured';
		$data['updated_at'] = current_time( 'mysql', true );

		return update_option( self::OPTION_NAME, $data, false );
	}

	/**
	 * Attempt license activation.
	 *
	 * Provider is deliberately not simulated; returns unconfigured state cleanly.
	 */
	public function activate( string $key ): array {
		$this->save_license_key( $key );

		// As specified: Do not simulate success. Leave the external provider adapter explicitly unimplemented.
		return array(
			'success' => false,
			'status'  => 'unconfigured',
			'message' => __( 'License provider integration is not yet configured. Live licensing provider endpoints and credentials are required.', 'wpcalibrate-bulk-price-editor' ),
		);
	}

	/**
	 * Attempt license deactivation.
	 */
	public function deactivate(): array {
		$data = $this->get_license_data();
		$data['key'] = '';
		$data['status'] = 'unconfigured';
		update_option( self::OPTION_NAME, $data, false );

		return array(
			'success' => true,
			'status'  => 'unconfigured',
			'message' => __( 'License key cleared.', 'wpcalibrate-bulk-price-editor' ),
		);
	}

	/**
	 * Plugin is never marked active under the pending stub.
	 */
	public function is_active(): bool {
		return false;
	}

	/**
	 * Mask stored license key for safe display.
	 */
	public function get_masked_key(): string {
		$data = $this->get_license_data();
		$key  = (string) ( $data['key'] ?? '' );

		if ( empty( $key ) ) {
			return '';
		}

		$len = strlen( $key );
		if ( $len <= 8 ) {
			return str_repeat( '*', $len );
		}

		$start = substr( $key, 0, 4 );
		$end   = substr( $key, -4 );
		$mid   = str_repeat( '*', max( 4, $len - 8 ) );

		return "{$start}-{$mid}-{$end}";
	}
}
