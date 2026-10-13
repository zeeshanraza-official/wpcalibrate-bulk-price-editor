<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Admin;

use WPCalibrate\BulkPriceEditor\Application\FilterService;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Handles asset enqueueing strictly on plugin admin screens.
 */
final class Assets {

	/**
	 * Register asset hooks.
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_head', array( $this, 'enqueue_admin_menu_styles' ) );
	}

	/**
	 * Output inline CSS in admin_head ensuring sidebar menu icon is perfectly sized (20x20)
	 * and matches WordPress dashboard icon aesthetics on all wp-admin screens.
	 */
	public function enqueue_admin_menu_styles(): void {
		$icon_url = esc_url( WPCALIBRATE_BPE_URL . 'branding/icon-white.png' );
		?>
		<style id="wpcalibrate-bpe-menu-icon-style">
			#adminmenu #toplevel_page_wpcalibrate div.wp-menu-image,
			#adminmenu .toplevel_page_wpcalibrate div.wp-menu-image {
				display: flex !important;
				align-items: center !important;
				justify-content: center !important;
			}
			#adminmenu #toplevel_page_wpcalibrate div.wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate div.wp-menu-image img {
				display: none !important;
			}
			#adminmenu #toplevel_page_wpcalibrate div.wp-menu-image:before,
			#adminmenu .toplevel_page_wpcalibrate div.wp-menu-image:before {
				content: '' !important;
				display: inline-block !important;
				width: 20px !important;
				height: 20px !important;
				background-color: currentColor !important;
				-webkit-mask-image: url('<?php echo $icon_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>') !important;
				mask-image: url('<?php echo $icon_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>') !important;
				-webkit-mask-size: 18px 18px !important;
				mask-size: 18px 18px !important;
				-webkit-mask-repeat: no-repeat !important;
				mask-repeat: no-repeat !important;
				-webkit-mask-position: center !important;
				mask-position: center !important;
				vertical-align: middle !important;
				padding: 0 !important;
				margin: 0 !important;
				opacity: 0.8 !important;
				transition: opacity 0.15s ease-in-out !important;
			}
			#adminmenu #toplevel_page_wpcalibrate:hover div.wp-menu-image:before,
			#adminmenu #toplevel_page_wpcalibrate.wp-has-current-submenu div.wp-menu-image:before,
			#adminmenu #toplevel_page_wpcalibrate.current div.wp-menu-image:before,
			#adminmenu .toplevel_page_wpcalibrate:hover div.wp-menu-image:before,
			#adminmenu .toplevel_page_wpcalibrate.wp-has-current-submenu div.wp-menu-image:before,
			#adminmenu .toplevel_page_wpcalibrate.current div.wp-menu-image:before {
				opacity: 1 !important;
			}
		</style>
		<?php
	}

	/**
	 * Enqueue styles and scripts only on this plugin's screens.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		// Strict check: only enqueue on this plugin's pages
		if ( ! str_contains( $hook_suffix, AdminMenu::PAGE_SLUG ) && ! str_contains( $hook_suffix, AdminMenu::PARENT_SLUG ) ) {
			return;
		}

		// CSS
		wp_enqueue_style(
			'wpcalibrate-bpe-admin',
			WPCALIBRATE_BPE_URL . 'assets/css/admin.css',
			array(),
			WPCALIBRATE_BPE_VERSION
		);

		if ( is_rtl() ) {
			wp_enqueue_style(
				'wpcalibrate-bpe-admin-rtl',
				WPCALIBRATE_BPE_URL . 'assets/css/admin-rtl.css',
				array( 'wpcalibrate-bpe-admin' ),
				WPCALIBRATE_BPE_VERSION
			);
		}

		// JS
		wp_enqueue_script(
			'wpcalibrate-bpe-bulk-editor',
			WPCALIBRATE_BPE_URL . 'assets/js/bulk-editor.js',
			array( 'jquery' ),
			WPCALIBRATE_BPE_VERSION,
			true
		);

		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';

		wp_localize_script(
			'wpcalibrate-bpe-bulk-editor',
			'wpcalibrateBpeData',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'wpcalibrate_bpe_nonce' ),
				'currency_symbol' => $currency_symbol,
				'decimals'        => $decimals,
				'timezone'        => wp_timezone_string(),
				'strings'         => array(
					'confirm_apply'        => __( 'Are you sure you want to apply this bulk price modification? All selected products and variations will be queued for processing.', 'wpcalibrate-bulk-price-editor' ),
					'confirm_undo'         => __( 'Are you sure you want to restore original prices for this job? Newer conflicting prices will be preserved.', 'wpcalibrate-bulk-price-editor' ),
					'confirm_cancel'       => __( 'Are you sure you want to cancel this scheduled job?', 'wpcalibrate-bulk-price-editor' ),
					'select_products_warn' => __( 'Please select at least one product or variation to proceed.', 'wpcalibrate-bulk-price-editor' ),
					'enter_value_warn'     => __( 'Please enter a valid numeric value for the price calculation.', 'wpcalibrate-bulk-price-editor' ),
					'processing'           => __( 'Processing...', 'wpcalibrate-bulk-price-editor' ),
					'completed'            => __( 'Completed', 'wpcalibrate-bulk-price-editor' ),
					'completed_with_errors'=> __( 'Completed with Errors', 'wpcalibrate-bulk-price-editor' ),
					'failed'               => __( 'Failed', 'wpcalibrate-bulk-price-editor' ),
					'conflict_detected'    => __( 'Conflict Detected', 'wpcalibrate-bulk-price-editor' ),
					'select_all_prompt'    => __( 'Select all matching products across all pages', 'wpcalibrate-bulk-price-editor' ),
				),
			)
		);
	}
}
