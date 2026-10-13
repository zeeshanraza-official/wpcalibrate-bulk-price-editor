<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Domain;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Supported price action operations.
 */
enum PriceAction: string {
	case SET_REGULAR_EXACT                  = 'set_regular_exact';
	case SET_SALE_EXACT                     = 'set_sale_exact';
	case DISCOUNT_SALE_FROM_REGULAR_PERCENT = 'discount_sale_from_regular_percent';
	case DISCOUNT_SALE_FROM_REGULAR_FIXED   = 'discount_sale_from_regular_fixed';
	case INCREASE_REGULAR_FIXED             = 'increase_regular_fixed';
	case DECREASE_REGULAR_FIXED             = 'decrease_regular_fixed';
	case INCREASE_REGULAR_PERCENT           = 'increase_regular_percent';
	case DECREASE_REGULAR_PERCENT           = 'decrease_regular_percent';
	case INCREASE_SALE_FIXED                = 'increase_sale_fixed';
	case DECREASE_SALE_FIXED                = 'decrease_sale_fixed';
	case INCREASE_SALE_PERCENT              = 'increase_sale_percent';
	case DECREASE_SALE_PERCENT              = 'decrease_sale_percent';
	case CLEAR_SALE_PRICE                   = 'clear_sale_price';

	/**
	 * Get human readable label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::SET_REGULAR_EXACT                  => __( 'Set regular price to exact amount', 'wpcalibrate-bulk-price-editor' ),
			self::SET_SALE_EXACT                     => __( 'Set sale price to exact amount', 'wpcalibrate-bulk-price-editor' ),
			self::DISCOUNT_SALE_FROM_REGULAR_PERCENT => __( 'Set sale price: discount from regular price by % (e.g. 20% OFF)', 'wpcalibrate-bulk-price-editor' ),
			self::DISCOUNT_SALE_FROM_REGULAR_FIXED   => __( 'Set sale price: discount from regular price by amount (e.g. $10 OFF)', 'wpcalibrate-bulk-price-editor' ),
			self::INCREASE_REGULAR_FIXED             => __( 'Increase regular price by fixed amount', 'wpcalibrate-bulk-price-editor' ),
			self::DECREASE_REGULAR_FIXED             => __( 'Decrease regular price by fixed amount', 'wpcalibrate-bulk-price-editor' ),
			self::INCREASE_REGULAR_PERCENT           => __( 'Increase regular price by percentage (%)', 'wpcalibrate-bulk-price-editor' ),
			self::DECREASE_REGULAR_PERCENT           => __( 'Decrease regular price by percentage (%)', 'wpcalibrate-bulk-price-editor' ),
			self::INCREASE_SALE_FIXED                => __( 'Increase existing sale price by fixed amount', 'wpcalibrate-bulk-price-editor' ),
			self::DECREASE_SALE_FIXED                => __( 'Decrease existing sale price by fixed amount', 'wpcalibrate-bulk-price-editor' ),
			self::INCREASE_SALE_PERCENT              => __( 'Increase existing sale price by percentage (%)', 'wpcalibrate-bulk-price-editor' ),
			self::DECREASE_SALE_PERCENT              => __( 'Decrease existing sale price by percentage (%)', 'wpcalibrate-bulk-price-editor' ),
			self::CLEAR_SALE_PRICE                   => __( 'Clear sale price (remove sale)', 'wpcalibrate-bulk-price-editor' ),
		};
	}

	/**
	 * Target field modified by this operation: 'regular', 'sale', or 'both'.
	 */
	public function target_field(): string {
		return match ( $this ) {
			self::SET_REGULAR_EXACT,
			self::INCREASE_REGULAR_FIXED,
			self::DECREASE_REGULAR_FIXED,
			self::INCREASE_REGULAR_PERCENT,
			self::DECREASE_REGULAR_PERCENT           => 'regular',
			self::SET_SALE_EXACT,
			self::DISCOUNT_SALE_FROM_REGULAR_PERCENT,
			self::DISCOUNT_SALE_FROM_REGULAR_FIXED,
			self::INCREASE_SALE_FIXED,
			self::DECREASE_SALE_FIXED,
			self::INCREASE_SALE_PERCENT,
			self::DECREASE_SALE_PERCENT,
			self::CLEAR_SALE_PRICE                   => 'sale',
		};
	}

	/**
	 * Does this action require a numeric input value?
	 */
	public function requires_value(): bool {
		return $this !== self::CLEAR_SALE_PRICE;
	}
}
