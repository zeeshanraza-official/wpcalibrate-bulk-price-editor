<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Admin;

use WPCalibrate\BulkPriceEditor\Application\SettingsService;
use WPCalibrate\BulkPriceEditor\Licensing\PendingLicenseService;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Handles isolated WPCalibrate top-level menu and Bulk Price Editor submenu registration.
 */
final class AdminMenu {

	public const PARENT_SLUG = 'wpcalibrate';
	public const PAGE_SLUG   = 'wpcalibrate-bulk-price-editor';

	/**
	 * Register admin menu hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
	}

	/**
	 * Register menu items.
	 */
	public function register_menu(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		global $admin_page_hooks;

		// 1. Shared top-level WPCalibrate menu
		if ( ! isset( $admin_page_hooks[ self::PARENT_SLUG ] ) ) {
			add_menu_page(
				__( 'WPCalibrate', 'wpcalibrate-bulk-price-editor' ),
				'WPCalibrate',
				'manage_woocommerce',
				self::PARENT_SLUG,
				array( $this, 'render_plugin_admin' ),
				'none',
				58.6
			);
		}

		// 2. This plugin's submenu item
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Bulk Price Editor', 'wpcalibrate-bulk-price-editor' ),
			__( 'Bulk Price Editor', 'wpcalibrate-bulk-price-editor' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_plugin_admin' )
		);

		// Remove duplicate parent submenu if this plugin created it
		remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
	}

	/**
	 * Render the primary plugin administrative interface.
	 */
	public function render_plugin_admin(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpcalibrate-bulk-price-editor' ) );
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$allowed_tabs = array(
			'overview'    => __( 'Overview', 'wpcalibrate-bulk-price-editor' ),
			'bulk-editor' => __( 'Bulk Editor', 'wpcalibrate-bulk-price-editor' ),
			'scheduled'   => __( 'Scheduled Changes', 'wpcalibrate-bulk-price-editor' ),
			'history'     => __( 'History & Undo', 'wpcalibrate-bulk-price-editor' ),
			'settings'    => __( 'Settings', 'wpcalibrate-bulk-price-editor' ),
			'license'     => __( 'License', 'wpcalibrate-bulk-price-editor' ),
			'support'     => __( 'Support & Diagnostics', 'wpcalibrate-bulk-price-editor' ),
		);

		if ( ! array_key_exists( $current_tab, $allowed_tabs ) ) {
			$current_tab = 'overview';
		}

		// Security: restrict Settings and License tabs to administrators (manage_options)
		if ( in_array( $current_tab, array( 'settings', 'license' ), true ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage plugin settings or licensing.', 'wpcalibrate-bulk-price-editor' ) );
		}

		$license_service = new PendingLicenseService();

		require WPCALIBRATE_BPE_PATH . 'templates/admin/layout.php';
	}

	/**
	 * Handle native POST form submissions for Settings and License tabs.
	 */
	public function handle_form_submissions(): void {
		if ( ! isset( $_POST['wpcalibrate_bpe_action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['wpcalibrate_bpe_action'] ) );

		if ( 'save_settings' === $action ) {
			check_admin_referer( 'wpcalibrate_bpe_save_settings', 'wpcalibrate_bpe_settings_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Permission denied.', 'wpcalibrate-bulk-price-editor' ) );
			}

			$settings_data = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
			SettingsService::update_settings( $settings_data );

			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'settings', 'updated' => 'true' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'save_license' === $action ) {
			check_admin_referer( 'wpcalibrate_bpe_save_license', 'wpcalibrate_bpe_license_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Permission denied.', 'wpcalibrate-bulk-price-editor' ) );
			}

			$license_service = new PendingLicenseService();
			$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';

			if ( isset( $_POST['deactivate_license'] ) ) {
				$license_service->deactivate();
				wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'license', 'license_cleared' => 'true' ), admin_url( 'admin.php' ) ) );
				exit;
			}

			$result = $license_service->activate( $license_key );
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'license', 'license_notice' => rawurlencode( $result['message'] ) ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}
}
