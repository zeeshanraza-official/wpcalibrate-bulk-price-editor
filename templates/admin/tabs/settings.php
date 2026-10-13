<?php
/**
 * Settings Tab.
 *
 * @package WPCalibrate\BulkPriceEditor
 */

declare(strict_types=1);

use WPCalibrate\BulkPriceEditor\Application\SettingsService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = SettingsService::get_settings();
$is_updated = ! empty( $_GET['updated'] );
?>

<div class="wpcalibrate-bpe-card">
	<div class="wpcalibrate-bpe-card-header">
		<h2 class="wpcalibrate-bpe-card-title">
			<span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Plugin Settings & Preferences', 'wpcalibrate-bulk-price-editor' ); ?>
		</h2>
		<p class="wpcalibrate-bpe-card-desc"><?php esc_html_e( 'Configure background processing performance, history retention limits, and diagnostic logs.', 'wpcalibrate-bulk-price-editor' ); ?></p>
	</div>

	<?php if ( $is_updated ) : ?>
		<div class="notice notice-success is-dismissible" style="margin: 0 0 20px 0;">
			<p><strong><?php esc_html_e( 'Settings successfully saved.', 'wpcalibrate-bulk-price-editor' ); ?></strong></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="wpcalibrate_bpe_action" value="save_settings" />
		<?php wp_nonce_field( 'wpcalibrate_bpe_save_settings', 'wpcalibrate_bpe_settings_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<!-- SECTION 1: PROCESSING -->
				<tr>
					<th scope="row">
						<label for="setting-batch-size"><?php esc_html_e( 'Batch Processing Size', 'wpcalibrate-bulk-price-editor' ); ?></label>
					</th>
					<td>
						<input type="number" min="5" max="500" step="5" id="setting-batch-size" name="settings[batch_size]" value="<?php echo esc_attr( (string) $settings['batch_size'] ); ?>" class="small-text" />
						<p class="description">
							<?php esc_html_e( 'Number of products or variations processed per Action Scheduler chunk. Default is 50. Lower values reduce memory consumption on constrained hosting.', 'wpcalibrate-bulk-price-editor' ); ?>
						</p>
					</td>
				</tr>

				<!-- SECTION 2: RETENTION -->
				<tr>
					<th scope="row">
						<label for="setting-retention-days"><?php esc_html_e( 'History Retention (Days)', 'wpcalibrate-bulk-price-editor' ); ?></label>
					</th>
					<td>
						<input type="number" min="1" max="365" step="1" id="setting-retention-days" name="settings[retention_days]" value="<?php echo esc_attr( (string) $settings['retention_days'] ); ?>" class="small-text" />
						<p class="description">
							<?php esc_html_e( 'Days to retain completed job records. Default is 30 days. Note: purging history records permanently removes the ability to undo those operations.', 'wpcalibrate-bulk-price-editor' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="setting-retention-max-jobs"><?php esc_html_e( 'Maximum History Records', 'wpcalibrate-bulk-price-editor' ); ?></label>
					</th>
					<td>
						<input type="number" min="5" max="1000" step="5" id="setting-retention-max-jobs" name="settings[retention_max_jobs]" value="<?php echo esc_attr( (string) $settings['retention_max_jobs'] ); ?>" class="small-text" />
						<p class="description">
							<?php esc_html_e( 'Maximum number of completed jobs kept in history before older records are asynchronously cleaned up. Default is 50.', 'wpcalibrate-bulk-price-editor' ); ?>
						</p>
					</td>
				</tr>

				<!-- SECTION 3: LOGGING -->
				<tr>
					<th scope="row">
						<label for="setting-log-level"><?php esc_html_e( 'Diagnostic Logging', 'wpcalibrate-bulk-price-editor' ); ?></label>
					</th>
					<td>
						<select id="setting-log-level" name="settings[log_level]">
							<option value="none" <?php selected( $settings['log_level'], 'none' ); ?>><?php esc_html_e( 'None (Disabled)', 'wpcalibrate-bulk-price-editor' ); ?></option>
							<option value="errors" <?php selected( $settings['log_level'], 'errors' ); ?>><?php esc_html_e( 'Errors & Warnings Only (Recommended)', 'wpcalibrate-bulk-price-editor' ); ?></option>
							<option value="debug" <?php selected( $settings['log_level'], 'debug' ); ?>><?php esc_html_e( 'Verbose Debug Logging', 'wpcalibrate-bulk-price-editor' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Logged directly to WooCommerce Logger under source "wpcalibrate-bulk-price-editor". Sensitive customer data and license keys are masked.', 'wpcalibrate-bulk-price-editor' ); ?>
						</p>
					</td>
				</tr>

				<!-- SECTION 4: UNINSTALL -->
				<tr>
					<th scope="row"><?php esc_html_e( 'Data Removal on Uninstall', 'wpcalibrate-bulk-price-editor' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php esc_html_e( 'Data Removal on Uninstall', 'wpcalibrate-bulk-price-editor' ); ?></span></legend>
							<label for="setting-delete-uninstall">
								<input type="checkbox" id="setting-delete-uninstall" name="settings[delete_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?> />
								<strong><?php esc_html_e( 'Delete plugin settings, custom tables, and job history when uninstalling.', 'wpcalibrate-bulk-price-editor' ); ?></strong>
							</label>
							<p class="description">
								<?php esc_html_e( 'Default is OFF. If enabled, uninstalling the plugin will drop custom job tables and remove plugin options. WooCommerce products and catalog prices are NEVER reverted or altered on uninstall.', 'wpcalibrate-bulk-price-editor' ); ?>
							</p>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary button-large">
				<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Settings', 'wpcalibrate-bulk-price-editor' ); ?>
			</button>
		</p>
	</form>
</div>
