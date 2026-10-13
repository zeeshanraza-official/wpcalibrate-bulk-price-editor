<?php
/**
 * Plugin Name: WPCalibrate Bulk Price Editor for WooCommerce
 * Plugin URI: https://marketplace.wpcalibrate.com/
 * Description: High-performance, safe, and scheduled bulk price management for WooCommerce products and variations with instant preview and full undo history.
 * Version: 1.0.0
 * Author: WPCalibrate
 * Author URI: https://wpcalibrate.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpcalibrate-bulk-price-editor
 * Domain Path: /languages
 * Requires at least: 6.4
 * Tested up to: 7.1.3
 * Requires PHP: 8.2
 * WC requires at least: 8.0
 * WC tested up to: 11.2.1
 * Update URI: https://github.com/zeeshanraza-official/wpcalibrate-bulk-price-editor
 * GitHub Plugin URI: zeeshanraza-official/wpcalibrate-bulk-price-editor
 * GitHub Branch: main
 *
 * @package WPCalibrate\BulkPriceEditor
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin Constants
define( 'WPCALIBRATE_BPE_VERSION', '1.0.0' );
define( 'WPCALIBRATE_BPE_FILE', __FILE__ );
define( 'WPCALIBRATE_BPE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPCALIBRATE_BPE_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCALIBRATE_BPE_BASENAME', plugin_basename( __FILE__ ) );

// Register High-Performance PSR-4 Autoloader
require_once WPCALIBRATE_BPE_PATH . 'src/Autoloader.php';
$wpcalibrate_bpe_autoloader = new \WPCalibrate\BulkPriceEditor\Autoloader( WPCALIBRATE_BPE_PATH . 'src' );
$wpcalibrate_bpe_autoloader->register();

// Lifecycle Hooks
register_activation_hook( WPCALIBRATE_BPE_FILE, array( \WPCalibrate\BulkPriceEditor\Activator::class, 'activate' ) );
register_deactivation_hook( WPCALIBRATE_BPE_FILE, array( \WPCalibrate\BulkPriceEditor\Deactivator::class, 'deactivate' ) );

// Launch Plugin
add_action(
	'plugins_loaded',
	function () {
		\WPCalibrate\BulkPriceEditor\Plugin::get_instance()->run();
	},
	5
);
