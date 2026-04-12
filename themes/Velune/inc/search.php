<?php
/**
 * Search helpers and AJAX endpoints.
 *
 * @package Velune
 */

function velune_get_search_post_types() {
	$post_types = get_post_types(
		array(
			'public'              => true,
			'exclude_from_search' => false,
		),
		'names'
	);

	$excluded = array(
		'attachment',
		'product_variation',
		'nav_menu_item',
		'revision',
		'wp_template',
		'wp_template_part',
		'wp_navigation',
		'wp_global_styles',
		'wp_font_family',
		'wp_font_face',
	);

	$searchable_types = array_values( array_diff( $post_types, $excluded ) );

	if ( empty( $searchable_types ) ) {
		$searchable_types = array( 'post', 'page' );
	}

	$type_order = array(
		'product' => 0,
		'page'    => 1,
		'post'    => 2,
	);

	usort(
		$searchable_types,
		static function ( $left, $right ) use ( $type_order ) {
			$left_order  = isset( $type_order[ $left ] ) ? (int) $type_order[ $left ] : 10;
			$right_order = isset( $type_order[ $right ] ) ? (int) $type_order[ $right ] : 10;

			if ( $left_order === $right_order ) {
				return strcmp( (string) $left, (string) $right );
			}

			return $left_order <=> $right_order;
		}
	);

	return array_values( array_unique( $searchable_types ) );
}

/**
 * Get storefront label for a search post type.
 *
 * @param string $post_type Post type name.
 * @return string
 */
function velune_get_search_type_label( $post_type ) {
	switch ( $post_type ) {
		case 'product':
			return __( 'Product', 'velune' );
		case 'post':
			return __( 'Article', 'velune' );
		case 'page':
			return __( 'Page', 'velune' );
		default:
			$post_type_object = get_post_type_object( $post_type );
			if ( $post_type_object && ! empty( $post_type_object->labels->singular_name ) ) {
				return (string) $post_type_object->labels->singular_name;
			}
			return ucfirst( str_replace( array( '-', '_' ), ' ', (string) $post_type ) );
	}
}

/**
 * Get ranking priority by post type for search ordering.
 *
 * @param string $post_type Post type.
 * @return int
 */
function velune_get_search_type_priority( $post_type ) {
	switch ( $post_type ) {
		case 'product':
			return 0;
		case 'page':
			return 1;
		case 'post':
			return 2;
		default:
			return 3;
	}
}

/**
 * Normalize search text for relevance scoring.
 *
 * @param string $value Input text.
 * @return string
 */
function velune_normalize_search_text( $value ) {
	$text = trim( (string) $value );
	$text = wp_strip_all_tags( $text );
	$text = remove_accents( $text );

	if ( function_exists( 'mb_strtolower' ) ) {
		$text = mb_strtolower( $text, 'UTF-8' );
	} else {
		$text = strtolower( $text );
	}

	return preg_replace( '/\s+/', ' ', $text );
}

/**
 * Calculate a practical storefront relevance score for one post.
 *
 * @param WP_Post           $post             Post object.
 * @param string            $normalized_query Normalized query.
 * @param array<int,string> $tokens           Normalized query tokens.
 * @return int
 */
function velune_calculate_search_score( WP_Post $post, $normalized_query, $tokens ) {
	$title   = velune_normalize_search_text( get_the_title( $post ) );
	$excerpt = velune_normalize_search_text( has_excerpt( $post ) ? get_the_excerpt( $post ) : '' );
	$content = velune_normalize_search_text( $post->post_content );
	$score   = 0;

	if ( '' === $normalized_query ) {
		return 0;
	}

	if ( $title === $normalized_query ) {
		$score += 140;
	} elseif ( 0 === strpos( $title, $normalized_query ) ) {
		$score += 100;
	} elseif ( false !== strpos( $title, $normalized_query ) ) {
		$score += 74;
	}

	if ( false !== strpos( $excerpt, $normalized_query ) ) {
		$score += 24;
	}

	if ( false !== strpos( $content, $normalized_query ) ) {
		$score += 16;
	}

	foreach ( $tokens as $token ) {
		if ( '' === $token || strlen( $token ) < 2 ) {
			continue;
		}

		$token_pattern = '/\b' . preg_quote( $token, '/' ) . '\b/u';

		if ( preg_match( $token_pattern, $title ) ) {
			$score += 18;
		} elseif ( false !== strpos( $title, $token ) ) {
			$score += 11;
		}

		if ( false !== strpos( $excerpt, $token ) ) {
			$score += 6;
		}

		if ( false !== strpos( $content, $token ) ) {
			$score += 4;
		}
	}

	if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( $post->ID );

		if ( $product ) {
			$sku = velune_normalize_search_text( (string) $product->get_sku() );

			if ( '' !== $sku ) {
				if ( $sku === $normalized_query ) {
					$score += 130;
				} elseif ( false !== strpos( $sku, $normalized_query ) ) {
					$score += 90;
				}
			}
		}

		$product_terms = wp_get_post_terms( $post->ID, array( 'product_cat', 'product_tag' ), array( 'fields' => 'names' ) );

		if ( ! is_wp_error( $product_terms ) && ! empty( $product_terms ) ) {
			$taxonomy_text = velune_normalize_search_text( implode( ' ', $product_terms ) );

			if ( false !== strpos( $taxonomy_text, $normalized_query ) ) {
				$score += 16;
			}

			foreach ( $tokens as $token ) {
				if ( '' !== $token && false !== strpos( $taxonomy_text, $token ) ) {
					$score += 4;
				}
			}
		}
	}

	return $score;
}

/**
 * Build grouped live-search response data.
 *
 * @param string $query Query text.
 * @param int    $limit Result limit.
 * @return array<string,mixed>
 */
function velune_build_live_search_results( $query, $limit = 8 ) {
	$normalized_query = velune_normalize_search_text( $query );
	$limit            = max( 3, min( 10, (int) $limit ) );

	if ( '' === $normalized_query || strlen( $normalized_query ) < 2 ) {
		return array(
			'query'      => $query,
			'search_url' => get_search_link( $query ),
			'groups'     => array(),
		);
	}

	$tokens          = array_values( array_filter( explode( ' ', $normalized_query ) ) );
	$post_types      = velune_get_search_post_types();
	$candidate_limit = max( 20, $limit * 4 );

	$base_query = new WP_Query(
		array(
			'post_type'           => $post_types,
			'post_status'         => 'publish',
			'posts_per_page'      => $candidate_limit,
			's'                   => $query,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	$candidates = $base_query->posts;

	if ( in_array( 'product', $post_types, true ) && velune_is_woocommerce_active() ) {
		$sku_query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => $candidate_limit,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_sku',
						'value'   => $query,
						'compare' => 'LIKE',
					),
				),
			)
		);

		if ( ! empty( $sku_query->posts ) ) {
			$existing_ids = wp_list_pluck( $candidates, 'ID' );

			foreach ( $sku_query->posts as $product_id ) {
				$product_id = (int) $product_id;

				if ( in_array( $product_id, $existing_ids, true ) ) {
					continue;
				}

				$product_post = get_post( $product_id );

				if ( $product_post instanceof WP_Post ) {
					$candidates[]  = $product_post;
					$existing_ids[] = $product_id;
				}
			}
		}
	}

	$ranked_results = array();

	foreach ( $candidates as $candidate ) {
		if ( ! ( $candidate instanceof WP_Post ) ) {
			continue;
		}

		$post_type = get_post_type( $candidate );
		$score     = velune_calculate_search_score( $candidate, $normalized_query, $tokens );

		if ( $score <= 0 || ! $post_type ) {
			continue;
		}

		$thumbnail_url = '';

		if ( 'product' === $post_type && has_post_thumbnail( $candidate ) ) {
			$thumbnail_url = get_the_post_thumbnail_url( $candidate, 'woocommerce_thumbnail' );
		}

		$price = '';

		if ( 'product' === $post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $candidate->ID );
			if ( $product ) {
				$price = velune_get_live_search_price_text( $product->get_price_html() );
			}
		}

		$ranked_results[] = array(
			'id'          => (int) $candidate->ID,
			'title'       => get_the_title( $candidate ),
			'url'         => get_permalink( $candidate ),
			'post_type'   => $post_type,
			'type_label'  => velune_get_search_type_label( $post_type ),
			'thumbnail'   => $thumbnail_url ? esc_url_raw( $thumbnail_url ) : '',
			'price'       => $price,
			'score'       => (int) $score,
			'type_weight' => velune_get_search_type_priority( $post_type ),
		);
	}

	usort(
		$ranked_results,
		static function ( $left, $right ) {
			$score_compare = (int) $right['score'] <=> (int) $left['score'];

			if ( 0 !== $score_compare ) {
				return $score_compare;
			}

			$type_compare = (int) $left['type_weight'] <=> (int) $right['type_weight'];

			if ( 0 !== $type_compare ) {
				return $type_compare;
			}

			return strcmp( (string) $left['title'], (string) $right['title'] );
		}
	);

	$ranked_results = array_slice( $ranked_results, 0, $limit );
	$grouped        = array();

	foreach ( $ranked_results as $result ) {
		$post_type = $result['post_type'];

		if ( ! isset( $grouped[ $post_type ] ) ) {
			$grouped[ $post_type ] = array(
				'type'  => $post_type,
				'label' => velune_get_search_type_label( $post_type ),
				'items' => array(),
			);
		}

		$grouped[ $post_type ]['items'][] = array(
			'id'         => $result['id'],
			'title'      => $result['title'],
			'url'        => $result['url'],
			'type_label' => $result['type_label'],
			'thumbnail'  => $result['thumbnail'],
			'price'      => $result['price'],
		);
	}

	return array(
		'query'      => $query,
		'search_url' => get_search_link( $query ),
		'groups'     => array_values( $grouped ),
	);
}

/**
 * Normalize WooCommerce price HTML into safe, display-ready text for live search.
 *
 * @param string $price_html WooCommerce price HTML.
 * @return string
 */
function velune_get_live_search_price_text( $price_html ) {
	$price_text = wp_strip_all_tags( (string) $price_html );

	if ( '' === $price_text ) {
		return '';
	}

	$blog_charset = get_bloginfo( 'charset' );
	$charset      = $blog_charset ? $blog_charset : 'UTF-8';
	$price_text   = html_entity_decode( $price_text, ENT_QUOTES, $charset );
	$price_text   = preg_replace( '/\x{00A0}/u', ' ', $price_text );
	$price_text   = preg_replace( '/\s+/u', ' ', $price_text );

	return trim( (string) $price_text );
}

/**
 * AJAX: return grouped live search results.
 */
function velune_ajax_live_search() {
	if ( ! check_ajax_referer( 'velune_search_nonce', 'nonce', false ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Security check failed.', 'velune' ),
			),
			403
		);
	}

	$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
	$limit = isset( $_POST['limit'] ) ? absint( wp_unslash( $_POST['limit'] ) ) : 8;

	wp_send_json_success( velune_build_live_search_results( $query, $limit ) );
}
add_action( 'wp_ajax_velune_live_search', 'velune_ajax_live_search' );
add_action( 'wp_ajax_nopriv_velune_live_search', 'velune_ajax_live_search' );

/**
 * Extend default search page query to include storefront post types.
 *
 * @param WP_Query $query Query object.
 */
function velune_extend_main_search_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}

	if ( ! $query->get( 'post_type' ) ) {
		$query->set( 'post_type', velune_get_search_post_types() );
	}

	$query->set( 'ignore_sticky_posts', true );
}
add_action( 'pre_get_posts', 'velune_extend_main_search_query' );

/**
 * Get WooCommerce cart object.
 *
 * @return WC_Cart|null
 */
