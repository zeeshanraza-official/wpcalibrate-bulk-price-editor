<?php
/**
 * Overview Tab - Purpose, Method, and How to Use.
 *
 * @package WPCalibrate\BulkPriceEditor
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bulk_editor_url = add_query_arg(
	array(
		'page' => 'wpcalibrate-bulk-price-editor',
		'tab'  => 'bulk-editor',
	),
	admin_url( 'admin.php' )
);
?>

<div class="wpcalibrate-bpe-overview-container">

	<!-- HERO PURPOSE SECTION -->
	<section class="wpcalibrate-bpe-card wpcalibrate-bpe-overview-hero">
		<div class="wpcalibrate-bpe-hero-content">
			<div class="wpcalibrate-bpe-hero-badge">
				<span class="dashicons dashicons-shield-alt"></span>
				<?php esc_html_e( 'Enterprise-Grade Catalog Pricing Engine', 'wpcalibrate-bulk-price-editor' ); ?>
			</div>
			<h2 class="wpcalibrate-bpe-hero-title"><?php esc_html_e( 'Purpose of WPCalibrate Bulk Price Editor', 'wpcalibrate-bulk-price-editor' ); ?></h2>
			<p class="wpcalibrate-bpe-hero-lead">
				<?php esc_html_e( 'WPCalibrate Bulk Price Editor is a focused WooCommerce pricing management system built for store administrators and authorized shop managers. It provides mathematical precision, deterministic preview verification, background queueing for massive catalogs, and safe non-destructive undo capability without risking database corruption or server timeouts.', 'wpcalibrate-bulk-price-editor' ); ?>
			</p>
			<div class="wpcalibrate-bpe-hero-actions">
				<a href="<?php echo esc_url( $bulk_editor_url ); ?>" class="button button-primary button-hero">
					<span class="dashicons dashicons-calculator"></span>
					<?php esc_html_e( 'Open Bulk Price Editor', 'wpcalibrate-bulk-price-editor' ); ?> &rarr;
				</a>
			</div>
		</div>
	</section>

	<!-- HOW TO USE: THE 9-STEP WORKFLOW -->
	<section class="wpcalibrate-bpe-card">
		<div class="wpcalibrate-bpe-card-header">
			<h2 class="wpcalibrate-bpe-card-title">
				<span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'How to Use: The 9-Step Guided Workflow', 'wpcalibrate-bulk-price-editor' ); ?>
			</h2>
			<p class="wpcalibrate-bpe-card-desc"><?php esc_html_e( 'Follow this systematic procedure to safely adjust prices across simple products and variations.', 'wpcalibrate-bulk-price-editor' ); ?></p>
		</div>

		<div class="wpcalibrate-bpe-steps-grid">
			<div class="wpcalibrate-bpe-step-card">
				<div class="step-number">1</div>
				<div class="step-content">
					<h3><?php esc_html_e( 'Find & Filter', 'wpcalibrate-bulk-price-editor' ); ?></h3>
					<p><?php esc_html_e( 'Filter products by title keyword, SKU, categories, tags, product type (simple or variations), stock status, and regular/sale price boundaries.', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>

			<div class="wpcalibrate-bpe-step-card">
				<div class="step-number">2</div>
				<div class="step-content">
					<h3><?php esc_html_e( 'Select Targets', 'wpcalibrate-bulk-price-editor' ); ?></h3>
					<p><?php esc_html_e( 'Select individual products or variations, select all items on the current page, or select all matching items across your entire catalog with one click.', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>

			<div class="wpcalibrate-bpe-step-card">
				<div class="step-number">3</div>
				<div class="step-content">
					<h3><?php esc_html_e( 'Choose Price Action', 'wpcalibrate-bulk-price-editor' ); ?></h3>
					<p><?php esc_html_e( 'Select from 11 pricing operations: exact assignment, fixed-amount increase/decrease, percentage increase/decrease, or clear sale price.', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>

			<div class="wpcalibrate-bpe-step-card">
				<div class="step-number">4</div>
				<div class="step-content">
					<h3><?php esc_html_e( 'Configure Rounding', 'wpcalibrate-bulk-price-editor' ); ?></h3>
					<p><?php esc_html_e( 'Choose retail rounding rules: store precision, nearest whole, round up, round down, or psychological retail endings (.99, .95, or .49).', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>

			<div class="wpcalibrate-bpe-step-card">
				<div class="step-number">5</div>
				<div class="step-content">
					<h3><?php esc_html_e( 'Schedule or Apply Now', 'wpcalibrate-bulk-price-editor' ); ?></h3>
					<p><?php esc_html_e( 'Run immediately or schedule a future execution time in your WordPress site timezone. Optionally define an automatic restore date/time.', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>

			<div class="wpcalibrate-bpe-step-card">
				<div class="step-number">6</div>
				<div class="step-content">
					<h3><?php esc_html_e( 'Review Safety Preview', 'wpcalibrate-bulk-price-editor' ); ?></h3>
					<p><?php esc_html_e( 'Inspect calculated regular and sale prices, differences, percentages, and WooCommerce warnings before any database mutation occurs.', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>

			<div class="wpcalibrate-bpe-step-card">
				<div class="step-number">7</div>
				<div class="step-content">
					<h3><?php esc_html_e( 'Explicit Confirmation', 'wpcalibrate-bulk-price-editor' ); ?></h3>
					<p><?php esc_html_e( 'A tamper-proof fingerprint verifies that the operation parameters match your review. Confirm with a single explicit action.', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>

			<div class="wpcalibrate-bpe-step-card">
				<div class="step-number">8</div>
				<div class="step-content">
					<h3><?php esc_html_e( 'Background Queueing', 'wpcalibrate-bulk-price-editor' ); ?></h3>
					<p><?php esc_html_e( 'Jobs process through WooCommerce Action Scheduler in bounded batches. Worker idempotency ensures retried batches never duplicate changes.', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>

			<div class="wpcalibrate-bpe-step-card">
				<div class="step-number">9</div>
				<div class="step-content">
					<h3><?php esc_html_e( 'Results & One-Click Undo', 'wpcalibrate-bulk-price-editor' ); ?></h3>
					<p><?php esc_html_e( 'Track live progress counters and review full audit history. Revert prior prices safely with conflict protection.', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<!-- METHOD & SAFETY ARCHITECTURE -->
	<section class="wpcalibrate-bpe-card">
		<div class="wpcalibrate-bpe-card-header">
			<h2 class="wpcalibrate-bpe-card-title">
				<span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Method & Safety Architecture', 'wpcalibrate-bulk-price-editor' ); ?>
			</h2>
			<p class="wpcalibrate-bpe-card-desc"><?php esc_html_e( 'Engineered to adhere to strict WooCommerce data integrity and reliability standards.', 'wpcalibrate-bulk-price-editor' ); ?></p>
		</div>

		<div class="wpcalibrate-bpe-methods-grid">
			<div class="wpcalibrate-bpe-method-item">
				<div class="method-icon"><span class="dashicons dashicons-randomize"></span></div>
				<div class="method-body">
					<h4><?php esc_html_e( 'Shared Pricing Calculation Engine', 'wpcalibrate-bulk-price-editor' ); ?></h4>
					<p><?php esc_html_e( 'Preview calculations and worker execution use the exact same calculation service. Mathematical precision is strictly preserved without floating-point errors.', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>

			<div class="wpcalibrate-bpe-method-item">
				<div class="method-icon"><span class="dashicons dashicons-lock"></span></div>
				<div class="method-body">
					<h4><?php esc_html_e( 'Race Conflict Detection', 'wpcalibrate-bulk-price-editor' ); ?></h4>
					<p><?php esc_html_e( 'If a product price is manually edited or modified by another process between preview and execution, the worker detects the checksum mismatch, skips the target, and logs a clear notice.', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>

			<div class="wpcalibrate-bpe-method-item">
				<div class="method-icon"><span class="dashicons dashicons-update"></span></div>
				<div class="method-body">
					<h4><?php esc_html_e( 'Worker Idempotency & Action Scheduler', 'wpcalibrate-bulk-price-editor' ); ?></h4>
					<p><?php esc_html_e( 'Large catalog jobs run in bounded batches. Per-target item status tracking and process locking guarantee that worker retries never apply relative percentage calculations twice.', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>

			<div class="wpcalibrate-bpe-method-item">
				<div class="method-icon"><span class="dashicons dashicons-undo"></span></div>
				<div class="method-body">
					<h4><?php esc_html_e( 'Conflict-Safe Restore (Undo)', 'wpcalibrate-bulk-price-editor' ); ?></h4>
					<p><?php esc_html_e( 'Undo restores captured pre-change price snapshots rather than trying to reverse mathematical formulas. If a product was repriced after the job completed, the conflict is flagged and preserved.', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>

			<div class="wpcalibrate-bpe-method-item">
				<div class="method-icon"><span class="dashicons dashicons-database"></span></div>
				<div class="method-body">
					<h4><?php esc_html_e( 'WooCommerce CRUD & Variable Sync', 'wpcalibrate-bulk-price-editor' ); ?></h4>
					<p><?php esc_html_e( 'All updates use WooCommerce CRUD APIs (WC_Product setters and save). Modifying variation prices automatically synchronizes parent variable price ranges and clears catalog transients.', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>

			<div class="wpcalibrate-bpe-method-item">
				<div class="method-icon"><span class="dashicons dashicons-performance"></span></div>
				<div class="method-body">
					<h4><?php esc_html_e( 'High-Performance Order Storage (HPOS) & Zero Frontend Overhead', 'wpcalibrate-bulk-price-editor' ); ?></h4>
					<p><?php esc_html_e( 'Fully certified for WooCommerce HPOS and Block-based Checkout. Absolutely zero scripts or styles load on customer storefront pages.', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>
		</div>
	</section>

</div>
