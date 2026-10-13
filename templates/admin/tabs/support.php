<?php
/**
 * Support & Diagnostics Tab.
 *
 * @package WPCalibrate\BulkPriceEditor
 */

declare(strict_types=1);

use WPCalibrate\BulkPriceEditor\Application\DiagnosticsService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$diagnostics = DiagnosticsService::get_diagnostics();
$report_text = DiagnosticsService::get_formatted_text_report();
?>

<div class="wpcalibrate-bpe-grid-two-col">

	<!-- CONTACT & SUPPORT INFORMATION -->
	<div class="wpcalibrate-bpe-card">
		<div class="wpcalibrate-bpe-card-header">
			<h2 class="wpcalibrate-bpe-card-title">
				<span class="dashicons dashicons-sos"></span> <?php esc_html_e( 'Official WPCalibrate Support', 'wpcalibrate-bulk-price-editor' ); ?>
			</h2>
			<p class="wpcalibrate-bpe-card-desc"><?php esc_html_e( 'Get help with questions, bulk pricing workflows, or extension compatibility.', 'wpcalibrate-bulk-price-editor' ); ?></p>
		</div>

		<ul class="wpcalibrate-bpe-contact-list">
			<li>
				<span class="dashicons dashicons-email-alt"></span>
				<strong><?php esc_html_e( 'Email Support:', 'wpcalibrate-bulk-price-editor' ); ?></strong>
				<a href="mailto:support@wpcalibrate.com">support@wpcalibrate.com</a>
			</li>
			<li>
				<span class="dashicons dashicons-admin-site-alt3"></span>
				<strong><?php esc_html_e( 'Official Website:', 'wpcalibrate-bulk-price-editor' ); ?></strong>
				<a href="https://wpcalibrate.com" target="_blank" rel="noopener noreferrer">https://wpcalibrate.com</a>
			</li>
			<li>
				<span class="dashicons dashicons-cart"></span>
				<strong><?php esc_html_e( 'Marketplace:', 'wpcalibrate-bulk-price-editor' ); ?></strong>
				<a href="https://marketplace.wpcalibrate.com/" target="_blank" rel="noopener noreferrer">https://marketplace.wpcalibrate.com/</a>
			</li>
			<li>
				<span class="dashicons dashicons-phone"></span>
				<strong><?php esc_html_e( 'Phone / WhatsApp:', 'wpcalibrate-bulk-price-editor' ); ?></strong>
				<a href="https://wa.me/447474795976" target="_blank" rel="noopener noreferrer">+447474795976</a>
			</li>
		</ul>

		<div class="wpcalibrate-bpe-privacy-badge">
			<span class="dashicons dashicons-shield"></span>
			<p>
				<strong><?php esc_html_e( 'Privacy Guarantee:', 'wpcalibrate-bulk-price-editor' ); ?></strong>
				<?php esc_html_e( 'This plugin runs entirely on your server. No customer data, order information, or catalog telemetry is collected or transmitted to WPCalibrate or third parties.', 'wpcalibrate-bulk-price-editor' ); ?>
			</p>
		</div>
	</div>

	<!-- ENVIRONMENT STATUS & DIAGNOSTICS -->
	<div class="wpcalibrate-bpe-card">
		<div class="wpcalibrate-bpe-card-header wpcalibrate-bpe-card-header-flex">
			<div>
				<h2 class="wpcalibrate-bpe-card-title">
					<span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'System Diagnostics', 'wpcalibrate-bulk-price-editor' ); ?>
				</h2>
				<p class="wpcalibrate-bpe-card-desc"><?php esc_html_e( 'Non-sensitive technical environment specifications useful when requesting support.', 'wpcalibrate-bulk-price-editor' ); ?></p>
			</div>
			<div>
				<button type="button" class="button button-secondary" id="btn-copy-diagnostics">
					<span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copy Report', 'wpcalibrate-bulk-price-editor' ); ?>
				</button>
			</div>
		</div>

		<table class="widefat striped wpcalibrate-bpe-diag-table">
			<tbody>
				<?php foreach ( $diagnostics as $key => $val ) : ?>
					<tr>
						<th><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></th>
						<td><code><?php echo esc_html( (string) $val ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<textarea id="diag-copy-textarea" class="screen-reader-text" readonly><?php echo esc_textarea( $report_text ); ?></textarea>
		<div id="diag-copy-notice" style="display:none; margin-top: 10px; color: #155724; font-weight: 600;">
			<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'System diagnostics report copied to clipboard!', 'wpcalibrate-bulk-price-editor' ); ?>
		</div>
	</div>

</div>
