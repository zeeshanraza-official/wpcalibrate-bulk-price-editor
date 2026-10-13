<?php
declare(strict_types=1);

namespace WPCalibrate\BulkPriceEditor\Application;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_BPE_TEST_MODE' ) ) {
	exit;
}

/**
 * Service for querying and filtering WooCommerce products and variations.
 */
final class FilterService {

	/**
	 * Query targets with pagination.
	 *
	 * @param array<string, mixed> $criteria Filter criteria.
	 * @param int                  $page     Current page number (1-indexed).
	 * @param int                  $per_page Items per page.
	 * @return array{targets: TargetDto[], total: int, total_pages: int, page: int}
	 */
	public static function query_targets( array $criteria, int $page = 1, int $per_page = 20 ): array {
		$matching_ids = self::get_matching_target_ids( $criteria );
		$total = count( $matching_ids );
		$total_pages = (int) ceil( $total / max( 1, $per_page ) );
		$offset = ( max( 1, $page ) - 1 ) * $per_page;
		$slice_ids = array_slice( $matching_ids, $offset, $per_page );

		$targets = array();
		foreach ( $slice_ids as $id ) {
			$dto = self::build_target_dto( (int) $id );
			if ( null !== $dto ) {
				$targets[] = $dto;
			}
		}

		return array(
			'targets'     => $targets,
			'total'       => $total,
			'total_pages' => $total_pages,
			'page'        => $page,
		);
	}

	/**
	 * Retrieve all matching product and variation IDs for the given criteria.
	 *
	 * Uses IDs-first querying to minimize memory overhead.
	 *
	 * @param array<string, mixed> $criteria Filter criteria.
	 * @return int[] Array of integer target IDs.
	 */
	public static function get_matching_target_ids( array $criteria ): array {
		$product_type = $criteria['product_type'] ?? 'all';
		$post_status  = ! empty( $criteria['post_status'] ) ? sanitize_text_field( $criteria['post_status'] ) : 'publish';
		$search       = ! empty( $criteria['search'] ) ? sanitize_text_field( $criteria['search'] ) : '';
		$sku          = ! empty( $criteria['sku'] ) ? sanitize_text_field( $criteria['sku'] ) : '';
		$category_id  = ! empty( $criteria['category_id'] ) ? absint( $criteria['category_id'] ) : 0;
		$tag_id       = ! empty( $criteria['tag_id'] ) ? absint( $criteria['tag_id'] ) : 0;
		$stock_status = ! empty( $criteria['stock_status'] ) ? sanitize_text_field( $criteria['stock_status'] ) : '';

		$min_regular = isset( $criteria['min_regular'] ) && '' !== $criteria['min_regular'] ? (float) $criteria['min_regular'] : null;
		$max_regular = isset( $criteria['max_regular'] ) && '' !== $criteria['max_regular'] ? (float) $criteria['max_regular'] : null;
		$min_sale    = isset( $criteria['min_sale'] ) && '' !== $criteria['min_sale'] ? (float) $criteria['min_sale'] : null;
		$max_sale    = isset( $criteria['max_sale'] ) && '' !== $criteria['max_sale'] ? (float) $criteria['max_sale'] : null;

		$results = array();

		// Determine if we should query simple products, variations, or both
		$include_simple    = in_array( $product_type, array( 'all', 'simple' ), true );
		$include_variation = in_array( $product_type, array( 'all', 'variation' ), true );

		// 1. If taxonomy filters exist (category or tag), find qualifying top-level products first
		$parent_ids_with_terms = null;
		if ( $category_id > 0 || $tag_id > 0 ) {
			$tax_query = array( 'relation' => 'AND' );
			if ( $category_id > 0 ) {
				$tax_query[] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => array( $category_id ),
				);
			}
			if ( $tag_id > 0 ) {
				$tax_query[] = array(
					'taxonomy' => 'product_tag',
					'field'    => 'term_id',
					'terms'    => array( $tag_id ),
				);
			}

			$term_query = new \WP_Query(
				array(
					'post_type'              => 'product',
					'post_status'            => $post_status,
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'tax_query'              => $tax_query,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			$parent_ids_with_terms = ! empty( $term_query->posts ) ? array_map( 'intval', $term_query->posts ) : array();

			if ( empty( $parent_ids_with_terms ) ) {
				return array(); // No products match taxonomy terms
			}
		}

		// 2. Query Simple Products if requested
		if ( $include_simple ) {
			$meta_query = self::build_price_and_stock_meta_query( $min_regular, $max_regular, $min_sale, $max_sale, $stock_status, $sku );

			$args = array(
				'post_type'              => 'product',
				'post_status'            => $post_status,
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			);

			if ( ! empty( $search ) ) {
				$args['s'] = $search;
			}

			if ( null !== $parent_ids_with_terms ) {
				$args['post__in'] = $parent_ids_with_terms;
			}

			if ( ! empty( $meta_query ) ) {
				$args['meta_query'] = $meta_query;
			}

			// Restrict to simple products (exclude variable parents if only editing individual prices)
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => 'simple',
				),
			);

			$simple_query = new \WP_Query( $args );
			if ( ! empty( $simple_query->posts ) ) {
				foreach ( $simple_query->posts as $pid ) {
					$results[] = (int) $pid;
				}
			}
		}

		// 3. Query Variations if requested
		if ( $include_variation ) {
			$meta_query = self::build_price_and_stock_meta_query( $min_regular, $max_regular, $min_sale, $max_sale, $stock_status, $sku );

			$args = array(
				'post_type'              => 'product_variation',
				'post_status'            => array( 'publish', 'private' ),
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			);

			if ( ! empty( $search ) ) {
				$args['s'] = $search;
			}

			if ( null !== $parent_ids_with_terms ) {
				$args['post_parent__in'] = $parent_ids_with_terms;
			}

			if ( ! empty( $meta_query ) ) {
				$args['meta_query'] = $meta_query;
			}

			$var_query = new \WP_Query( $args );
			if ( ! empty( $var_query->posts ) ) {
				foreach ( $var_query->posts as $vid ) {
					$results[] = (int) $vid;
				}
			}
		}

		return array_values( array_unique( $results ) );
	}

	/**
	 * Build WP meta_query array for pricing and stock filters.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_price_and_stock_meta_query(
		?float $min_reg,
		?float $max_reg,
		?float $min_sale,
		?float $max_sale,
		string $stock_status,
		string $sku
	): array {
		$meta_query = array( 'relation' => 'AND' );

		if ( ! empty( $sku ) ) {
			$meta_query[] = array(
				'key'     => '_sku',
				'value'   => $sku,
				'compare' => 'LIKE',
			);
		}

		if ( ! empty( $stock_status ) ) {
			$meta_query[] = array(
				'key'     => '_stock_status',
				'value'   => $stock_status,
				'compare' => '=',
			);
		}

		if ( null !== $min_reg ) {
			$meta_query[] = array(
				'key'     => '_regular_price',
				'value'   => $min_reg,
				'type'    => 'DECIMAL(18,4)',
				'compare' => '>=',
			);
		}

		if ( null !== $max_reg ) {
			$meta_query[] = array(
				'key'     => '_regular_price',
				'value'   => $max_reg,
				'type'    => 'DECIMAL(18,4)',
				'compare' => '<=',
			);
		}

		if ( null !== $min_sale ) {
			$meta_query[] = array(
				'key'     => '_sale_price',
				'value'   => $min_sale,
				'type'    => 'DECIMAL(18,4)',
				'compare' => '>=',
			);
		}

		if ( null !== $max_sale ) {
			$meta_query[] = array(
				'key'     => '_sale_price',
				'value'   => $max_sale,
				'type'    => 'DECIMAL(18,4)',
				'compare' => '<=',
			);
		}

		return count( $meta_query ) > 1 ? $meta_query : array();
	}

	/**
	 * Build TargetDto for a given product or variation ID using WooCommerce CRUD.
	 */
	public static function build_target_dto( int $id ): ?TargetDto {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $id );
		if ( ! $product instanceof \WC_Product ) {
			return null;
		}

		$type = $product->get_type();
		$parent_id = 0;
		$parent_title = null;
		$attributes_summary = '';

		if ( $product instanceof \WC_Product_Variation ) {
			$type = 'variation';
			$parent_id = $product->get_parent_id();
			$parent_title = get_the_title( $parent_id );

			// Format variation attributes (e.g. Size: Large, Color: Blue)
			$formatted_attributes = array();
			$attributes = $product->get_attributes();
			foreach ( $attributes as $attr_key => $attr_value ) {
				$label = wc_attribute_label( $attr_key, $product );
				$formatted_attributes[] = "{$label}: {$attr_value}";
			}
			$attributes_summary = implode( ', ', $formatted_attributes );
		}

		$regular_price = '' !== $product->get_regular_price() ? (float) $product->get_regular_price() : null;
		$sale_price    = '' !== $product->get_sale_price() ? (float) $product->get_sale_price() : null;

		return new TargetDto(
			id: $product->get_id(),
			parent_id: $parent_id,
			title: $product->get_name(),
			parent_title: $parent_title,
			sku: $product->get_sku() ?: '',
			type: $type,
			regular_price: $regular_price,
			sale_price: $sale_price,
			stock_status: $product->get_stock_status() ?: 'instock',
			status: $product->get_status() ?: 'publish',
			attributes_summary: $attributes_summary
		);
	}

	/**
	 * Retrieve product categories list for filter dropdown.
	 *
	 * @return array<int, string>
	 */
	public static function get_categories(): array {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$options = array();
		foreach ( $terms as $term ) {
			$options[ (int) $term->term_id ] = $term->name . ' (' . $term->count . ')';
		}
		return $options;
	}

	/**
	 * Retrieve product tags list for filter dropdown.
	 *
	 * @return array<int, string>
	 */
	public static function get_tags(): array {
		if ( ! taxonomy_exists( 'product_tag' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$options = array();
		foreach ( $terms as $term ) {
			$options[ (int) $term->term_id ] = $term->name;
		}
		return $options;
	}
}
