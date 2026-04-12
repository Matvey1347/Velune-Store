<?php
/**
 * Cart helpers and AJAX cart actions.
 *
 * @package Velune
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
 * AJAX: create instant buy-now checkout URL for a single product.
 */
function velune_ajax_buy_now() {
	velune_verify_cart_nonce();

	if ( ! function_exists( 'wc_create_order' ) ) {
		wp_send_json_error( array( 'message' => __( 'Checkout is unavailable right now.', 'velune' ) ), 500 );
	}

	$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
	$quantity   = isset( $_POST['quantity'] ) ? max( 1, absint( wp_unslash( $_POST['quantity'] ) ) ) : 1;

	if ( ! $product_id ) {
		wp_send_json_error( array( 'message' => __( 'Product is missing.', 'velune' ) ), 400 );
	}

	$product = wc_get_product( $product_id );

	if ( ! $product || ! $product->is_type( 'simple' ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		wp_send_json_error( array( 'message' => __( 'This product is not available for instant checkout.', 'velune' ) ), 400 );
	}

	try {
		$order = wc_create_order(
			array(
				'customer_id' => get_current_user_id(),
				'created_via' => 'velune_buy_now',
			)
		);

		if ( is_wp_error( $order ) || ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'Could not prepare checkout.', 'velune' ) ), 500 );
		}

		$order->add_product( $product, $quantity );
		$order->calculate_totals();

		$redirect_url = $order->get_checkout_payment_url( true );

		if ( ! $redirect_url ) {
			wp_send_json_error( array( 'message' => __( 'Could not start checkout.', 'velune' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'redirect_url' => esc_url_raw( $redirect_url ),
			)
		);
	} catch ( Exception $exception ) {
		wp_send_json_error( array( 'message' => __( 'Could not prepare instant checkout.', 'velune' ) ), 500 );
	}
}
add_action( 'wp_ajax_velune_buy_now', 'velune_ajax_buy_now' );
add_action( 'wp_ajax_nopriv_velune_buy_now', 'velune_ajax_buy_now' );

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
