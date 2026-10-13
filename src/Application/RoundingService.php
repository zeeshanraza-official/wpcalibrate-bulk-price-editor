<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Application;

use WPCalibrate\BulkPriceEditor\Domain\RoundingMode;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Service handling rounding rules with floating-point safety.
 */
final class RoundingService {

	/**
	 * Apply a rounding mode to a calculated price.
	 *
	 * @param float        $price Raw calculated price.
	 * @param RoundingMode $mode Rounding mode.
	 * @param int          $decimals Number of decimals (default 2).
	 * @return float Rounded price.
	 */
	public static function round( float $price, RoundingMode $mode, int $decimals = 2 ): float {
		if ( $price <= 0.0 ) {
			return 0.0;
		}

		return match ( $mode ) {
			RoundingMode::NONE            => self::to_precision( $price, $decimals ),
			RoundingMode::STORE_PRECISION => self::to_precision( $price, $decimals ),
			RoundingMode::NEAREST_WHOLE   => self::to_precision( round( $price ), $decimals ),
			RoundingMode::UP_WHOLE        => self::to_precision( ceil( $price ), $decimals ),
			RoundingMode::DOWN_WHOLE      => self::to_precision( floor( $price ), $decimals ),
			RoundingMode::END_99          => self::apply_cents_ending( $price, 0.99, $decimals ),
			RoundingMode::END_95          => self::apply_cents_ending( $price, 0.95, $decimals ),
			RoundingMode::END_49          => self::apply_cents_ending( $price, 0.49, $decimals ),
		};
	}

	/**
	 * Apply psychological cents ending (.99, .95, .49).
	 *
	 * @param float $price Original price.
	 * @param float $cents Cents ending (e.g. 0.99).
	 * @param int   $decimals Decimal precision.
	 * @return float
	 */
	private static function apply_cents_ending( float $price, float $cents, int $decimals ): float {
		if ( $price <= 0.0 ) {
			return 0.0;
		}

		$whole = floor( $price );
		$candidate = $whole + $cents;

		// Boundary case: if original price was lower than the cents fraction (e.g. 0.30 with .99 ending),
		// $candidate is 0.99.
		return self::to_precision( $candidate, $decimals );
	}

	/**
	 * Round price to configured decimal precision safely.
	 */
	public static function to_precision( float $val, int $decimals = 2 ): float {
		$factor = 10 ** $decimals;
		return round( $val * $factor ) / $factor;
	}

	/**
	 * Format price as standard decimal string.
	 */
	public static function format_decimal( float $price, int $decimals = 2 ): string {
		return number_format( $price, $decimals, '.', '' );
	}
}
