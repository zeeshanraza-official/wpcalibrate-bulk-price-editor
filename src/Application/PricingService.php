<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Application;

use WPCalibrate\BulkPriceEditor\Domain\PriceAction;
use WPCalibrate\BulkPriceEditor\Domain\RoundingMode;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Result data transfer object for pricing calculations.
 */
final class CalculationResult {

	/**
	 * @param float|null $proposed_regular
	 * @param float|null $proposed_sale
	 * @param bool       $has_regular_changed
	 * @param bool       $has_sale_changed
	 * @param float|null $regular_diff
	 * @param float|null $sale_diff
	 * @param float|null $regular_diff_percent
	 * @param float|null $sale_diff_percent
	 * @param string[]   $warnings
	 * @param bool       $is_valid
	 * @param string|null $error
	 */
	public function __construct(
		public readonly ?float $proposed_regular,
		public readonly ?float $proposed_sale,
		public readonly bool $has_regular_changed,
		public readonly bool $has_sale_changed,
		public readonly ?float $regular_diff,
		public readonly ?float $sale_diff,
		public readonly ?float $regular_diff_percent,
		public readonly ?float $sale_diff_percent,
		public readonly array $warnings = array(),
		public readonly bool $is_valid = true,
		public readonly ?string $error = null
	) {}
}

/**
 * Shared Pricing Engine for calculations in preview and batch execution.
 */
final class PricingService {

	/**
	 * Calculate new prices according to the action and rounding rules.
	 *
	 * @param float|null   $current_regular Current regular price or null.
	 * @param float|null   $current_sale    Current sale price or null.
	 * @param PriceAction  $action          Price action to perform.
	 * @param float        $value           Numeric operation value (amount or percent).
	 * @param RoundingMode $rounding_mode   Rounding mode.
	 * @param int          $decimals        Currency decimals (default 2).
	 * @return CalculationResult
	 */
	public static function calculate(
		?float $current_regular,
		?float $current_sale,
		PriceAction $action,
		float $value,
		RoundingMode $rounding_mode = RoundingMode::NONE,
		int $decimals = 2
	): CalculationResult {
		$warnings = array();
		$proposed_regular = $current_regular;
		$proposed_sale    = $current_sale;
		$has_regular_changed = false;
		$has_sale_changed    = false;

		// Floating-point safe delta calculations
		switch ( $action ) {
			case PriceAction::SET_REGULAR_EXACT:
				$proposed_regular    = max( 0.0, $value );
				$has_regular_changed = true;
				break;

			case PriceAction::SET_SALE_EXACT:
				$proposed_sale    = max( 0.0, $value );
				$has_sale_changed = true;
				break;

			case PriceAction::DISCOUNT_SALE_FROM_REGULAR_PERCENT:
				$base = $current_regular ?? 0.0;
				$delta = $base * ( $value / 100.0 );
				$proposed_sale    = max( 0.0, $base - $delta );
				$has_sale_changed = true;
				break;

			case PriceAction::DISCOUNT_SALE_FROM_REGULAR_FIXED:
				$base = $current_regular ?? 0.0;
				$proposed_sale    = max( 0.0, $base - max( 0.0, $value ) );
				$has_sale_changed = true;
				break;

			case PriceAction::INCREASE_REGULAR_FIXED:
				$base = $current_regular ?? 0.0;
				$proposed_regular    = $base + max( 0.0, $value );
				$has_regular_changed = true;
				break;

			case PriceAction::DECREASE_REGULAR_FIXED:
				$base = $current_regular ?? 0.0;
				$proposed_regular    = max( 0.0, $base - max( 0.0, $value ) );
				$has_regular_changed = true;
				break;

			case PriceAction::INCREASE_REGULAR_PERCENT:
				$base = $current_regular ?? 0.0;
				$delta = $base * ( $value / 100.0 );
				$proposed_regular    = $base + $delta;
				$has_regular_changed = true;
				break;

			case PriceAction::DECREASE_REGULAR_PERCENT:
				$base = $current_regular ?? 0.0;
				$delta = $base * ( $value / 100.0 );
				$proposed_regular    = max( 0.0, $base - $delta );
				$has_regular_changed = true;
				break;

			case PriceAction::INCREASE_SALE_FIXED:
				if ( null !== $current_sale && $current_sale > 0.0 ) {
					$proposed_sale    = $current_sale + max( 0.0, $value );
					$has_sale_changed = true;
				} else {
					$base = $current_regular ?? 0.0;
					$proposed_sale    = max( 0.0, $base - max( 0.0, $value ) );
					$has_sale_changed = true;
					$warnings[] = __( 'No prior sale price; applied sale discount from regular price.', 'wpcalibrate-bulk-price-editor' );
				}
				break;

			case PriceAction::DECREASE_SALE_FIXED:
				$base = ( null !== $current_sale && $current_sale > 0.0 ) ? $current_sale : ( $current_regular ?? 0.0 );
				$proposed_sale    = max( 0.0, $base - max( 0.0, $value ) );
				$has_sale_changed = true;
				if ( null === $current_sale || $current_sale <= 0.0 ) {
					$warnings[] = __( 'No prior sale price; sale discount calculated from regular price.', 'wpcalibrate-bulk-price-editor' );
				}
				break;

			case PriceAction::INCREASE_SALE_PERCENT:
				if ( null !== $current_sale && $current_sale > 0.0 ) {
					$delta = $current_sale * ( $value / 100.0 );
					$proposed_sale    = $current_sale + $delta;
					$has_sale_changed = true;
				} else {
					$base = $current_regular ?? 0.0;
					$delta = $base * ( $value / 100.0 );
					$proposed_sale    = max( 0.0, $base - $delta );
					$has_sale_changed = true;
					$warnings[] = __( 'No prior sale price; applied sale discount from regular price.', 'wpcalibrate-bulk-price-editor' );
				}
				break;

			case PriceAction::DECREASE_SALE_PERCENT:
				$base = ( null !== $current_sale && $current_sale > 0.0 ) ? $current_sale : ( $current_regular ?? 0.0 );
				$delta = $base * ( $value / 100.0 );
				$proposed_sale    = max( 0.0, $base - $delta );
				$has_sale_changed = true;
				if ( null === $current_sale || $current_sale <= 0.0 ) {
					$warnings[] = __( 'No prior sale price; sale discount calculated from regular price.', 'wpcalibrate-bulk-price-editor' );
				}
				break;

			case PriceAction::CLEAR_SALE_PRICE:
				if ( null !== $current_sale ) {
					$proposed_sale    = null;
					$has_sale_changed = true;
				}
				break;
		}

		// Apply Rounding Rules to altered values
		if ( $has_regular_changed && null !== $proposed_regular ) {
			$proposed_regular = RoundingService::round( $proposed_regular, $rounding_mode, $decimals );
		}

		if ( $has_sale_changed && null !== $proposed_sale ) {
			$proposed_sale = RoundingService::round( $proposed_sale, $rounding_mode, $decimals );
		}

		// If value didn't actually change numerically from current, reflect has_changed accurately
		if ( $has_regular_changed && null !== $current_regular && null !== $proposed_regular ) {
			if ( abs( $proposed_regular - $current_regular ) < 0.00001 ) {
				$has_regular_changed = false;
			}
		}

		if ( $has_sale_changed && null !== $current_sale && null !== $proposed_sale ) {
			if ( abs( $proposed_sale - $current_sale ) < 0.00001 ) {
				$has_sale_changed = false;
			}
		}

		// Validate WooCommerce Sale Price Constraints
		$effective_regular = $proposed_regular ?? $current_regular;
		$effective_sale    = $proposed_sale;

		if ( null !== $effective_sale && null !== $effective_regular && $effective_regular > 0.0 ) {
			if ( $effective_sale >= $effective_regular ) {
				$warnings[] = sprintf(
					/* translators: 1: Proposed sale price, 2: Proposed regular price */
					__( 'Sale price (%1$s) must be lower than regular price (%2$s). In WooCommerce, a sale price equal to or greater than the regular price is invalid.', 'wpcalibrate-bulk-price-editor' ),
					RoundingService::format_decimal( $effective_sale, $decimals ),
					RoundingService::format_decimal( $effective_regular, $decimals )
				);
			}
		}

		if ( null !== $proposed_regular && $proposed_regular < 0.0 ) {
			return new CalculationResult(
				proposed_regular: null,
				proposed_sale: null,
				has_regular_changed: false,
				has_sale_changed: false,
				regular_diff: null,
				sale_diff: null,
				regular_diff_percent: null,
				sale_diff_percent: null,
				warnings: $warnings,
				is_valid: false,
				error: __( 'Regular price cannot be negative.', 'wpcalibrate-bulk-price-editor' )
			);
		}

		// Compute differences
		$regular_diff = null;
		$regular_diff_percent = null;
		if ( null !== $proposed_regular && null !== $current_regular ) {
			$regular_diff = RoundingService::to_precision( $proposed_regular - $current_regular, $decimals );
			if ( $current_regular > 0.0 ) {
				$regular_diff_percent = RoundingService::to_precision( ( ( $proposed_regular - $current_regular ) / $current_regular ) * 100.0, 2 );
			}
		}

		$sale_diff = null;
		$sale_diff_percent = null;
		if ( null !== $proposed_sale && null !== $current_sale ) {
			$sale_diff = RoundingService::to_precision( $proposed_sale - $current_sale, $decimals );
			if ( $current_sale > 0.0 ) {
				$sale_diff_percent = RoundingService::to_precision( ( ( $proposed_sale - $current_sale ) / $current_sale ) * 100.0, 2 );
			}
		}

		return new CalculationResult(
			proposed_regular: $proposed_regular,
			proposed_sale: $proposed_sale,
			has_regular_changed: $has_regular_changed,
			has_sale_changed: $has_sale_changed,
			regular_diff: $regular_diff,
			sale_diff: $sale_diff,
			regular_diff_percent: $regular_diff_percent,
			sale_diff_percent: $sale_diff_percent,
			warnings: $warnings,
			is_valid: true,
			error: null
		);
	}
}
