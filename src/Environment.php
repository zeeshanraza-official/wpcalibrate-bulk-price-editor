<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Checks system requirements and environment dependencies.
 */
final class Environment {

	public const MIN_PHP_VERSION = '8.2';
	public const MIN_WP_VERSION  = '6.4';
	public const MIN_WC_VERSION  = '8.0';

	/**
	 * Check if PHP meets minimum requirement.
	 */
	public static function is_php_compatible(): bool {
		return version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '>=' );
	}

	/**
	 * Check if WordPress meets minimum requirement.
	 */
	public static function is_wp_compatible(): bool {
		global $wp_version;
		if ( ! isset( $wp_version ) ) {
			return true;
		}
		return version_compare( $wp_version, self::MIN_WP_VERSION, '>=' );
	}

	/**
	 * Check if WooCommerce is active.
	 */
	public static function is_woocommerce_active(): bool {
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}

		if ( function_exists( 'is_plugin_active' ) ) {
			return is_plugin_active( 'woocommerce/woocommerce.php' );
		}

		// Fallback check in active_plugins option
		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) ) {
			return true;
		}

		if ( is_multisite() ) {
			$active_sitewide_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
			if ( isset( $active_sitewide_plugins['woocommerce/woocommerce.php'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if WooCommerce version meets minimum requirement.
	 */
	public static function is_wc_version_compatible(): bool {
		if ( ! defined( 'WC_VERSION' ) ) {
			return true;
		}
		return version_compare( WC_VERSION, self::MIN_WC_VERSION, '>=' );
	}

	/**
	 * Check if entire environment meets requirements.
	 */
	public static function is_compatible(): bool {
		return self::is_php_compatible() && self::is_wp_compatible() && self::is_woocommerce_active() && self::is_wc_version_compatible();
	}

	/**
	 * Render admin notices for unmet dependencies.
	 */
	public static function render_dependency_notices(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! self::is_php_compatible() ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php echo esc_html__( 'WPCalibrate Bulk Price Editor for WooCommerce', 'wpcalibrate-bulk-price-editor' ); ?>:</strong>
					<?php
					printf(
						/* translators: 1: Required PHP version, 2: Current PHP version */
						esc_html__( 'This plugin requires PHP %1$s or higher. Your server is running PHP %2$s. Please upgrade your PHP version.', 'wpcalibrate-bulk-price-editor' ),
						esc_html( self::MIN_PHP_VERSION ),
						esc_html( PHP_VERSION )
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( ! self::is_woocommerce_active() ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php echo esc_html__( 'WPCalibrate Bulk Price Editor for WooCommerce', 'wpcalibrate-bulk-price-editor' ); ?>:</strong>
					<?php esc_html_e( 'WooCommerce is required for this plugin to function. Please install and activate WooCommerce.', 'wpcalibrate-bulk-price-editor' ); ?>
				</p>
			</div>
			<?php
		} elseif ( ! self::is_wc_version_compatible() && defined( 'WC_VERSION' ) ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php echo esc_html__( 'WPCalibrate Bulk Price Editor for WooCommerce', 'wpcalibrate-bulk-price-editor' ); ?>:</strong>
					<?php
					printf(
						/* translators: 1: Required WooCommerce version, 2: Current WooCommerce version */
						esc_html__( 'This plugin requires WooCommerce %1$s or higher. Your site is running WooCommerce %2$s. Please update WooCommerce.', 'wpcalibrate-bulk-price-editor' ),
						esc_html( self::MIN_WC_VERSION ),
						esc_html( WC_VERSION )
					);
					?>
				</p>
			</div>
			<?php
		}
	}
}
