<?php
/**
 * Scheduled Changes Tab.
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
$list = JobRepository::list_jobs(
	array( 'status' => JobStatus::SCHEDULED->value ),
	$page,
	20,
	'scheduled_at',
	'ASC'
);
?>

<div class="wpcalibrate-bpe-card">
	<div class="wpcalibrate-bpe-card-header wpcalibrate-bpe-card-header-flex">
		<div>
			<h2 class="wpcalibrate-bpe-card-title">
				<span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Scheduled Future Price Changes', 'wpcalibrate-bulk-price-editor' ); ?>
			</h2>
			<p class="wpcalibrate-bpe-card-desc">
				<?php
				printf(
					/* translators: %s: Current site timezone */
					esc_html__( 'All times are scheduled and displayed in the site timezone: %s. Timing depends on Action Scheduler / WP-Cron.', 'wpcalibrate-bulk-price-editor' ),
					'<strong>' . esc_html( wp_timezone_string() ) . '</strong>'
				);
				?>
			</p>
		</div>
	</div>

	<?php if ( empty( $list['jobs'] ) ) : ?>
		<div class="wpcalibrate-bpe-empty-state">
			<span class="dashicons dashicons-calendar"></span>
			<h3><?php esc_html_e( 'No scheduled price changes found.', 'wpcalibrate-bulk-price-editor' ); ?></h3>
			<p><?php esc_html_e( 'When you configure a bulk price edit and choose to schedule it for a future date, it will appear here.', 'wpcalibrate-bulk-price-editor' ); ?></p>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wpcalibrate-bulk-price-editor', 'tab' => 'bulk-editor' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Schedule a Price Change', 'wpcalibrate-bulk-price-editor' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="wpcalibrate-bpe-table-wrapper">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Job ID', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th><?php esc_html_e( 'Scheduled Time', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th><?php esc_html_e( 'Operation', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th><?php esc_html_e( 'Value', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th><?php esc_html_e( 'Targets', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th><?php esc_html_e( 'Auto-Restore', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th><?php esc_html_e( 'Created By', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'wpcalibrate-bulk-price-editor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $list['jobs'] as $job ) : ?>
						<?php
						$creator = get_userdata( (int) $job['creator_id'] );
						$creator_name = $creator ? $creator->display_name : '#' . $job['creator_id'];

						// Format scheduled datetime in site timezone
						$scheduled_display = $job['scheduled_at'] ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $job['scheduled_at'] ) ) : '&ndash;';
						$restore_display   = $job['auto_restore_at'] ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $job['auto_restore_at'] ) ) : __( 'None', 'wpcalibrate-bulk-price-editor' );
						?>
						<tr id="job-row-<?php echo esc_attr( (string) $job['id'] ); ?>">
							<td><strong>#<?php echo esc_html( (string) $job['id'] ); ?></strong></td>
							<td><?php echo esc_html( $scheduled_display ); ?></td>
							<td><code><?php echo esc_html( str_replace( '_', ' ', $job['price_action'] ) ); ?></code></td>
							<td><?php echo esc_html( (string) $job['price_value'] ); ?></td>
							<td><?php echo esc_html( (string) $job['total_targets'] ); ?></td>
							<td><?php echo esc_html( $restore_display ); ?></td>
							<td><?php echo esc_html( $creator_name ); ?></td>
							<td>
								<button type="button" class="button button-secondary btn-cancel-job" data-job-id="<?php echo esc_attr( (string) $job['id'] ); ?>">
									<span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Cancel', 'wpcalibrate-bulk-price-editor' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
