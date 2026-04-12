<?php
/**
 * Theme setup, assets, and baseline WooCommerce support.
 *
 * @package Velune
 */

function velune_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'script',
			'style',
		)
	);

	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'velune' ),
			'footer'  => __( 'Footer Menu', 'velune' ),
		)
	);
}
add_action( 'after_setup_theme', 'velune_setup' );

/**
 * Disable default WooCommerce stylesheet output.
 *
 * @return array<string, array>
 */
function velune_disable_woocommerce_styles() {
	return array();
}
add_filter( 'woocommerce_enqueue_styles', 'velune_disable_woocommerce_styles' );

/**
 * Refine single product hooks for custom template output.
 */
function velune_refine_single_product_hooks() {
	if ( ! velune_is_woocommerce_active() ) {
		return;
	}

	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );

	add_action( 'woocommerce_single_product_summary', 'velune_render_single_product_actions', 30 );
	add_action( 'woocommerce_single_product_summary', 'velune_render_single_product_meta', 40 );
}
add_action( 'wp', 'velune_refine_single_product_hooks' );

/**
 * Disable single product gallery zoom/lightbox behavior.
 *
 * @return bool
 */
function velune_disable_single_product_zoom() {
	return false;
}
add_filter( 'woocommerce_single_product_zoom_enabled', 'velune_disable_single_product_zoom' );
add_filter( 'woocommerce_single_product_photoswipe_enabled', 'velune_disable_single_product_zoom' );

/**
 * Keep related products concise in the single product footer.
 *
 * @param array<string, mixed> $args Related products args.
 * @return array<string, mixed>
 */
function velune_related_products_args( $args ) {
	$args['posts_per_page'] = 3;
	$args['columns']        = 3;

	return $args;
}
add_filter( 'woocommerce_output_related_products_args', 'velune_related_products_args' );

/**
 * Get meaningful product categories excluding the default "Uncategorized".
 *
 * @param int $product_id Product ID.
 * @return WP_Term[]
 */
function velune_get_meaningful_product_categories( $product_id ) {
	$product_id = (int) $product_id;

	if ( $product_id <= 0 ) {
		return array();
	}

	$terms = get_the_terms( $product_id, 'product_cat' );

	if ( ! is_array( $terms ) || empty( $terms ) ) {
		return array();
	}

	$default_term_id = (int) get_option( 'default_product_cat', 0 );

	return array_values(
		array_filter(
			$terms,
			static function ( $term ) use ( $default_term_id ) {
				if ( ! ( $term instanceof WP_Term ) ) {
					return false;
				}

				if ( $default_term_id > 0 && (int) $term->term_id === $default_term_id ) {
					return false;
				}

				return 'uncategorized' !== sanitize_title( $term->slug );
			}
		)
	);
}

/**
 * Get current cart quantity for a specific product.
 *
 * @param int $product_id Product ID.
 * @return int
 */
function velune_get_cart_quantity_for_product( $product_id ) {
	$product_id = (int) $product_id;

	if ( $product_id <= 0 ) {
		return 0;
	}

	$items = velune_get_cart_items_by_product();
	$key   = (string) $product_id;

	if ( empty( $items[ $key ]['quantity'] ) ) {
		return 0;
	}

	return max( 0, (int) $items[ $key ]['quantity'] );
}

/**
 * Render curated single-product meta output.
 */
function velune_render_single_product_meta() {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$categories = velune_get_meaningful_product_categories( $product->get_id() );

	if ( empty( $categories ) ) {
		return;
	}

	$links = array_map(
		static function ( $term ) {
			$term_link = get_term_link( $term );

			if ( is_wp_error( $term_link ) ) {
				return '';
			}

			return sprintf(
				'<a href="%1$s" rel="tag">%2$s</a>',
				esc_url( $term_link ),
				esc_html( $term->name )
			);
		},
		$categories
	);

	$links = array_filter( $links );

	if ( empty( $links ) ) {
		return;
	}
	?>
	<div class="product_meta">
		<span class="posted_in">
			<?php esc_html_e( 'Category:', 'velune' ); ?>
			<?php echo wp_kses_post( implode( ', ', $links ) ); ?>
		</span>
	</div>
	<?php
}

/**
 * Render custom single product actions.
 */
function velune_render_single_product_actions() {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$product_id          = (int) $product->get_id();
	$cart_quantity       = velune_get_cart_quantity_for_product( $product_id );
	$is_simple_purchasable = $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock();

	if ( ! $is_simple_purchasable ) {
		woocommerce_template_single_add_to_cart();
		return;
	}
	?>
	<div class="velune-product-actions" data-product-actions data-product-id="<?php echo esc_attr( (string) $product_id ); ?>">
		<button
			type="button"
			class="button button--primary<?php echo $cart_quantity > 0 ? ' hidden' : ''; ?>"
			data-product-add
			data-add-to-cart="<?php echo esc_attr( (string) $product_id ); ?>"
			data-open-cart="false"
		>
			<?php esc_html_e( 'Add to cart', 'velune' ); ?>
		</button>

		<div class="qty-control<?php echo $cart_quantity > 0 ? '' : ' hidden'; ?>" data-product-qty-control>
			<button type="button" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>" data-product-change="-1" aria-label="<?php esc_attr_e( 'Decrease quantity', 'velune' ); ?>">−</button>
			<span data-product-qty-value><?php echo esc_html( (string) max( 0, $cart_quantity ) ); ?></span>
			<button type="button" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>" data-product-change="1" aria-label="<?php esc_attr_e( 'Increase quantity', 'velune' ); ?>">+</button>
		</div>

		<button
			type="button"
			class="button button--secondary velune-buy-now"
			data-buy-now="<?php echo esc_attr( (string) $product_id ); ?>"
		>
			<?php esc_html_e( 'Buy now', 'velune' ); ?>
		</button>
	</div>
	<?php
}

/**
 * Enqueue shared assets.
 */
function velune_enqueue_assets() {
	$theme = wp_get_theme();

	wp_enqueue_style(
		'velune-fonts',
		'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'velune-main',
		get_theme_file_uri( '/assets/css/main.css' ),
		array( 'velune-fonts' ),
		filemtime( get_theme_file_path( '/assets/css/main.css' ) )
	);

	wp_enqueue_style(
		'velune-style',
		get_stylesheet_uri(),
		array( 'velune-main' ),
		$theme->get( 'Version' )
	);

	wp_enqueue_script(
		'velune-store',
		get_theme_file_uri( '/assets/js/store.js' ),
		array(),
		filemtime( get_theme_file_path( '/assets/js/store.js' ) ),
		true
	);

	wp_enqueue_script(
		'velune-main',
		get_theme_file_uri( '/assets/js/main.js' ),
		array( 'velune-store' ),
		filemtime( get_theme_file_path( '/assets/js/main.js' ) ),
		true
	);

	wp_localize_script(
		'velune-store',
		'veluneStoreConfig',
		array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'velune_cart_nonce' ),
			'cartUrl'      => velune_get_cart_url(),
			'checkoutUrl'  => velune_get_checkout_url(),
			'accountUrl'   => velune_get_account_url(),
			'isWooActive'  => velune_is_woocommerce_active(),
			'labels'       => array(
				'cartEmpty' => __( 'Your cart is empty', 'velune' ),
				'cartError' => __( 'Unable to update cart right now. Please try again.', 'velune' ),
			),
		)
	);

	wp_localize_script(
		'velune-main',
		'veluneThemeConfig',
		array(
			'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
			'homeUrl'            => home_url( '/' ),
			'searchNonce'        => wp_create_nonce( 'velune_search_nonce' ),
			'searchMinChars'     => 2,
			'searchLimit'        => 8,
			'searchHintLabel'    => __( 'Type at least 2 characters to search.', 'velune' ),
			'searchLoadingLabel' => __( 'Searching...', 'velune' ),
			'searchEmptyLabel'   => __( 'No matching results found.', 'velune' ),
			'searchErrorLabel'   => __( 'Unable to load search results right now.', 'velune' ),
			'searchViewAllLabel' => __( 'View full search results', 'velune' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'velune_enqueue_assets' );

/**
 * Check if WooCommerce is available.
 *
 * @return bool
 */
