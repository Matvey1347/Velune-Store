<?php
/**
 * Velune theme bootstrap.
 *
 * @package Velune
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme setup.
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
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
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
}
add_action( 'wp_enqueue_scripts', 'velune_enqueue_assets' );

/**
 * Check if WooCommerce is available.
 *
 * @return bool
 */
function velune_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Resolve a page URL by slug with fallback.
 *
 * @param string $slug     Page slug.
 * @param string $fallback Fallback URL.
 * @return string
 */
function velune_get_page_url_by_slug( $slug, $fallback = '' ) {
	$page = get_page_by_path( $slug );

	if ( $page instanceof WP_Post ) {
		return get_permalink( $page );
	}

	return $fallback ? $fallback : home_url( '/' . trim( $slug, '/' ) . '/' );
}

/**
 * Get blog archive URL.
 *
 * @return string
 */
function velune_get_blog_url() {
	$posts_page_id = (int) get_option( 'page_for_posts' );

	if ( $posts_page_id ) {
		return get_permalink( $posts_page_id );
	}

	return home_url( '/blog/' );
}

/**
 * Get shop URL.
 *
 * @return string
 */
function velune_get_shop_url() {
	if ( velune_is_woocommerce_active() && function_exists( 'wc_get_page_permalink' ) ) {
		$shop_url = wc_get_page_permalink( 'shop' );

		if ( $shop_url ) {
			return $shop_url;
		}
	}

	return home_url( '/shop/' );
}

/**
 * Get cart URL.
 *
 * @return string
 */
function velune_get_cart_url() {
	if ( velune_is_woocommerce_active() && function_exists( 'wc_get_cart_url' ) ) {
		return wc_get_cart_url();
	}

	return velune_get_page_url_by_slug( 'cart' );
}

/**
 * Get checkout URL.
 *
 * @return string
 */
function velune_get_checkout_url() {
	if ( velune_is_woocommerce_active() && function_exists( 'wc_get_checkout_url' ) ) {
		return wc_get_checkout_url();
	}

	return velune_get_page_url_by_slug( 'checkout' );
}

/**
 * Get My Account URL.
 *
 * @return string
 */
function velune_get_account_url() {
	if ( velune_is_woocommerce_active() && function_exists( 'wc_get_page_permalink' ) ) {
		$account_url = wc_get_page_permalink( 'myaccount' );

		if ( $account_url ) {
			return $account_url;
		}
	}

	return velune_get_page_url_by_slug( 'account' );
}

/**
 * Get first uppercase character from a text string.
 *
 * @param string $value Input string.
 * @return string
 */
function velune_get_first_character( $value ) {
	$value = trim( wp_strip_all_tags( (string) $value ) );

	if ( '' === $value ) {
		return '';
	}

	if ( function_exists( 'mb_substr' ) ) {
		$character = mb_substr( $value, 0, 1, 'UTF-8' );

		if ( function_exists( 'mb_strtoupper' ) ) {
			return mb_strtoupper( $character, 'UTF-8' );
		}

		return strtoupper( $character );
	}

	return strtoupper( substr( $value, 0, 1 ) );
}

/**
 * Build account initials from user profile data.
 *
 * @param WP_User $user User object.
 * @return string
 */
function velune_get_user_initials( $user ) {
	if ( ! ( $user instanceof WP_User ) ) {
		return '';
	}

	$first_name = trim( (string) $user->first_name );
	$last_name  = trim( (string) $user->last_name );

	if ( '' !== $first_name ) {
		$initials = velune_get_first_character( $first_name );

		if ( '' !== $last_name ) {
			$initials .= velune_get_first_character( $last_name );
		}

		return $initials;
	}

	$fallback_name = trim( (string) $user->display_name );

	if ( '' === $fallback_name ) {
		$fallback_name = trim( (string) $user->user_login );
	}

	return velune_get_first_character( $fallback_name );
}

/**
 * Get user avatar URL if a real avatar exists.
 *
 * @param int $user_id User ID.
 * @param int $size    Avatar size in pixels.
 * @return string
 */
function velune_get_user_avatar_url( $user_id, $size = 40 ) {
	$user_id = (int) $user_id;

	if ( $user_id <= 0 ) {
		return '';
	}

	$avatar_data = get_avatar_data(
		$user_id,
		array(
			'size'          => max( 1, (int) $size ),
			'default'       => '404',
			'force_default' => false,
		)
	);

	if ( empty( $avatar_data['found_avatar'] ) || empty( $avatar_data['url'] ) ) {
		return '';
	}

	return esc_url_raw( $avatar_data['url'] );
}

/**
 * Get login page URL.
 *
 * @return string
 */
function velune_get_login_url() {
	return velune_get_page_url_by_slug( 'login', velune_get_account_url() );
}

/**
 * Get register page URL.
 *
 * @return string
 */
function velune_get_register_url() {
	return velune_get_page_url_by_slug( 'register', velune_get_account_url() );
}

/**
 * Get subscription page URL.
 *
 * @return string
 */
function velune_get_subscription_url() {
	return velune_get_page_url_by_slug( 'subscription' );
}

/**
 * Get forgot password URL.
 *
 * @return string
 */
function velune_get_forgot_password_url() {
	$custom_url = velune_get_page_url_by_slug( 'forgot-password', '' );

	if ( $custom_url ) {
		return $custom_url;
	}

	if ( velune_is_woocommerce_active() && function_exists( 'wc_lostpassword_url' ) ) {
		return wc_lostpassword_url();
	}

	return wp_lostpassword_url();
}

/**
 * Get reset password URL.
 *
 * @return string
 */
function velune_get_reset_password_url() {
	return velune_get_page_url_by_slug( 'reset-password', velune_get_forgot_password_url() );
}

/**
 * Get a product ID by slug.
 *
 * @param string $slug Product slug.
 * @return int
 */
function velune_get_product_id_by_slug( $slug ) {
	$product = get_page_by_path( $slug, OBJECT, 'product' );

	if ( $product instanceof WP_Post ) {
		return (int) $product->ID;
	}

	return 0;
}

/**
 * Get primary navigation links.
 *
 * @return array<int, array<string, string>>
 */
function velune_get_navigation_links() {
	$links = array();

	if ( has_nav_menu( 'primary' ) ) {
		$locations = get_nav_menu_locations();

		if ( ! empty( $locations['primary'] ) ) {
			$menu_items = wp_get_nav_menu_items( $locations['primary'] );

			if ( ! empty( $menu_items ) ) {
				foreach ( $menu_items as $menu_item ) {
					if ( empty( $menu_item->url ) || empty( $menu_item->title ) ) {
						continue;
					}

					$links[] = array(
						'url'   => $menu_item->url,
						'label' => $menu_item->title,
					);
				}
			}
		}
	}

	if ( ! empty( $links ) ) {
		return $links;
	}

	return array(
		array(
			'label' => __( 'Shop', 'velune' ),
			'url'   => home_url( '/#shop' ),
		),
		array(
			'label' => __( 'Subscription', 'velune' ),
			'url'   => velune_get_subscription_url(),
		),
		array(
			'label' => __( 'Ritual', 'velune' ),
			'url'   => home_url( '/#ritual' ),
		),
		array(
			'label' => __( 'Blog', 'velune' ),
			'url'   => velune_get_blog_url(),
		),
		array(
			'label' => __( 'Account', 'velune' ),
			'url'   => velune_get_account_url(),
		),
	);
}

/**
 * Echo navigation links.
 */
function velune_render_navigation_links() {
	$links = velune_get_navigation_links();

	foreach ( $links as $link ) {
		echo '<a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a>';
	}
}

/**
 * Get WooCommerce cart object.
 *
 * @return WC_Cart|null
 */
function velune_get_cart_instance() {
	if ( ! velune_is_woocommerce_active() || ! function_exists( 'WC' ) ) {
		return null;
	}

	if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
		wc_load_cart();
	}

	return WC()->cart;
}

/**
 * Get current cart count.
 *
 * @return int
 */
function velune_get_cart_count() {
	$cart = velune_get_cart_instance();

	if ( ! $cart ) {
		return 0;
	}

	return (int) $cart->get_cart_contents_count();
}

/**
 * Get current cart subtotal HTML.
 *
 * @return string
 */
function velune_get_cart_subtotal_html() {
	$cart = velune_get_cart_instance();

	if ( ! $cart ) {
		return function_exists( 'wc_price' ) ? wc_price( 0 ) : '$0.00';
	}

	return $cart->get_cart_subtotal();
}

/**
 * Get current cart total HTML.
 *
 * @return string
 */
function velune_get_cart_total_html() {
	$cart = velune_get_cart_instance();

	if ( ! $cart ) {
		return function_exists( 'wc_price' ) ? wc_price( 0 ) : '$0.00';
	}

	return $cart->get_total();
}

/**
 * Get current cart shipping total HTML.
 *
 * @return string
 */
function velune_get_cart_shipping_html() {
	$cart = velune_get_cart_instance();

	if ( ! $cart ) {
		return esc_html__( 'Free', 'velune' );
	}

	$shipping_total = (float) $cart->get_shipping_total() + (float) $cart->get_shipping_tax();

	if ( $shipping_total <= 0 ) {
		return esc_html__( 'Free', 'velune' );
	}

	return wc_price( $shipping_total );
}

/**
 * Get normalized cart quantities grouped by product ID.
 *
 * @return array<string, array<string, mixed>>
 */
function velune_get_cart_items_by_product() {
	$cart = velune_get_cart_instance();

	if ( ! $cart || $cart->is_empty() ) {
		return array();
	}

	$items = array();

	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
		$product_id = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
		$quantity   = isset( $cart_item['quantity'] ) ? max( 0, (int) $cart_item['quantity'] ) : 0;

		if ( ! $product_id || $quantity <= 0 ) {
			continue;
		}

		$product_key = (string) $product_id;

		if ( ! isset( $items[ $product_key ] ) ) {
			$items[ $product_key ] = array(
				'product_id'    => $product_id,
				'quantity'      => 0,
				'cart_item_key' => '',
			);
		}

		$items[ $product_key ]['quantity'] += $quantity;

		if ( '' === $items[ $product_key ]['cart_item_key'] ) {
			$items[ $product_key ]['cart_item_key'] = $cart_item_key;
		}
	}

	return $items;
}

/**
 * Render mini cart items HTML.
 *
 * @return string
 */
function velune_get_cart_items_html() {
	$cart = velune_get_cart_instance();

	if ( ! $cart || $cart->is_empty() ) {
		return '<div class="empty-state"><h3>' . esc_html__( 'Your cart is empty', 'velune' ) . '</h3><p>' . esc_html__( 'Add products to continue.', 'velune' ) . '</p></div>';
	}

	ob_start();

	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : false;

		if ( ! $product || ! $product->exists() ) {
			continue;
		}

		$quantity    = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
		$meta_text   = $product->get_attribute( 'pa_size' );
		$product_id  = $product->get_id();
		$line_total  = isset( $cart_item['line_total'] ) ? (float) $cart_item['line_total'] : (float) $product->get_price() * $quantity;
		$line_tax    = isset( $cart_item['line_tax'] ) ? (float) $cart_item['line_tax'] : 0.0;
		$line_amount = $line_total + $line_tax;

		if ( ! $meta_text ) {
			$meta_text = wp_trim_words( wp_strip_all_tags( $product->get_short_description() ), 8, '...' );
		}

		if ( ! $meta_text ) {
			$meta_text = esc_html__( 'Premium skincare essential', 'velune' );
		}
		?>
		<article class="cart-item" data-cart-item="<?php echo esc_attr( $cart_item_key ); ?>" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>">
			<div class="cart-item__media">
				<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ) ); ?>
			</div>
			<div class="cart-item__body">
				<h4>
					<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
						<?php echo esc_html( $product->get_name() ); ?>
					</a>
				</h4>
				<p><?php echo esc_html( $meta_text ); ?></p>
				<div class="cart-item__meta">
					<div class="qty-control">
						<button type="button" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>" data-change="-1" aria-label="<?php esc_attr_e( 'Decrease quantity', 'velune' ); ?>">−</button>
						<span><?php echo esc_html( (string) $quantity ); ?></span>
						<button type="button" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>" data-change="1" aria-label="<?php esc_attr_e( 'Increase quantity', 'velune' ); ?>">+</button>
					</div>
					<strong><?php echo wp_kses_post( wc_price( $line_amount ) ); ?></strong>
				</div>
				<button type="button" class="cart-remove" data-remove-item="<?php echo esc_attr( $cart_item_key ); ?>">
					<?php esc_html_e( 'Remove', 'velune' ); ?>
				</button>
			</div>
		</article>
		<?php
	}

	return trim( (string) ob_get_clean() );
}

/**
 * Get full cart drawer state.
 *
 * @return array<string, mixed>
 */
function velune_get_cart_state() {
	return array(
		'count'            => velune_get_cart_count(),
		'subtotal'         => velune_get_cart_subtotal_html(),
		'total'            => velune_get_cart_total_html(),
		'shipping'         => velune_get_cart_shipping_html(),
		'items_html'       => velune_get_cart_items_html(),
		'page_items_html'  => velune_get_cart_items_html(),
		'items_by_product' => velune_get_cart_items_by_product(),
		'cart_url'         => velune_get_cart_url(),
		'checkout'         => velune_get_checkout_url(),
	);
}

/**
 * Validate AJAX nonce.
 */
function velune_verify_cart_nonce() {
	if ( ! check_ajax_referer( 'velune_cart_nonce', 'nonce', false ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Security check failed.', 'velune' ),
			),
			403
		);
	}
}

/**
 * AJAX: return cart drawer state.
 */
function velune_ajax_get_cart() {
	velune_verify_cart_nonce();
	wp_send_json_success( velune_get_cart_state() );
}
add_action( 'wp_ajax_velune_get_cart', 'velune_ajax_get_cart' );
add_action( 'wp_ajax_nopriv_velune_get_cart', 'velune_ajax_get_cart' );

/**
 * AJAX: add product to cart.
 */
function velune_ajax_add_to_cart() {
	velune_verify_cart_nonce();

	$cart = velune_get_cart_instance();

	if ( ! $cart ) {
		wp_send_json_error( array( 'message' => __( 'Cart is unavailable.', 'velune' ) ), 500 );
	}

	$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
	$quantity   = isset( $_POST['quantity'] ) ? max( 1, absint( wp_unslash( $_POST['quantity'] ) ) ) : 1;

	if ( ! $product_id ) {
		wp_send_json_error( array( 'message' => __( 'Product is missing.', 'velune' ) ), 400 );
	}

	$product = wc_get_product( $product_id );

	if ( ! $product || ! $product->is_purchasable() ) {
		wp_send_json_error( array( 'message' => __( 'This product cannot be purchased right now.', 'velune' ) ), 400 );
	}

	$added = $cart->add_to_cart( $product_id, $quantity );

	if ( ! $added ) {
		$message = __( 'Could not add item to cart.', 'velune' );
		$errors  = wc_get_notices( 'error' );

		if ( ! empty( $errors[0]['notice'] ) ) {
			$message = wp_strip_all_tags( $errors[0]['notice'] );
		}

		wc_clear_notices();

		wp_send_json_error( array( 'message' => $message ), 400 );
	}

	$cart->calculate_totals();

	wp_send_json_success( velune_get_cart_state() );
}
add_action( 'wp_ajax_velune_add_to_cart', 'velune_ajax_add_to_cart' );
add_action( 'wp_ajax_nopriv_velune_add_to_cart', 'velune_ajax_add_to_cart' );

/**
 * AJAX: update cart item quantity.
 */
function velune_ajax_update_cart_item() {
	velune_verify_cart_nonce();

	$cart = velune_get_cart_instance();

	if ( ! $cart ) {
		wp_send_json_error( array( 'message' => __( 'Cart is unavailable.', 'velune' ) ), 500 );
	}

	$cart_item_key = isset( $_POST['cart_item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) ) : '';
	$quantity      = isset( $_POST['quantity'] ) ? max( 0, absint( wp_unslash( $_POST['quantity'] ) ) ) : 1;
	$cart_items    = $cart->get_cart();

	if ( ! $cart_item_key || ! isset( $cart_items[ $cart_item_key ] ) ) {
		wp_send_json_error( array( 'message' => __( 'Cart item not found.', 'velune' ) ), 404 );
	}

	if ( 0 === $quantity ) {
		$cart->remove_cart_item( $cart_item_key );
	} else {
		$cart->set_quantity( $cart_item_key, $quantity, true );
	}

	$cart->calculate_totals();

	wp_send_json_success( velune_get_cart_state() );
}
add_action( 'wp_ajax_velune_update_cart_item', 'velune_ajax_update_cart_item' );
add_action( 'wp_ajax_nopriv_velune_update_cart_item', 'velune_ajax_update_cart_item' );

/**
 * AJAX: remove cart item.
 */
function velune_ajax_remove_cart_item() {
	velune_verify_cart_nonce();

	$cart = velune_get_cart_instance();

	if ( ! $cart ) {
		wp_send_json_error( array( 'message' => __( 'Cart is unavailable.', 'velune' ) ), 500 );
	}

	$cart_item_key = isset( $_POST['cart_item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) ) : '';

	if ( ! $cart_item_key ) {
		wp_send_json_error( array( 'message' => __( 'Cart item key is missing.', 'velune' ) ), 400 );
	}

	$cart->remove_cart_item( $cart_item_key );
	$cart->calculate_totals();

	wp_send_json_success( velune_get_cart_state() );
}
add_action( 'wp_ajax_velune_remove_cart_item', 'velune_ajax_remove_cart_item' );
add_action( 'wp_ajax_nopriv_velune_remove_cart_item', 'velune_ajax_remove_cart_item' );

/**
 * AJAX: set product quantity (aggregated by product ID).
 */
function velune_ajax_set_product_quantity() {
	velune_verify_cart_nonce();

	$cart = velune_get_cart_instance();

	if ( ! $cart ) {
		wp_send_json_error( array( 'message' => __( 'Cart is unavailable.', 'velune' ) ), 500 );
	}

	$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
	$quantity   = isset( $_POST['quantity'] ) ? max( 0, absint( wp_unslash( $_POST['quantity'] ) ) ) : 0;

	if ( ! $product_id ) {
		wp_send_json_error( array( 'message' => __( 'Product is missing.', 'velune' ) ), 400 );
	}

	$matching_keys = array();

	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
		$cart_product_id = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;

		if ( $cart_product_id === $product_id ) {
			$matching_keys[] = $cart_item_key;
		}
	}

	if ( $quantity <= 0 ) {
		foreach ( $matching_keys as $matching_key ) {
			$cart->remove_cart_item( $matching_key );
		}

		$cart->calculate_totals();
		wp_send_json_success( velune_get_cart_state() );
	}

	if ( empty( $matching_keys ) ) {
		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_purchasable() ) {
			wp_send_json_error( array( 'message' => __( 'This product cannot be purchased right now.', 'velune' ) ), 400 );
		}

		$added = $cart->add_to_cart( $product_id, $quantity );

		if ( ! $added ) {
			wp_send_json_error( array( 'message' => __( 'Could not update cart quantity.', 'velune' ) ), 400 );
		}

		$cart->calculate_totals();
		wp_send_json_success( velune_get_cart_state() );
	}

	$primary_key = array_shift( $matching_keys );

	if ( $primary_key ) {
		$cart->set_quantity( $primary_key, $quantity, true );
	}

	foreach ( $matching_keys as $duplicate_key ) {
		$cart->remove_cart_item( $duplicate_key );
	}

	$cart->calculate_totals();

	wp_send_json_success( velune_get_cart_state() );
}
add_action( 'wp_ajax_velune_set_product_quantity', 'velune_ajax_set_product_quantity' );
add_action( 'wp_ajax_nopriv_velune_set_product_quantity', 'velune_ajax_set_product_quantity' );

/**
 * Redirect non-admin users away from wp-admin.
 */
function velune_redirect_non_admins_from_dashboard() {
	if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	global $pagenow;

	if ( in_array( $pagenow, array( 'admin-ajax.php', 'async-upload.php' ), true ) ) {
		return;
	}

	wp_safe_redirect( velune_get_account_url() );
	exit;
}
add_action( 'admin_init', 'velune_redirect_non_admins_from_dashboard' );

/**
 * Hide admin bar for customers.
 *
 * @param bool $show Admin bar visibility.
 * @return bool
 */
function velune_filter_admin_bar_visibility( $show ) {
	if ( current_user_can( 'manage_options' ) ) {
		return $show;
	}

	return false;
}
add_filter( 'show_admin_bar', 'velune_filter_admin_bar_visibility' );

/**
 * Redirect customer login to My Account page.
 *
 * @param string               $redirect_to           Redirect URL.
 * @param string               $requested_redirect_to Requested URL.
 * @param WP_User|WP_Error|mixed $user               User object.
 * @return string
 */
function velune_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
	if ( $user instanceof WP_User && ! user_can( $user, 'manage_options' ) ) {
		return velune_get_account_url();
	}

	return $redirect_to;
}
add_filter( 'login_redirect', 'velune_login_redirect', 10, 3 );

/**
 * Route lost password links to the frontend forgot password page.
 *
 * @param string $lostpassword_url Lost password URL.
 * @param string $redirect         Redirect URL.
 * @return string
 */
function velune_filter_lostpassword_url( $lostpassword_url, $redirect ) {
	return velune_get_forgot_password_url();
}
add_filter( 'lostpassword_url', 'velune_filter_lostpassword_url', 10, 2 );
