<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor;

use WPCalibrate\BulkPriceEditor\Admin\AdminMenu;
use WPCalibrate\BulkPriceEditor\Admin\Assets;
use WPCalibrate\BulkPriceEditor\Admin\AjaxController;
use WPCalibrate\BulkPriceEditor\Application\RetentionService;
use WPCalibrate\BulkPriceEditor\Database\MigrationManager;
use WPCalibrate\BulkPriceEditor\Jobs\ActionSchedulerManager;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Main Plugin Orchestrator and Lifecycle Manager.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private AdminMenu $admin_menu;
	private Assets $assets;
	private AjaxController $ajax_controller;

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->admin_menu       = new AdminMenu();
		$this->assets           = new Assets();
		$this->ajax_controller  = new AjaxController();
	}

	/**
	 * Bootstrap and initialize plugin hooks.
	 */
	public function run(): void {
		// 1. Declare WooCommerce HPOS and Blocks Compatibility
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );

		// 2. Load translations
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// 3. Check runtime environment dependencies
		if ( ! Environment::is_compatible() ) {
			add_action( 'admin_notices', array( Environment::class, 'render_dependency_notices' ) );
			// Fail gracefully: do not initialize WooCommerce dependent services
			return;
		}

		// 4. Automatic DB migrations if needed
		add_action( 'plugins_loaded', array( MigrationManager::class, 'maybe_migrate' ), 10 );

		// 5. Initialize Action Scheduler Hooks & Maintenance
		ActionSchedulerManager::init();
		RetentionService::init();

		// 6. Admin Interface, Endpoints & In-Dashboard GitHub Updates
		if ( is_admin() ) {
			$this->admin_menu->init();
			$this->assets->init();
			$this->ajax_controller->init();

			$updater = new \WPCalibrate\BulkPriceEditor\Support\GitHubUpdater(
				'zeeshanraza-official',
				'wpcalibrate-bulk-price-editor',
				WPCALIBRATE_BPE_FILE,
				WPCALIBRATE_BPE_VERSION
			);
			$updater->init();
		}
	}

	/**
	 * Declare WooCommerce High-Performance Order Storage (HPOS) and Cart/Checkout Blocks compatibility.
	 */
	public function declare_compatibility(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				WPCALIBRATE_BPE_FILE,
				true
			);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				WPCALIBRATE_BPE_FILE,
				true
			);
		}
	}

	/**
	 * Load plugin internationalization text domain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wpcalibrate-bulk-price-editor',
			false,
			dirname( plugin_basename( WPCALIBRATE_BPE_FILE ) ) . '/languages'
		);
	}
}
