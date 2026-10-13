<?php
/**
 * Bulk Price Editor Primary Workflow Tab.
 *
 * @package WPCalibrate\BulkPriceEditor
 */

declare(strict_types=1);

use WPCalibrate\BulkPriceEditor\Application\FilterService;
use WPCalibrate\BulkPriceEditor\Domain\PriceAction;
use WPCalibrate\BulkPriceEditor\Domain\RoundingMode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$categories = FilterService::get_categories();
$tags       = FilterService::get_tags();
$currency   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
?>

<div class="wpcalibrate-bpe-workflow-container">

	<!-- STEP 1 & 2: FILTERS & SEARCH -->
	<section class="wpcalibrate-bpe-card wpcalibrate-bpe-filter-card" aria-labelledby="filter-section-title">
		<div class="wpcalibrate-bpe-card-header">
			<h2 id="filter-section-title" class="wpcalibrate-bpe-card-title">
				<span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Find & Filter Products', 'wpcalibrate-bulk-price-editor' ); ?>
			</h2>
			<p class="wpcalibrate-bpe-card-desc"><?php esc_html_e( 'Filter simple products, variable products, and individual variations to target for price adjustment.', 'wpcalibrate-bulk-price-editor' ); ?></p>
		</div>

		<form id="wpcalibrate-bpe-filter-form" class="wpcalibrate-bpe-filter-grid">
			<div class="wpcalibrate-bpe-field">
				<label for="filter-search"><?php esc_html_e( 'Search Title / SKU', 'wpcalibrate-bulk-price-editor' ); ?></label>
				<input type="text" id="filter-search" name="search" placeholder="<?php esc_attr_e( 'Search by product title or SKU...', 'wpcalibrate-bulk-price-editor' ); ?>" class="regular-text" />
			</div>

			<div class="wpcalibrate-bpe-field">
				<label for="filter-type"><?php esc_html_e( 'Product Type', 'wpcalibrate-bulk-price-editor' ); ?></label>
				<select id="filter-type" name="product_type">
					<option value="all"><?php esc_html_e( 'All Supported (Simple & Variations)', 'wpcalibrate-bulk-price-editor' ); ?></option>
					<option value="simple"><?php esc_html_e( 'Simple Products Only', 'wpcalibrate-bulk-price-editor' ); ?></option>
					<option value="variation"><?php esc_html_e( 'Individual Variations Only', 'wpcalibrate-bulk-price-editor' ); ?></option>
				</select>
			</div>

			<div class="wpcalibrate-bpe-field">
				<label for="filter-category"><?php esc_html_e( 'Category', 'wpcalibrate-bulk-price-editor' ); ?></label>
				<select id="filter-category" name="category_id">
					<option value=""><?php esc_html_e( 'All Categories', 'wpcalibrate-bulk-price-editor' ); ?></option>
					<?php foreach ( $categories as $cat_id => $cat_name ) : ?>
						<option value="<?php echo esc_attr( (string) $cat_id ); ?>"><?php echo esc_html( $cat_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="wpcalibrate-bpe-field">
				<label for="filter-tag"><?php esc_html_e( 'Tag', 'wpcalibrate-bulk-price-editor' ); ?></label>
				<select id="filter-tag" name="tag_id">
					<option value=""><?php esc_html_e( 'All Tags', 'wpcalibrate-bulk-price-editor' ); ?></option>
					<?php foreach ( $tags as $tag_id => $tag_name ) : ?>
						<option value="<?php echo esc_attr( (string) $tag_id ); ?>"><?php echo esc_html( $tag_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="wpcalibrate-bpe-field">
				<label for="filter-stock"><?php esc_html_e( 'Stock Status', 'wpcalibrate-bulk-price-editor' ); ?></label>
				<select id="filter-stock" name="stock_status">
					<option value=""><?php esc_html_e( 'Any Stock Status', 'wpcalibrate-bulk-price-editor' ); ?></option>
					<option value="instock"><?php esc_html_e( 'In Stock', 'wpcalibrate-bulk-price-editor' ); ?></option>
					<option value="outofstock"><?php esc_html_e( 'Out of Stock', 'wpcalibrate-bulk-price-editor' ); ?></option>
					<option value="onbackorder"><?php esc_html_e( 'On Backorder', 'wpcalibrate-bulk-price-editor' ); ?></option>
				</select>
			</div>

			<div class="wpcalibrate-bpe-field wpcalibrate-bpe-price-range">
				<label><?php esc_html_e( 'Regular Price Range', 'wpcalibrate-bulk-price-editor' ); ?> (<?php echo esc_html( $currency ); ?>)</label>
				<div class="wpcalibrate-bpe-range-inputs">
					<input type="number" step="0.01" min="0" id="filter-min-regular" name="min_regular" placeholder="<?php esc_attr_e( 'Min', 'wpcalibrate-bulk-price-editor' ); ?>" />
					<span>&ndash;</span>
					<input type="number" step="0.01" min="0" id="filter-max-regular" name="max_regular" placeholder="<?php esc_attr_e( 'Max', 'wpcalibrate-bulk-price-editor' ); ?>" />
				</div>
			</div>

			<div class="wpcalibrate-bpe-field wpcalibrate-bpe-price-range">
				<label><?php esc_html_e( 'Sale Price Range', 'wpcalibrate-bulk-price-editor' ); ?> (<?php echo esc_html( $currency ); ?>)</label>
				<div class="wpcalibrate-bpe-range-inputs">
					<input type="number" step="0.01" min="0" id="filter-min-sale" name="min_sale" placeholder="<?php esc_attr_e( 'Min', 'wpcalibrate-bulk-price-editor' ); ?>" />
					<span>&ndash;</span>
					<input type="number" step="0.01" min="0" id="filter-max-sale" name="max_sale" placeholder="<?php esc_attr_e( 'Max', 'wpcalibrate-bulk-price-editor' ); ?>" />
				</div>
			</div>

			<div class="wpcalibrate-bpe-field wpcalibrate-bpe-actions-cell">
				<label class="screen-reader-text"><?php esc_html_e( 'Filter Actions', 'wpcalibrate-bulk-price-editor' ); ?></label>
				<button type="submit" class="button button-primary" id="btn-apply-filters">
					<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Filter Products', 'wpcalibrate-bulk-price-editor' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="btn-reset-filters">
					<?php esc_html_e( 'Reset Filters', 'wpcalibrate-bulk-price-editor' ); ?>
				</button>
			</div>
		</form>
	</section>

	<!-- STEP 3: PRODUCT SELECTION TABLE -->
	<section class="wpcalibrate-bpe-card" aria-labelledby="select-section-title">
		<div class="wpcalibrate-bpe-card-header wpcalibrate-bpe-card-header-flex">
			<div>
				<h2 id="select-section-title" class="wpcalibrate-bpe-card-title">
					<span class="dashicons dashicons-products"></span> <?php esc_html_e( 'Select Products & Variations', 'wpcalibrate-bulk-price-editor' ); ?>
				</h2>
				<span class="wpcalibrate-bpe-counter-badge" id="selection-counter-badge">
					<?php esc_html_e( '0 selected', 'wpcalibrate-bulk-price-editor' ); ?>
				</span>
			</div>
			<div class="wpcalibrate-bpe-table-controls">
				<span id="results-count-summary" class="wpcalibrate-bpe-results-summary"><?php esc_html_e( 'Loading...', 'wpcalibrate-bulk-price-editor' ); ?></span>
			</div>
		</div>

		<!-- Select All Across Catalog Notice Banner -->
		<div id="select-all-catalog-banner" class="wpcalibrate-bpe-notice-banner" style="display:none;" role="alert">
			<span id="select-all-catalog-text"></span>
			<button type="button" class="button-link" id="btn-select-all-catalog">
				<?php esc_html_e( 'Select all matching products across entire store', 'wpcalibrate-bulk-price-editor' ); ?>
			</button>
			<button type="button" class="button-link" id="btn-clear-selection" style="display:none; margin-inline-start: 12px;">
				<?php esc_html_e( 'Clear Selection', 'wpcalibrate-bulk-price-editor' ); ?>
			</button>
		</div>

		<div class="wpcalibrate-bpe-table-wrapper">
			<table class="wp-list-table widefat fixed striped wpcalibrate-bpe-table" id="products-table">
				<thead>
					<tr>
						<td id="cb" class="manage-column column-cb check-column">
							<input type="checkbox" id="cb-select-all" aria-label="<?php esc_attr_e( 'Select all rows on this page', 'wpcalibrate-bulk-price-editor' ); ?>" />
						</td>
						<th scope="col" class="column-title"><?php esc_html_e( 'Product / Variation', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th scope="col" class="column-sku"><?php esc_html_e( 'SKU', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th scope="col" class="column-type"><?php esc_html_e( 'Type', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th scope="col" class="column-price"><?php esc_html_e( 'Regular Price', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th scope="col" class="column-price"><?php esc_html_e( 'Sale Price', 'wpcalibrate-bulk-price-editor' ); ?></th>
						<th scope="col" class="column-stock"><?php esc_html_e( 'Stock', 'wpcalibrate-bulk-price-editor' ); ?></th>
					</tr>
				</thead>
				<tbody id="products-table-body">
					<tr>
						<td colspan="7" class="wpcalibrate-bpe-loading-cell">
							<span class="spinner is-active" style="float:none;"></span> <?php esc_html_e( 'Loading catalog products...', 'wpcalibrate-bulk-price-editor' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="wpcalibrate-bpe-pagination" id="table-pagination">
			<!-- Populated via bulk-editor.js -->
		</div>
	</section>

	<!-- STEP 4 & 5: PRICE ACTION & CONFIGURATION -->
	<section class="wpcalibrate-bpe-card" aria-labelledby="action-section-title">
		<div class="wpcalibrate-bpe-card-header">
			<h2 id="action-section-title" class="wpcalibrate-bpe-card-title">
				<span class="dashicons dashicons-calculator"></span> <?php esc_html_e( 'Price Action & Rules', 'wpcalibrate-bulk-price-editor' ); ?>
			</h2>
			<p class="wpcalibrate-bpe-card-desc"><?php esc_html_e( 'Configure the price modification formula, rounding rules, and execution schedule.', 'wpcalibrate-bulk-price-editor' ); ?></p>
		</div>

		<div class="wpcalibrate-bpe-config-grid">
			<div class="wpcalibrate-bpe-field">
				<label for="price-action-select"><strong><?php esc_html_e( 'Price Operation', 'wpcalibrate-bulk-price-editor' ); ?></strong></label>
				<select id="price-action-select" name="action_type">
					<?php foreach ( PriceAction::cases() as $action ) : ?>
						<option value="<?php echo esc_attr( $action->value ); ?>">
							<?php echo esc_html( $action->label() ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="wpcalibrate-bpe-field" id="field-operation-value">
				<label for="operation-value"><strong><?php esc_html_e( 'Value', 'wpcalibrate-bulk-price-editor' ); ?></strong></label>
				<div class="wpcalibrate-bpe-input-adornment">
					<input type="number" step="0.01" min="0" id="operation-value" name="value" placeholder="0.00" class="regular-text" />
					<span id="value-unit-label" class="wpcalibrate-bpe-unit"><?php echo esc_html( $currency ); ?></span>
				</div>
			</div>

			<div class="wpcalibrate-bpe-field">
				<label for="rounding-mode-select"><strong><?php esc_html_e( 'Rounding Mode', 'wpcalibrate-bulk-price-editor' ); ?></strong></label>
				<select id="rounding-mode-select" name="rounding_mode">
					<?php foreach ( RoundingMode::cases() as $rm ) : ?>
						<option value="<?php echo esc_attr( $rm->value ); ?>">
							<?php echo esc_html( $rm->label() ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<!-- Scheduling Section -->
		<div class="wpcalibrate-bpe-schedule-box">
			<div class="wpcalibrate-bpe-field-checkbox">
				<label>
					<input type="checkbox" id="toggle-schedule" />
					<strong><?php esc_html_e( 'Schedule for future date/time instead of applying now', 'wpcalibrate-bulk-price-editor' ); ?></strong>
				</label>
			</div>

			<div id="schedule-fields" class="wpcalibrate-bpe-schedule-fields" style="display:none;">
				<div class="wpcalibrate-bpe-field">
					<label for="schedule-datetime">
						<?php esc_html_e( 'Execution Date & Time', 'wpcalibrate-bulk-price-editor' ); ?>
						<span class="wpcalibrate-bpe-tz-note">(<?php echo esc_html( wp_timezone_string() ); ?>)</span>
					</label>
					<input type="datetime-local" id="schedule-datetime" name="scheduled_at" />
				</div>

				<div class="wpcalibrate-bpe-field">
					<label for="auto-restore-datetime">
						<?php esc_html_e( 'Optional Automatic Restore Date & Time', 'wpcalibrate-bulk-price-editor' ); ?>
						<span class="wpcalibrate-bpe-tz-note">(<?php echo esc_html( wp_timezone_string() ); ?>)</span>
					</label>
					<input type="datetime-local" id="auto-restore-datetime" name="auto_restore_at" />
					<p class="description"><?php esc_html_e( 'Automatically reverts to previous prices at this time. Captured pre-change values are restored safely.', 'wpcalibrate-bulk-price-editor' ); ?></p>
				</div>
			</div>
		</div>

		<div class="wpcalibrate-bpe-action-footer">
			<button type="button" class="button button-primary button-hero" id="btn-open-preview">
				<span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Calculate & Preview Changes', 'wpcalibrate-bulk-price-editor' ); ?>
			</button>
		</div>
	</section>
</div>

<!-- STEP 6 & 7: PREVIEW & CONFIRMATION MODAL -->
<div id="wpcalibrate-bpe-preview-modal" class="wpcalibrate-bpe-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modal-preview-title">
	<div class="wpcalibrate-bpe-modal-box wpcalibrate-bpe-modal-large">
		<header class="wpcalibrate-bpe-modal-header">
			<h3 id="modal-preview-title"><?php esc_html_e( 'Preview Price Changes & Safety Check', 'wpcalibrate-bulk-price-editor' ); ?></h3>
			<button type="button" class="wpcalibrate-bpe-modal-close" aria-label="<?php esc_attr_e( 'Close preview', 'wpcalibrate-bulk-price-editor' ); ?>">&times;</button>
		</header>

		<div class="wpcalibrate-bpe-modal-body">
			<!-- Summary Counter Badges -->
			<div class="wpcalibrate-bpe-preview-badges" id="preview-badges">
				<div class="wpcalibrate-bpe-badge-card">
					<span class="badge-num" id="badge-total">0</span>
					<span class="badge-label"><?php esc_html_e( 'Total Targets', 'wpcalibrate-bulk-price-editor' ); ?></span>
				</div>
				<div class="wpcalibrate-bpe-badge-card badge-success">
					<span class="badge-num" id="badge-changing">0</span>
					<span class="badge-label"><?php esc_html_e( 'Changing', 'wpcalibrate-bulk-price-editor' ); ?></span>
				</div>
				<div class="wpcalibrate-bpe-badge-card badge-muted">
					<span class="badge-num" id="badge-unchanged">0</span>
					<span class="badge-label"><?php esc_html_e( 'Unchanged', 'wpcalibrate-bulk-price-editor' ); ?></span>
				</div>
				<div class="wpcalibrate-bpe-badge-card badge-warning">
					<span class="badge-num" id="badge-warnings">0</span>
					<span class="badge-label"><?php esc_html_e( 'Warnings', 'wpcalibrate-bulk-price-editor' ); ?></span>
				</div>
			</div>

			<!-- Preview Comparison Table -->
			<div class="wpcalibrate-bpe-table-wrapper wpcalibrate-bpe-preview-table-wrapper">
				<table class="wp-list-table widefat striped" id="preview-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product / Variation', 'wpcalibrate-bulk-price-editor' ); ?></th>
							<th><?php esc_html_e( 'SKU', 'wpcalibrate-bulk-price-editor' ); ?></th>
							<th><?php esc_html_e( 'Regular Price', 'wpcalibrate-bulk-price-editor' ); ?></th>
							<th><?php esc_html_e( 'Sale Price', 'wpcalibrate-bulk-price-editor' ); ?></th>
							<th><?php esc_html_e( 'Difference', 'wpcalibrate-bulk-price-editor' ); ?></th>
							<th><?php esc_html_e( 'Status / Notice', 'wpcalibrate-bulk-price-editor' ); ?></th>
						</tr>
					</thead>
					<tbody id="preview-table-body">
						<!-- Populated via AJAX -->
					</tbody>
				</table>
			</div>

			<div class="wpcalibrate-bpe-pagination" id="preview-pagination"></div>
		</div>

		<footer class="wpcalibrate-bpe-modal-footer">
			<button type="button" class="button button-secondary wpcalibrate-bpe-modal-close">
				<?php esc_html_e( 'Back to Edit', 'wpcalibrate-bulk-price-editor' ); ?>
			</button>
			<button type="button" class="button button-primary button-large" id="btn-confirm-execute">
				<span class="dashicons dashicons-yes-alt"></span> <span id="confirm-execute-label"><?php esc_html_e( 'Confirm & Apply Prices Now', 'wpcalibrate-bulk-price-editor' ); ?></span>
			</button>
		</footer>
	</div>
</div>

<!-- STEP 8 & 9: PROCESS & LIVE PROGRESS MODAL -->
<div id="wpcalibrate-bpe-progress-modal" class="wpcalibrate-bpe-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modal-progress-title">
	<div class="wpcalibrate-bpe-modal-box">
		<header class="wpcalibrate-bpe-modal-header">
			<h3 id="modal-progress-title"><?php esc_html_e( 'Applying Bulk Price Changes...', 'wpcalibrate-bulk-price-editor' ); ?></h3>
		</header>

		<div class="wpcalibrate-bpe-modal-body">
			<div class="wpcalibrate-bpe-progress-container">
				<div class="wpcalibrate-bpe-progress-bar-bg">
					<div class="wpcalibrate-bpe-progress-bar-fill" id="job-progress-bar" style="width: 0%;"></div>
				</div>
				<div class="wpcalibrate-bpe-progress-percent" id="job-progress-percent">0%</div>
			</div>

			<div class="wpcalibrate-bpe-progress-stats">
				<div class="wpcalibrate-bpe-stat-item">
					<span class="stat-label"><?php esc_html_e( 'Processed:', 'wpcalibrate-bulk-price-editor' ); ?></span>
					<span class="stat-val" id="stat-processed">0 / 0</span>
				</div>
				<div class="wpcalibrate-bpe-stat-item">
					<span class="stat-label"><?php esc_html_e( 'Changed:', 'wpcalibrate-bulk-price-editor' ); ?></span>
					<span class="stat-val stat-success" id="stat-changed">0</span>
				</div>
				<div class="wpcalibrate-bpe-stat-item">
					<span class="stat-label"><?php esc_html_e( 'Skipped (Conflicts):', 'wpcalibrate-bulk-price-editor' ); ?></span>
					<span class="stat-val stat-muted" id="stat-skipped">0</span>
				</div>
				<div class="wpcalibrate-bpe-stat-item">
					<span class="stat-label"><?php esc_html_e( 'Failed:', 'wpcalibrate-bulk-price-editor' ); ?></span>
					<span class="stat-val stat-danger" id="stat-failed">0</span>
				</div>
			</div>

			<div id="progress-completion-message" class="wpcalibrate-bpe-complete-banner" style="display:none;">
				<span class="dashicons dashicons-yes-alt"></span>
				<strong><?php esc_html_e( 'Job successfully finished!', 'wpcalibrate-bulk-price-editor' ); ?></strong>
			</div>
		</div>

		<footer class="wpcalibrate-bpe-modal-footer">
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wpcalibrate-bulk-price-editor', 'tab' => 'history' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary" id="btn-view-history" style="display:none;">
				<?php esc_html_e( 'View History & Details', 'wpcalibrate-bulk-price-editor' ); ?>
			</a>
			<button type="button" class="button button-secondary" id="btn-close-progress" style="display:none;">
				<?php esc_html_e( 'Close', 'wpcalibrate-bulk-price-editor' ); ?>
			</button>
		</footer>
	</div>
</div>
