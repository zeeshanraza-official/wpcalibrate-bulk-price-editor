<?php
/**
 * History & Undo Tab.
 *
 * @package WPCalibrate\BulkPriceEditor
 */

declare(strict_types=1);

use WPCalibrate\BulkPriceEditor\Database\JobRepository;
use WPCalibrate\BulkPriceEditor\Domain\JobStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$list = JobRepository::list_jobs( array(), $page, 20, 'id', 'DESC' );
?>

<div class="wpcalibrate-bpe-card">
	<div class="wpcalibrate-bpe-card-header wpcalibrate-bpe-card-header-flex">
		<div>
			<h2 class="wpcalibrate-bpe-card-title">
				<span class="dashicons dashicons-backup"></span> <?php esc_html_e( 'Operation History & Undo', 'wpcalibrate-bulk-price-editor' ); ?>
			</h2>
			<p class="wpcalibrate-bpe-card-desc"><?php esc_html_e( 'Inspect past bulk pricing operations, review results, and restore previous prices for eligible jobs.', 'wpcalibrate-bulk-price-editor' ); ?></p>
		</div>
	</div>

	<?php if ( empty( $list['jobs'] ) ) : ?>
		<div class="wpcalibrate-bpe-empty-state">
			<span class="dashicons dashicons-backup"></span>
			<h3><?php esc_html_e( 'No bulk pricing operations recorded yet.', 'wpcalibrate-bulk-price-editor' ); ?></h3>
			<p><?php esc_html_e( 'Completed operations and full undo history will appear here once executed.', 'wpcalibrate-bulk-price-editor' ); ?></p>
		</div>
	<?php else : ?>
		<div class="wpcalibrate-bpe-table-wrapper">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 70px;"><?php esc_html_e( 'Job ID', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th><?php esc_html_e( 'Date', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th><?php esc_html_e( 'Operation', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th><?php esc_html_e( 'Targets', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th><?php esc_html_e( 'Changed / Skipped / Failed', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th><?php esc_html_e( 'Initiated By', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th style="width: 220px;"><?php esc_html_e( 'Actions', 'wpcalibrate-bulk-price-editor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $list['jobs'] as $job ) : ?>
						<?php
						$creator = get_userdata( (int) $job['creator_id'] );
						$creator_name = $creator ? $creator->display_name : '#' . $job['creator_id'];
						$status_enum = JobStatus::tryFrom( $job['status'] );
						$date_display = $job['created_at'] ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $job['created_at'] ) ) : '&ndash;';
						$can_undo = $status_enum && $status_enum->can_undo() && 'restore' !== $job['job_type'];
						?>
						<tr id="history-row-<?php echo esc_attr( (string) $job['id'] ); ?>">
							<td><strong>#<?php echo esc_html( (string) $job['id'] ); ?></strong></td>
							<td><?php echo esc_html( $date_display ); ?></td>
							<td>
								<code><?php echo esc_html( str_replace( '_', ' ', $job['price_action'] ) ); ?></code>
								<?php if ( 'restore' === $job['job_type'] ) : ?>
									<span class="wpcalibrate-bpe-pill-restore"><?php esc_html_e( 'Restore Job', 'wpcalibrate-bulk-price-editor' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) $job['total_targets'] ); ?></td>
							<td>
								<span class="text-success"><?php echo esc_html( (string) $job['changed_count'] ); ?></span> /
								<span class="text-muted"><?php echo esc_html( (string) $job['skipped_count'] ); ?></span> /
								<span class="text-danger"><?php echo esc_html( (string) $job['failed_count'] ); ?></span>
							</td>
							<td>
								<span class="wpcalibrate-bpe-status-pill status-<?php echo esc_attr( $job['status'] ); ?>">
									<?php echo esc_html( $status_enum ? $status_enum->label() : $job['status'] ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $creator_name ); ?></td>
							<td class="wpcalibrate-bpe-actions-col">
								<?php if ( $can_undo ) : ?>
									<button type="button" class="button button-secondary btn-open-undo" data-job-id="<?php echo esc_attr( (string) $job['id'] ); ?>" title="<?php esc_attr_e( 'Restore captured prices', 'wpcalibrate-bulk-price-editor' ); ?>">
										<span class="dashicons dashicons-undo"></span> <?php esc_html_e( 'Undo', 'wpcalibrate-bulk-price-editor' ); ?>
									</button>
								<?php endif; ?>

								<?php if ( current_user_can( 'manage_options' ) ) : ?>
									<button type="button" class="button button-link-delete btn-delete-history" data-job-id="<?php echo esc_attr( (string) $job['id'] ); ?>" title="<?php esc_attr_e( 'Delete history log', 'wpcalibrate-bulk-price-editor' ); ?>">
										<span class="dashicons dashicons-trash"></span>
									</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<!-- Undo / Restore Confirmation & Conflict Modal -->
<div id="wpcalibrate-bpe-undo-modal" class="wpcalibrate-bpe-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modal-undo-title">
	<div class="wpcalibrate-bpe-modal-box wpcalibrate-bpe-modal-large">
		<header class="wpcalibrate-bpe-modal-header">
			<h3 id="modal-undo-title"><?php esc_html_e( 'Restore Previous Prices (Undo)', 'wpcalibrate-bulk-price-editor' ); ?></h3>
			<button type="button" class="wpcalibrate-bpe-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wpcalibrate-bulk-price-editor' ); ?>">&times;</button>
		</header>

		<div class="wpcalibrate-bpe-modal-body">
			<div class="wpcalibrate-bpe-undo-summary" id="undo-summary-banner">
				<p><?php esc_html_e( 'Checking conflict state between current catalog prices and captured previous prices...', 'wpcalibrate-bulk-price-editor' ); ?></p>
			</div>

			<div class="wpcalibrate-bpe-preview-badges" id="undo-badges" style="display:none;">
				<div class="wpcalibrate-bpe-badge-card badge-success">
					<span class="badge-num" id="undo-eligible-count">0</span>
					<span class="badge-label"><?php esc_html_e( 'Eligible to Restore', 'wpcalibrate-bulk-price-editor' ); ?></span>
				</div>
				<div class="wpcalibrate-bpe-badge-card badge-warning">
					<span class="badge-num" id="undo-conflict-count">0</span>
					<span class="badge-label"><?php esc_html_e( 'Newer Conflicts (Will Skip)', 'wpcalibrate-bulk-price-editor' ); ?></span>
				</div>
				<div class="wpcalibrate-bpe-badge-card badge-muted">
					<span class="badge-num" id="undo-missing-count">0</span>
					<span class="badge-label"><?php esc_html_e( 'Deleted Products', 'wpcalibrate-bulk-price-editor' ); ?></span>
				</div>
			</div>

			<div class="wpcalibrate-bpe-table-wrapper" id="undo-preview-table-wrapper" style="display:none;">
				<table class="wp-list-table widefat striped" id="undo-preview-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product', 'wpcalibrate-bulk-price-editor' ); ?></th>
							<th><?php esc_html_e( 'Current Price', 'wpcalibrate-bulk-price-editor' ); ?></th>
							<th><?php esc_html_e( 'Will Restore To', 'wpcalibrate-bulk-price-editor' ); ?></th>
							<th><?php esc_html_e( 'Safety State', 'wpcalibrate-bulk-price-editor' ); ?></th>
						</tr>
					</thead>
					<tbody id="undo-preview-table-body"></tbody>
				</table>
			</div>
		</div>

		<footer class="wpcalibrate-bpe-modal-footer">
			<button type="button" class="button button-secondary wpcalibrate-bpe-modal-close">
				<?php esc_html_e( 'Cancel', 'wpcalibrate-bulk-price-editor' ); ?>
			</button>
			<button type="button" class="button button-primary" id="btn-confirm-undo" disabled>
				<span class="dashicons dashicons-undo"></span> <?php esc_html_e( 'Confirm & Execute Restore', 'wpcalibrate-bulk-price-editor' ); ?>
			</button>
		</footer>
	</div>
</div>
