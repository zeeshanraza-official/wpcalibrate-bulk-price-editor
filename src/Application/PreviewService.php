<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Application;

use WPCalibrate\BulkPriceEditor\Domain\PriceAction;
use WPCalibrate\BulkPriceEditor\Domain\RoundingMode;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Preview item representation.
 */
final class PreviewItemDto {

	/**
	 * @param int         $id
	 * @param string      $title
	 * @param string|null $parent_title
	 * @param string      $sku
	 * @param string      $type
	 * @param string      $attributes_summary
	 * @param float|null  $current_regular
	 * @param float|null  $proposed_regular
	 * @param float|null  $regular_diff
	 * @param float|null  $regular_diff_percent
	 * @param float|null  $current_sale
	 * @param float|null  $proposed_sale
	 * @param float|null  $sale_diff
	 * @param float|null  $sale_diff_percent
	 * @param bool        $has_changed
	 * @param string[]    $warnings
	 * @param string      $checksum
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly ?string $parent_title,
		public readonly string $sku,
		public readonly string $type,
		public readonly string $attributes_summary,
		public readonly ?float $current_regular,
		public readonly ?float $proposed_regular,
		public readonly ?float $regular_diff,
		public readonly ?float $regular_diff_percent,
		public readonly ?float $current_sale,
		public readonly ?float $proposed_sale,
		public readonly ?float $sale_diff,
		public readonly ?float $sale_diff_percent,
		public readonly bool $has_changed,
		public readonly array $warnings,
		public readonly string $checksum
	) {}

	/**
	 * Convert to array for REST/AJAX response.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'                   => $this->id,
			'title'                => $this->title,
			'parent_title'         => $this->parent_title,
			'sku'                  => $this->sku,
			'type'                 => $this->type,
			'attributes_summary'   => $this->attributes_summary,
			'current_regular'      => $this->current_regular,
			'proposed_regular'     => $this->proposed_regular,
			'regular_diff'         => $this->regular_diff,
			'regular_diff_percent' => $this->regular_diff_percent,
			'current_sale'         => $this->current_sale,
			'proposed_sale'        => $this->proposed_sale,
			'sale_diff'            => $this->sale_diff,
			'sale_diff_percent'    => $this->sale_diff_percent,
			'has_changed'          => $this->has_changed,
			'warnings'             => $this->warnings,
			'checksum'             => $this->checksum,
		);
	}
}

/**
 * Preview calculation service with deterministic fingerprinting.
 */
final class PreviewService {

	/**
	 * Generate price preview and totals.
	 *
	 * @param int[]        $target_ids Array of target IDs.
	 * @param PriceAction  $action Price action.
	 * @param float        $value Value.
	 * @param RoundingMode $rounding_mode Rounding mode.
	 * @param int          $page Page.
	 * @param int          $per_page Items per page.
	 * @param int          $decimals Decimals precision.
	 * @return array<string, mixed>
	 */
	public static function generate_preview(
		array $target_ids,
		PriceAction $action,
		float $value,
		RoundingMode $rounding_mode,
		int $page = 1,
		int $per_page = 20,
		int $decimals = 2
	): array {
		$total_targets = count( $target_ids );
		$offset = ( max( 1, $page ) - 1 ) * $per_page;
		$paginated_ids = array_slice( $target_ids, $offset, $per_page );

		$items = array();
		$changing_count  = 0;
		$unchanged_count = 0;
		$skipped_count   = 0;
		$warnings_count  = 0;

		// Process targets to compute preview items for the requested page
		foreach ( $paginated_ids as $target_id ) {
			$dto = FilterService::build_target_dto( (int) $target_id );
			if ( null === $dto ) {
				continue;
			}

			$calc = PricingService::calculate(
				$dto->regular_price,
				$dto->sale_price,
				$action,
				$value,
				$rounding_mode,
				$decimals
			);

			$has_changed = $calc->has_regular_changed || $calc->has_sale_changed;
			$checksum = self::generate_target_checksum( $dto->id, $dto->regular_price, $dto->sale_price );

			$item = new PreviewItemDto(
				id: $dto->id,
				title: $dto->title,
				parent_title: $dto->parent_title,
				sku: $dto->sku,
				type: $dto->type,
				attributes_summary: $dto->attributes_summary,
				current_regular: $dto->regular_price,
				proposed_regular: $calc->proposed_regular,
				regular_diff: $calc->regular_diff,
				regular_diff_percent: $calc->regular_diff_percent,
				current_sale: $calc->proposed_sale !== $dto->sale_price ? $dto->sale_price : $dto->sale_price,
				proposed_sale: $calc->proposed_sale,
				sale_diff: $calc->sale_diff,
				sale_diff_percent: $calc->sale_diff_percent,
				has_changed: $has_changed,
				warnings: $calc->warnings,
				checksum: $checksum
			);

			$items[] = $item->to_array();
		}

		// Calculate approximate counts for summary across the target list
		// Sample / quick query for totals
		$changing_count = count( array_filter( $items, fn( $i ) => $i['has_changed'] ) );
		$warnings_count = count( array_filter( $items, fn( $i ) => ! empty( $i['warnings'] ) ) );

		// Generate overall fingerprint of the operation definition
		$operation_fingerprint = self::generate_operation_fingerprint(
			$target_ids,
			$action,
			$value,
			$rounding_mode
		);

		return array(
			'items'                 => $items,
			'total'                 => $total_targets,
			'total_pages'           => (int) ceil( $total_targets / max( 1, $per_page ) ),
			'page'                  => $page,
			'changing_count'        => $changing_count,
			'unchanged_count'       => count( $items ) - $changing_count,
			'warnings_count'        => $warnings_count,
			'operation_fingerprint' => $operation_fingerprint,
		);
	}

	/**
	 * Generate target price checksum to detect modifications between preview and execution.
	 */
	public static function generate_target_checksum( int $id, ?float $regular, ?float $sale ): string {
		$reg_str  = null !== $regular ? number_format( $regular, 4, '.', '' ) : 'null';
		$sale_str = null !== $sale ? number_format( $sale, 4, '.', '' ) : 'null';
		return hash( 'sha256', "{$id}:{$reg_str}:{$sale_str}" );
	}

	/**
	 * Generate deterministic fingerprint of the whole operation.
	 *
	 * @param int[] $target_ids
	 */
	public static function generate_operation_fingerprint(
		array $target_ids,
		PriceAction $action,
		float $value,
		RoundingMode $rounding_mode
	): string {
		sort( $target_ids, SORT_NUMERIC );
		$id_hash = hash( 'sha256', implode( ',', $target_ids ) );
		return hash( 'sha256', "{$id_hash}:{$action->value}:{$value}:{$rounding_mode->value}" );
	}
}
