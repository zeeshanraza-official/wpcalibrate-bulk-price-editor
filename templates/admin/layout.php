<?php
/**
 * Main administrative layout wrapper.
 *
 * @package WPCalibrate\BulkPriceEditor
 */

declare(strict_types=1);

use WPCalibrate\BulkPriceEditor\Admin\AdminMenu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$icon_dark_url = WPCALIBRATE_BPE_URL . 'branding/icon-dark.png';
?>
<div class="wrap wpcalibrate-bpe-wrap">
	<header class="wpcalibrate-bpe-header">
		<div class="wpcalibrate-bpe-header-brand">
			<img src="<?php echo esc_url( $icon_dark_url ); ?>" alt="WPCalibrate" class="wpcalibrate-bpe-logo" width="36" height="36" />
			<div class="wpcalibrate-bpe-header-title">
				<h1><?php esc_html_e( 'WPCalibrate Bulk Price Editor', 'wpcalibrate-bulk-price-editor' ); ?></h1>
				<span class="wpcalibrate-bpe-version">v<?php echo esc_html( WPCALIBRATE_BPE_VERSION ); ?></span>
			</div>
		</div>
		<div class="wpcalibrate-bpe-header-meta">
			<span class="wpcalibrate-bpe-tz-pill" title="<?php esc_attr_e( 'Site Timezone', 'wpcalibrate-bulk-price-editor' ); ?>">
				<span class="dashicons dashicons-clock"></span> <?php echo esc_html( wp_timezone_string() ); ?>
			</span>
		</div>
	</header>

	<nav class="nav-tab-wrapper wpcalibrate-bpe-nav-tabs" aria-label="<?php esc_attr_e( 'Plugin navigation tabs', 'wpcalibrate-bulk-price-editor' ); ?>">
		<?php foreach ( $allowed_tabs as $tab_key => $tab_title ) : ?>
			<?php
			$tab_url = add_query_arg(
				array(
					'page' => AdminMenu::PAGE_SLUG,
					'tab'  => $tab_key,
				),
				admin_url( 'admin.php' )
			);
			$is_active = ( $current_tab === $tab_key );
			?>
			<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>" <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
				<?php echo esc_html( $tab_title ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<main class="wpcalibrate-bpe-tab-content" id="wpcalibrate-bpe-main">
		<?php
		$tab_file = WPCALIBRATE_BPE_PATH . 'templates/admin/tabs/' . $current_tab . '.php';
		if ( file_exists( $tab_file ) ) {
			require $tab_file;
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Tab template not found.', 'wpcalibrate-bulk-price-editor' ) . '</p></div>';
		}
		?>
	</main>
</div>
