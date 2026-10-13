<?php
/**
 * License Management Tab.
 *
 * @package WPCalibrate\BulkPriceEditor
 */

declare(strict_types=1);

use WPCalibrate\BulkPriceEditor\Licensing\PendingLicenseService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$license_service = new PendingLicenseService();
$license_data    = $license_service->get_license_data();
$masked_key      = $license_service->get_masked_key();
$license_notice  = ! empty( $_GET['license_notice'] ) ? sanitize_text_field( rawurldecode( (string) $_GET['license_notice'] ) ) : '';
$is_cleared      = ! empty( $_GET['license_cleared'] );
?>

<div class="wpcalibrate-bpe-card">
	<div class="wpcalibrate-bpe-card-header">
		<h2 class="wpcalibrate-bpe-card-title">
			<span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Commercial License Management', 'wpcalibrate-bulk-price-editor' ); ?>
		</h2>
		<p class="wpcalibrate-bpe-card-desc"><?php esc_html_e( 'Manage your WPCalibrate license key for automatic plugin updates and commercial support.', 'wpcalibrate-bulk-price-editor' ); ?></p>
	</div>

	<?php if ( $license_notice ) : ?>
		<div class="notice notice-info is-dismissible" style="margin: 0 0 20px 0;">
			<p><strong><?php echo esc_html( $license_notice ); ?></strong></p>
		</div>
	<?php endif; ?>

	<?php if ( $is_cleared ) : ?>
		<div class="notice notice-success is-dismissible" style="margin: 0 0 20px 0;">
			<p><strong><?php esc_html_e( 'License key deactivated and cleared.', 'wpcalibrate-bulk-price-editor' ); ?></strong></p>
		</div>
	<?php endif; ?>

	<div class="wpcalibrate-bpe-license-status-box">
		<div class="status-indicator">
			<span class="dashicons dashicons-info"></span>
			<div>
				<strong><?php esc_html_e( 'Integration Status:', 'wpcalibrate-bulk-price-editor' ); ?></strong>
				<span class="wpcalibrate-bpe-pill-unconfigured"><?php esc_html_e( 'License integration not configured (Development Boundary)', 'wpcalibrate-bulk-price-editor' ); ?></span>
			</div>
		</div>
		<p class="description" style="margin-top: 10px;">
			<?php esc_html_e( 'As specified in the architecture, the external license provider API endpoints and credentials are pending official supply. No simulated or fake validation is performed.', 'wpcalibrate-bulk-price-editor' ); ?>
		</p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-top: 25px;">
		<input type="hidden" name="wpcalibrate_bpe_action" value="save_license" />
		<?php wp_nonce_field( 'wpcalibrate_bpe_save_license', 'wpcalibrate_bpe_license_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="license_key"><?php esc_html_e( 'License Key', 'wpcalibrate-bulk-price-editor' ); ?></label>
					</th>
					<td>
						<?php if ( ! empty( $masked_key ) ) : ?>
							<div class="wpcalibrate-bpe-masked-key-row">
								<code class="wpcalibrate-bpe-key-display"><?php echo esc_html( $masked_key ); ?></code>
								<span class="description"><?php esc_html_e( '(Masked for security)', 'wpcalibrate-bulk-price-editor' ); ?></span>
							</div>
						<?php endif; ?>

						<input type="password" id="license_key" name="license_key" value="" placeholder="<?php esc_attr_e( 'Paste new license key...', 'wpcalibrate-bulk-price-editor' ); ?>" class="regular-text" autocomplete="off" />
						<p class="description">
							<?php
							printf(
								/* translators: %s: Marketplace URL */
								esc_html__( 'Enter your commercial key from your purchase receipt. Need a license? Visit the %s.', 'wpcalibrate-bulk-price-editor' ),
								'<a href="https://marketplace.wpcalibrate.com/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'WPCalibrate Marketplace', 'wpcalibrate-bulk-price-editor' ) . '</a>'
							);
							?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Save & Activate License', 'wpcalibrate-bulk-price-editor' ); ?>
			</button>

			<?php if ( ! empty( $license_data['key'] ) ) : ?>
				<button type="submit" name="deactivate_license" value="1" class="button button-secondary" style="margin-inline-start: 10px;">
					<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Deactivate License', 'wpcalibrate-bulk-price-editor' ); ?>
				</button>
			<?php endif; ?>
		</p>
	</form>
</div>
