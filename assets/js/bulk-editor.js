/**
 * WPCalibrate Bulk Price Editor - Admin Client Workflow Engine
 */

(function($) {
	'use strict';

	const app = {
		selectedIds: new Set(),
		selectAllMatching: false,
		totalMatching: 0,
		currentPage: 1,
		perPage: 20,
		currentCriteria: {},
		currentFingerprint: '',
		activeJobId: null,
		pollTimer: null,

		init() {
			this.bindEvents();
			if ($('#products-table').length) {
				this.fetchTargets(1);
			}
		},

		bindEvents() {
			const self = this;

			// 1. Filter Form Submit & Reset
			$('#wpcalibrate-bpe-filter-form').on('submit', function(e) {
				e.preventDefault();
				self.selectedIds.clear();
				self.selectAllMatching = false;
				self.updateSelectionUI();
				self.fetchTargets(1);
			});

			$('#btn-reset-filters').on('click', function() {
				$('#wpcalibrate-bpe-filter-form')[0].reset();
				self.selectedIds.clear();
				self.selectAllMatching = false;
				self.updateSelectionUI();
				self.fetchTargets(1);
			});

			// 2. Select All Checkbox on Page
			$('#cb-select-all').on('change', function() {
				const checked = $(this).is(':checked');
				$('.cb-product-row').each(function() {
					$(this).prop('checked', checked);
					const id = parseInt($(this).val(), 10);
					if (checked) {
						self.selectedIds.add(id);
					} else {
						self.selectedIds.delete(id);
					}
				});
				self.updateSelectionUI();
			});

			// 3. Row-level Checkboxes
			$(document).on('change', '.cb-product-row', function() {
				const id = parseInt($(this).val(), 10);
				if ($(this).is(':checked')) {
					self.selectedIds.add(id);
				} else {
					self.selectedIds.delete(id);
					self.selectAllMatching = false;
				}
				self.updateSelectionUI();
			});

			// 4. Select All Across Catalog Banner Button
			$('#btn-select-all-catalog').on('click', function() {
				self.selectAllMatching = true;
				self.updateSelectionUI();
			});

			$('#btn-clear-selection').on('click', function() {
				self.selectedIds.clear();
				self.selectAllMatching = false;
				$('.cb-product-row, #cb-select-all').prop('checked', false);
				self.updateSelectionUI();
			});

			// 5. Price Action Change (Adornment & Value Input)
			$('#price-action-select').on('change', function() {
				const action = $(this).val();
				const $valField = $('#field-operation-value');
				const $unit = $('#value-unit-label');

				if (action === 'clear_sale_price') {
					$valField.hide();
				} else {
					$valField.show();
					if (action.includes('percent')) {
						$unit.text('%');
					} else {
						$unit.text(wpcalibrateBpeData.currency_symbol || '$');
					}
				}
			}).trigger('change');

			// 6. Schedule Checkbox Toggle
			$('#toggle-schedule').on('change', function() {
				if ($(this).is(':checked')) {
					$('#schedule-fields').slideDown(200);
					$('#confirm-execute-label').text('Confirm & Schedule Changes');
				} else {
					$('#schedule-fields').slideUp(200);
					$('#confirm-execute-label').text('Confirm & Apply Prices Now');
				}
			});

			// 7. Preview Button -> Open Preview Modal
			$('#btn-open-preview').on('click', function() {
				self.openPreview();
			});

			// 8. Confirm & Execute Button
			$('#btn-confirm-execute').on('click', function() {
				self.executeJob();
			});

			// 9. Modal Close Buttons
			$('.wpcalibrate-bpe-modal-close, #btn-close-progress').on('click', function() {
				$('.wpcalibrate-bpe-modal-overlay').hide();
			});

			// 10. History: Cancel Scheduled Job
			$(document).on('click', '.btn-cancel-job', function() {
				const jobId = $(this).data('job-id');
				if (confirm(wpcalibrateBpeData.strings.confirm_cancel)) {
					self.cancelJob(jobId);
				}
			});

			// 11. History: Open Undo Modal
			$(document).on('click', '.btn-open-undo', function() {
				const jobId = $(this).data('job-id');
				self.openUndoPreview(jobId);
			});

			// 12. History: Confirm Undo Execution
			$('#btn-confirm-undo').on('click', function() {
				const jobId = $(this).data('job-id');
				self.executeUndo(jobId);
			});

			// 13. History: Delete History Record
			$(document).on('click', '.btn-delete-history', function() {
				const jobId = $(this).data('job-id');
				if (confirm('Delete this history record? Note: You will not be able to undo this operation.')) {
					self.deleteHistory(jobId);
				}
			});

			// 14. Support: Copy Diagnostics
			$('#btn-copy-diagnostics').on('click', function() {
				const text = $('#diag-copy-textarea').val();
				navigator.clipboard.writeText(text).then(function() {
					$('#diag-copy-notice').fadeIn().delay(3000).fadeOut();
				});
			});
		},

		getFilterCriteria() {
			return {
				search: $('#filter-search').val(),
				product_type: $('#filter-type').val(),
				category_id: $('#filter-category').val(),
				tag_id: $('#filter-tag').val(),
				stock_status: $('#filter-stock').val(),
				min_regular: $('#filter-min-regular').val(),
				max_regular: $('#filter-max-regular').val(),
				min_sale: $('#filter-min-sale').val(),
				max_sale: $('#filter-max-sale').val(),
			};
		},

		fetchTargets(page = 1) {
			const self = this;
			self.currentPage = page;
			self.currentCriteria = self.getFilterCriteria();

			const $body = $('#products-table-body');
			$body.html('<tr><td colspan="7" class="wpcalibrate-bpe-loading-cell"><span class="spinner is-active" style="float:none;"></span> Loading products...</td></tr>');

			$.ajax({
				url: wpcalibrateBpeData.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wpcalibrate_bpe_get_targets',
					nonce: wpcalibrateBpeData.nonce,
					page: page,
					per_page: self.perPage,
					...self.currentCriteria,
				},
				success(res) {
					if (!res.success) {
						$body.html('<tr><td colspan="7" class="wpcalibrate-bpe-empty-cell">' + (res.data.message || 'Error loading targets.') + '</td></tr>');
						return;
					}

					self.totalMatching = res.data.total;
					self.renderTargetsTable(res.data.targets);
					self.renderPagination(res.data.page, res.data.total_pages, res.data.total);
					self.updateSelectionUI();
				},
				error() {
					$body.html('<tr><td colspan="7" class="wpcalibrate-bpe-empty-cell">Network error occurred while fetching catalog items.</td></tr>');
				}
			});
		},

		renderTargetsTable(targets) {
			const self = this;
			const $body = $('#products-table-body');
			$body.empty();

			if (!targets || !targets.length) {
				$body.html('<tr><td colspan="7" class="wpcalibrate-bpe-empty-cell">No products found matching the specified filters.</td></tr>');
				return;
			}

			const currency = wpcalibrateBpeData.currency_symbol || '$';

			targets.forEach(item => {
				const isChecked = self.selectAllMatching || self.selectedIds.has(item.id);
				const isVariation = item.type === 'variation';

				let titleHtml = `<strong>${self.escapeHtml(item.title)}</strong>`;
				if (isVariation) {
					titleHtml = `<div><strong>${self.escapeHtml(item.title)}</strong></div>`;
					if (item.parent_title) {
						titleHtml += `<span class="wpcalibrate-bpe-variation-indicator">Parent: ${self.escapeHtml(item.parent_title)}</span>`;
					}
					if (item.attributes_summary) {
						titleHtml += `<span class="wpcalibrate-bpe-variation-indicator">${self.escapeHtml(item.attributes_summary)}</span>`;
					}
				}

				const typeBadge = isVariation 
					? '<span class="wpcalibrate-bpe-badge-variation">Variation</span>'
					: '<span class="wpcalibrate-bpe-badge-simple">Simple</span>';

				const regDisplay = item.regular_price !== null ? `${currency}${item.regular_price.toFixed(2)}` : '&ndash;';
				const saleDisplay = item.sale_price !== null ? `${currency}${item.sale_price.toFixed(2)}` : '&ndash;';

				const rowHtml = `
					<tr id="target-row-${item.id}">
						<th scope="row" class="check-column">
							<input type="checkbox" class="cb-product-row" value="${item.id}" ${isChecked ? 'checked' : ''} aria-label="Select product ${self.escapeHtml(item.title)}" />
						</th>
						<td>${titleHtml}</td>
						<td><code>${self.escapeHtml(item.sku || '&ndash;')}</code></td>
						<td>${typeBadge}</td>
						<td>${regDisplay}</td>
						<td>${saleDisplay}</td>
						<td><span class="stock-status status-${self.escapeHtml(item.stock_status)}">${self.escapeHtml(item.stock_status)}</span></td>
					</tr>
				`;
				$body.append(rowHtml);
			});
		},

		renderPagination(current, totalPages, totalCount) {
			const self = this;
			const $container = $('#table-pagination');
			$container.empty();

			$('#results-count-summary').text(`${totalCount} items found`);

			if (totalPages <= 1) return;

			let html = `<span>Page ${current} of ${totalPages}</span>`;
			if (current > 1) {
				html += `<button type="button" class="button button-secondary btn-page" data-page="${current - 1}">&laquo; Prev</button>`;
			}
			if (current < totalPages) {
				html += `<button type="button" class="button button-secondary btn-page" data-page="${current + 1}">Next &raquo;</button>`;
			}

			$container.html(html);
			$container.find('.btn-page').on('click', function() {
				self.fetchTargets(parseInt($(this).data('page'), 10));
			});
		},

		updateSelectionUI() {
			const totalSelected = this.selectAllMatching ? this.totalMatching : this.selectedIds.size;
			$('#selection-counter-badge').text(`${totalSelected} selected`);

			const $banner = $('#select-all-catalog-banner');
			const visibleRowsCount = $('.cb-product-row').length;
			const checkedVisibleCount = $('.cb-product-row:checked').length;

			$('#cb-select-all').prop('checked', visibleRowsCount > 0 && checkedVisibleCount === visibleRowsCount);

			if (this.selectAllMatching) {
				$banner.show();
				$('#select-all-catalog-text').text(`All ${this.totalMatching} products matching filters across the entire catalog are selected.`);
				$('#btn-select-all-catalog').hide();
				$('#btn-clear-selection').show();
			} else if (checkedVisibleCount === visibleRowsCount && this.totalMatching > visibleRowsCount) {
				$banner.show();
				$('#select-all-catalog-text').text(`All ${visibleRowsCount} products on this page are selected.`);
				$('#btn-select-all-catalog').show();
				$('#btn-clear-selection').hide();
			} else if (this.selectedIds.size > 0) {
				$banner.show();
				$('#select-all-catalog-text').text(`${this.selectedIds.size} specific products selected.`);
				$('#btn-select-all-catalog').hide();
				$('#btn-clear-selection').show();
			} else {
				$banner.hide();
			}
		},

		openPreview() {
			const self = this;
			let totalSelected = self.selectAllMatching ? self.totalMatching : self.selectedIds.size;

			if (totalSelected <= 0) {
				if (self.totalMatching > 0) {
					const confirmAll = confirm(`No products specifically selected. Would you like to apply this price operation to all ${self.totalMatching} matching products in the list?`);
					if (confirmAll) {
						self.selectAllMatching = true;
						self.updateSelectionUI();
						totalSelected = self.totalMatching;
					} else {
						return;
					}
				} else {
					alert(wpcalibrateBpeData.strings.select_products_warn);
					return;
				}
			}

			const actionType = $('#price-action-select').val();
			const val = parseFloat($('#operation-value').val()) || 0.0;
			const roundingMode = $('#rounding-mode-select').val();

			if (actionType !== 'clear_sale_price' && val <= 0 && !actionType.includes('exact')) {
				$('#operation-value').focus();
				alert(wpcalibrateBpeData.strings.enter_value_warn);
				return;
			}

			const $modal = $('#wpcalibrate-bpe-preview-modal');
			const $tbody = $('#preview-table-body');
			$tbody.html('<tr><td colspan="6" class="wpcalibrate-bpe-loading-cell"><span class="spinner is-active" style="float:none;"></span> Calculating preview values...</td></tr>');
			$modal.show();

			$.ajax({
				url: wpcalibrateBpeData.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wpcalibrate_bpe_preview',
					nonce: wpcalibrateBpeData.nonce,
					action_type: actionType,
					value: val,
					rounding_mode: roundingMode,
					select_all_matching: self.selectAllMatching ? 1 : 0,
					target_ids: Array.from(self.selectedIds),
					criteria: self.currentCriteria,
					page: 1,
					per_page: 25,
				},
				success(res) {
					if (!res.success) {
						$tbody.html('<tr><td colspan="6" class="wpcalibrate-bpe-empty-cell">' + (res.data.message || 'Preview calculation error.') + '</td></tr>');
						return;
					}

					const data = res.data;
					self.currentFingerprint = data.operation_fingerprint;

					// Update Counters
					$('#badge-total').text(data.total);
					$('#badge-changing').text(data.changing_count);
					$('#badge-unchanged').text(data.unchanged_count);
					$('#badge-warnings').text(data.warnings_count);

					self.renderPreviewTable(data.items);
				},
				error() {
					$tbody.html('<tr><td colspan="6" class="wpcalibrate-bpe-empty-cell">Network error during preview calculation.</td></tr>');
				}
			});
		},

		renderPreviewTable(items) {
			const self = this;
			const $tbody = $('#preview-table-body');
			$tbody.empty();

			if (!items || !items.length) {
				$tbody.html('<tr><td colspan="6" class="wpcalibrate-bpe-empty-cell">No items to preview.</td></tr>');
				return;
			}

			const currency = wpcalibrateBpeData.currency_symbol || '$';

			items.forEach(item => {
				const regBefore = item.current_regular !== null ? `${currency}${item.current_regular.toFixed(2)}` : '&ndash;';
				const regAfter = item.proposed_regular !== null ? `${currency}${item.proposed_regular.toFixed(2)}` : '&ndash;';
				const regDiff = item.regular_diff !== null && item.regular_diff !== 0 ? `<span class="${item.regular_diff > 0 ? 'text-success' : 'text-danger'}">(${item.regular_diff > 0 ? '+' : ''}${item.regular_diff.toFixed(2)})</span>` : '';

				const saleBefore = item.current_sale !== null ? `${currency}${item.current_sale.toFixed(2)}` : '&ndash;';
				const saleAfter = item.proposed_sale !== null ? `${currency}${item.proposed_sale.toFixed(2)}` : '&ndash;';
				const saleDiff = item.sale_diff !== null && item.sale_diff !== 0 ? `<span class="${item.sale_diff > 0 ? 'text-success' : 'text-danger'}">(${item.sale_diff > 0 ? '+' : ''}${item.sale_diff.toFixed(2)})</span>` : '';

				let noticesHtml = '';
				if (item.warnings && item.warnings.length) {
					noticesHtml = `<span class="wpcalibrate-bpe-pill-warning" title="${self.escapeHtml(item.warnings.join(', '))}"><span class="dashicons dashicons-warning"></span> Warning</span>`;
				} else if (item.has_changed) {
					noticesHtml = '<span class="text-success"><span class="dashicons dashicons-yes"></span> Updating</span>';
				} else {
					noticesHtml = '<span class="text-muted">No Change</span>';
				}

				const row = `
					<tr>
						<td><strong>${self.escapeHtml(item.title)}</strong></td>
						<td><code>${self.escapeHtml(item.sku || '&ndash;')}</code></td>
						<td>${regBefore} &rarr; <strong>${regAfter}</strong> ${regDiff}</td>
						<td>${saleBefore} &rarr; <strong>${saleAfter}</strong> ${saleDiff}</td>
						<td>${item.regular_diff_percent ? `${item.regular_diff_percent}%` : '&ndash;'}</td>
						<td>${noticesHtml}</td>
					</tr>
				`;
				$tbody.append(row);
			});
		},

		executeJob() {
			const self = this;
			if (!confirm(wpcalibrateBpeData.strings.confirm_apply)) {
				return;
			}

			const $btn = $('#btn-confirm-execute').prop('disabled', true);
			const isScheduled = $('#toggle-schedule').is(':checked');

			$.ajax({
				url: wpcalibrateBpeData.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wpcalibrate_bpe_start_job',
					nonce: wpcalibrateBpeData.nonce,
					action_type: $('#price-action-select').val(),
					value: parseFloat($('#operation-value').val()) || 0.0,
					rounding_mode: $('#rounding-mode-select').val(),
					scheduled_at: isScheduled ? $('#schedule-datetime').val() : '',
					auto_restore_at: isScheduled ? $('#auto-restore-datetime').val() : '',
					select_all_matching: self.selectAllMatching ? 1 : 0,
					target_ids: Array.from(self.selectedIds),
					criteria: self.currentCriteria,
					fingerprint: self.currentFingerprint,
				},
				success(res) {
					$btn.prop('disabled', false);
					if (!res.success) {
						alert(res.data.message || 'Failed to start bulk pricing job.');
						return;
					}

					$('#wpcalibrate-bpe-preview-modal').hide();

					if (res.data.is_scheduled) {
						alert('Price modification successfully scheduled for future execution!');
						window.location.href = window.location.href.replace('tab=bulk-editor', 'tab=scheduled');
						return;
					}

					// Show progress modal and start polling
					self.activeJobId = res.data.job_id;
					self.openProgressModal();
				},
				error() {
					$btn.prop('disabled', false);
					alert('A network error occurred while queueing the job.');
				}
			});
		},

		openProgressModal() {
			const self = this;
			const $modal = $('#wpcalibrate-bpe-progress-modal');
			$('#job-progress-bar').css('width', '0%');
			$('#job-progress-percent').text('0%');
			$('#progress-completion-message').hide();
			$('#btn-view-history, #btn-close-progress').hide();
			$modal.show();

			self.pollProgress();
			self.pollTimer = setInterval(() => self.pollProgress(), 1500);
		},

		pollProgress() {
			const self = this;
			if (!self.activeJobId) return;

			$.ajax({
				url: wpcalibrateBpeData.ajax_url,
				type: 'GET',
				dataType: 'json',
				data: {
					action: 'wpcalibrate_bpe_poll_progress',
					nonce: wpcalibrateBpeData.nonce,
					job_id: self.activeJobId,
				},
				success(res) {
					if (!res.success) return;

					const p = res.data;
					$('#job-progress-bar').css('width', `${p.percent}%`);
					$('#job-progress-percent').text(`${p.percent}% (${p.status_label})`);
					$('#stat-processed').text(`${p.processed} / ${p.total}`);
					$('#stat-changed').text(p.changed);
					$('#stat-skipped').text(p.skipped);
					$('#stat-failed').text(p.failed);

					if (p.is_terminal) {
						clearInterval(self.pollTimer);
						$('#progress-completion-message').fadeIn();
						$('#btn-view-history, #btn-close-progress').show();
					}
				}
			});
		},

		cancelJob(jobId) {
			$.ajax({
				url: wpcalibrateBpeData.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wpcalibrate_bpe_cancel_job',
					nonce: wpcalibrateBpeData.nonce,
					job_id: jobId,
				},
				success(res) {
					if (res.success) {
						$(`#job-row-${jobId}`).fadeOut();
					} else {
						alert(res.data.message || 'Could not cancel job.');
					}
				}
			});
		},

		openUndoPreview(jobId) {
			const self = this;
			const $modal = $('#wpcalibrate-bpe-undo-modal');
			$('#undo-summary-banner').html('<p><span class="spinner is-active" style="float:none;"></span> Verifying current catalog prices against captured before-prices...</p>');
			$('#undo-badges, #undo-preview-table-wrapper').hide();
			$('#btn-confirm-undo').prop('disabled', true).data('job-id', jobId);
			$modal.show();

			$.ajax({
				url: wpcalibrateBpeData.ajax_url,
				type: 'GET',
				dataType: 'json',
				data: {
					action: 'wpcalibrate_bpe_preview_undo',
					nonce: wpcalibrateBpeData.nonce,
					parent_job_id: jobId,
				},
				success(res) {
					if (!res.success) {
						$('#undo-summary-banner').html(`<p class="text-danger">${res.data.message || 'Error loading undo preview.'}</p>`);
						return;
					}

					const data = res.data;
					$('#undo-eligible-count').text(data.eligible_count);
					$('#undo-conflict-count').text(data.conflict_count);
					$('#undo-missing-count').text(data.missing_count);
					$('#undo-badges').show();

					if (data.eligible_count > 0) {
						$('#btn-confirm-undo').prop('disabled', false);
						$('#undo-summary-banner').html(`<p class="text-success">Found <strong>${data.eligible_count}</strong> products eligible for safe price restore.</p>`);
					} else {
						$('#undo-summary-banner').html('<p class="text-warning">No products are eligible to restore (prices changed since job completed or products deleted).</p>');
					}

					self.renderUndoTable(data.items);
				}
			});
		},

		renderUndoTable(items) {
			const $tbody = $('#undo-preview-table-body');
			$tbody.empty();
			const currency = wpcalibrateBpeData.currency_symbol || '$';

			items.forEach(item => {
				const current = item.current_regular !== null ? `${currency}${item.current_regular.toFixed(2)}` : '&ndash;';
				const restore = item.restore_target_reg !== null ? `${currency}${item.restore_target_reg.toFixed(2)}` : '&ndash;';

				let stateHtml = '';
				if (item.status === 'eligible') {
					stateHtml = '<span class="text-success"><span class="dashicons dashicons-yes"></span> Ready to restore</span>';
				} else if (item.status === 'conflict') {
					stateHtml = '<span class="text-warning"><span class="dashicons dashicons-warning"></span> Conflict (Will skip)</span>';
				} else {
					stateHtml = '<span class="text-muted">Product Deleted</span>';
				}

				$tbody.append(`
					<tr>
						<td><strong>${this.escapeHtml(item.title)}</strong></td>
						<td>${current}</td>
						<td><strong>${restore}</strong></td>
						<td>${stateHtml}</td>
					</tr>
				`);
			});
			$('#undo-preview-table-wrapper').show();
		},

		executeUndo(jobId) {
			const self = this;
			if (!confirm(wpcalibrateBpeData.strings.confirm_undo)) return;

			$('#btn-confirm-undo').prop('disabled', true);

			$.ajax({
				url: wpcalibrateBpeData.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wpcalibrate_bpe_execute_undo',
					nonce: wpcalibrateBpeData.nonce,
					parent_job_id: jobId,
				},
				success(res) {
					$('#wpcalibrate-bpe-undo-modal').hide();
					if (res.success) {
						alert(`Restore job #${res.data.restore_job_id} successfully dispatched via Action Scheduler!`);
						location.reload();
					} else {
						alert(res.data.message || 'Failed to dispatch restore job.');
					}
				}
			});
		},

		deleteHistory(jobId) {
			$.ajax({
				url: wpcalibrateBpeData.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wpcalibrate_bpe_delete_history',
					nonce: wpcalibrateBpeData.nonce,
					job_id: jobId,
				},
				success(res) {
					if (res.success) {
						$(`#history-row-${jobId}`).fadeOut();
					} else {
						alert(res.data.message || 'Could not delete history record.');
					}
				}
			});
		},

		escapeHtml(str) {
			if (!str) return '';
			return String(str).replace(/[&<>"']/g, function(m) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
			});
		}
	};

	$(document).ready(function() {
		app.init();
	});

})(jQuery);
