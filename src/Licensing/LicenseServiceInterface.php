<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Licensing;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Interface contract for commercial license management provider integration.
 */
interface LicenseServiceInterface {

	/**
	 * Retrieve current stored license data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_license_data(): array;

	/**
	 * Save license key.
	 *
	 * @param string $key Plain license key string.
	 * @return bool
	 */
	public function save_license_key( string $key ): bool;

	/**
	 * Attempt license activation.
	 *
	 * @param string $key License key.
	 * @return array{success: bool, status: string, message: string}
	 */
	public function activate( string $key ): array;

	/**
	 * Attempt license deactivation.
	 *
	 * @return array{success: bool, status: string, message: string}
	 */
	public function deactivate(): array;

	/**
	 * Check if plugin currently holds an active verified license.
	 */
	public function is_active(): bool;

	/**
	 * Get masked license key for safe UI display.
	 */
	public function get_masked_key(): string;
}
