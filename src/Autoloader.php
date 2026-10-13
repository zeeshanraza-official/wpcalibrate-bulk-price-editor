<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * High-performance PSR-4 Autoloader for WPCalibrate Bulk Price Editor.
 */
final class Autoloader {

	/**
	 * Namespace prefix.
	 */
	private const PREFIX = 'WPCalibrate\\BulkPriceEditor\\';

	/**
	 * Base directory for the namespace prefix.
	 */
	private string $base_dir;

	/**
	 * Constructor.
	 *
	 * @param string $base_dir Base directory pointing to src/.
	 */
	public function __construct( string $base_dir ) {
		$this->base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;
	}

	/**
	 * Register the autoloader with SPL.
	 */
	public function register(): void {
		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Autoload callback.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public function autoload( string $class ): void {
		if ( ! str_starts_with( $class, self::PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( self::PREFIX ) );
		$file = $this->base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
