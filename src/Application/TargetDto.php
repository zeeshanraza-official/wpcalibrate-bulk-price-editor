<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Application;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Data Transfer Object representing a selectable pricing target (simple product or variation).
 */
final class TargetDto {

	/**
	 * @param int         $id Product or variation ID.
	 * @param int         $parent_id Parent ID if variation, 0 if top-level product.
	 * @param string      $title Display title.
	 * @param string|null $parent_title Parent title if variation.
	 * @param string      $sku SKU code.
	 * @param string      $type 'simple', 'variation', or 'variable'.
	 * @param float|null  $regular_price Current regular price.
	 * @param float|null  $sale_price Current sale price.
	 * @param string      $stock_status 'instock', 'outofstock', or 'onbackorder'.
	 * @param string      $status 'publish', 'draft', etc.
	 * @param string      $attributes_summary Human-readable variation attributes.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $parent_id,
		public readonly string $title,
		public readonly ?string $parent_title,
		public readonly string $sku,
		public readonly string $type,
		public readonly ?float $regular_price,
		public readonly ?float $sale_price,
		public readonly string $stock_status,
		public readonly string $status = 'publish',
		public readonly string $attributes_summary = ''
	) {}

	/**
	 * Convert to array for JSON responses and previews.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'                 => $this->id,
			'parent_id'          => $this->parent_id,
			'title'              => $this->title,
			'parent_title'       => $this->parent_title,
			'sku'                => $this->sku,
			'type'               => $this->type,
			'regular_price'      => $this->regular_price,
			'sale_price'         => $this->sale_price,
			'stock_status'       => $this->stock_status,
			'status'             => $this->status,
			'attributes_summary' => $this->attributes_summary,
		);
	}
}
