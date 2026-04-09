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
 * Ensure a published frontend auth page exists with a mapped template.
 *
 * @param string $slug          Page slug.
 * @param string $title         Page title.
 * @param string $page_template Page template file.
 * @return int
 */
function velune_ensure_frontend_auth_page( $slug, $title, $page_template ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );

	if ( $page instanceof WP_Post ) {
		if ( 'publish' !== get_post_status( $page->ID ) ) {
			wp_update_post(
				array(
					'ID'          => (int) $page->ID,
					'post_status' => 'publish',
				)
			);
		}

		if ( $page_template && $page_template !== get_post_meta( $page->ID, '_wp_page_template', true ) ) {
			update_post_meta( $page->ID, '_wp_page_template', $page_template );
		}

		return (int) $page->ID;
	}

	$created_page_id = wp_insert_post(
		array(
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => '',
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'meta_input'   => array(
				'_wp_page_template' => $page_template,
			),
		),
		true
	);

	if ( is_wp_error( $created_page_id ) ) {
		return 0;
	}

	return (int) $created_page_id;
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
 * Get user custom avatar attachment ID.
 *
 * @param int $user_id User ID.
 * @return int
 */
function velune_get_user_custom_avatar_id( $user_id ) {
	$user_id = (int) $user_id;

	if ( $user_id <= 0 ) {
		return 0;
	}

	return (int) get_user_meta( $user_id, 'velune_avatar_id', true );
}

/**
 * Get user custom avatar URL from media library.
 *
 * @param int $user_id User ID.
 * @param int $size    Avatar size in pixels.
 * @return string
 */
function velune_get_user_custom_avatar_url( $user_id, $size = 40 ) {
	$attachment_id = velune_get_user_custom_avatar_id( $user_id );

	if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
		return '';
	}

	$avatar_url = wp_get_attachment_image_url( $attachment_id, array( max( 1, (int) $size ), max( 1, (int) $size ) ) );

	if ( ! is_string( $avatar_url ) || '' === $avatar_url ) {
		return '';
	}

	return esc_url_raw( $avatar_url );
}

/**
 * Get fallback avatar URL if a real WP avatar exists.
 *
 * @param int $user_id User ID.
 * @param int $size    Avatar size in pixels.
 * @return string
 */
function velune_get_user_real_avatar_url( $user_id, $size = 40 ) {
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

	$avatar_url = esc_url_raw( $avatar_data['url'] );

	if ( '' === $avatar_url ) {
		return '';
	}

	$avatar_parts = wp_parse_url( $avatar_url );

	if ( ! empty( $avatar_parts['query'] ) ) {
		$query_args = array();
		wp_parse_str( $avatar_parts['query'], $query_args );

		$default_arg = '';

		if ( isset( $query_args['d'] ) ) {
			$default_arg = strtolower( (string) $query_args['d'] );
		} elseif ( isset( $query_args['default'] ) ) {
			$default_arg = strtolower( (string) $query_args['default'] );
		}

		if ( '404' === $default_arg || false !== strpos( $default_arg, 'd=404' ) ) {
			return '';
		}
	}

	$avatar_url_lower = strtolower( $avatar_url );

	if (
		false !== strpos( $avatar_url_lower, 'gravatar.com/avatar/' ) &&
		(
			false !== strpos( $avatar_url_lower, 'd=404' ) ||
			false !== strpos( $avatar_url_lower, 'default=404' ) ||
			false !== strpos( $avatar_url_lower, 'd%3d404' ) ||
			false !== strpos( $avatar_url_lower, 'default%3d404' )
		)
	) {
		return '';
	}

	return $avatar_url;
}

/**
 * Get user avatar URL if a real avatar exists.
 *
 * @param int $user_id User ID.
 * @param int $size    Avatar size in pixels.
 * @return string
 */
function velune_get_user_avatar_url( $user_id, $size = 40 ) {
	$custom_avatar_url = velune_get_user_custom_avatar_url( $user_id, $size );

	if ( '' !== $custom_avatar_url ) {
		return $custom_avatar_url;
	}

	return velune_get_user_real_avatar_url( $user_id, $size );
}

/**
 * Ensure edit-account form supports file uploads.
 */
function velune_add_account_avatar_form_encoding() {
	echo 'enctype="multipart/form-data"';
}
add_action( 'woocommerce_edit_account_form_tag', 'velune_add_account_avatar_form_encoding' );

/**
 * Render custom avatar editor in WooCommerce account details form.
 */
function velune_render_account_avatar_field() {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$current_user = wp_get_current_user();

	if ( ! ( $current_user instanceof WP_User ) || $current_user->ID <= 0 ) {
		return;
	}

	$avatar_url = velune_get_user_avatar_url( (int) $current_user->ID, 160 );
	$initials   = velune_get_user_initials( $current_user );
	?>
	<fieldset class="velune-account-avatar-fieldset">
		<legend><?php esc_html_e( 'Profile photo', 'velune' ); ?></legend>
		<label for="velune_avatar_file" class="velune-account-avatar-picker">
			<span class="velune-account-avatar-media" aria-hidden="true">
				<?php if ( $avatar_url ) : ?>
					<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" width="96" height="96" loading="lazy" decoding="async">
				<?php else : ?>
					<span class="velune-account-avatar-initials"><?php echo esc_html( $initials ? $initials : 'U' ); ?></span>
				<?php endif; ?>
				<span class="velune-account-avatar-overlay"><?php esc_html_e( 'Change photo', 'velune' ); ?></span>
			</span>
			<span class="velune-account-avatar-meta">
				<span class="velune-account-avatar-title"><?php esc_html_e( 'Update your avatar', 'velune' ); ?></span>
				<span class="velune-account-avatar-help"><?php esc_html_e( 'Accepted: JPG, PNG, GIF, WebP, AVIF.', 'velune' ); ?></span>
			</span>
		</label>
		<input type="file" name="velune_avatar_file" id="velune_avatar_file" accept="image/*">
	</fieldset>
	<?php
}
add_action( 'woocommerce_edit_account_form_start', 'velune_render_account_avatar_field' );

/**
 * Handle avatar upload during WooCommerce account details save.
 *
 * @param int $user_id User ID.
 */
function velune_handle_account_avatar_upload( $user_id ) {
	$user_id = (int) $user_id;

	if ( $user_id <= 0 || ! is_user_logged_in() || (int) get_current_user_id() !== $user_id ) {
		return;
	}

	if ( empty( $_FILES['velune_avatar_file'] ) || ! is_array( $_FILES['velune_avatar_file'] ) ) {
		return;
	}

	$upload = $_FILES['velune_avatar_file'];

	if ( empty( $upload['name'] ) || ( isset( $upload['error'] ) && UPLOAD_ERR_NO_FILE === (int) $upload['error'] ) ) {
		return;
	}

	if ( ! empty( $upload['error'] ) ) {
		wc_add_notice( __( 'We could not upload your profile photo. Please try again.', 'velune' ), 'error' );
		return;
	}

	$tmp_name = isset( $upload['tmp_name'] ) ? (string) $upload['tmp_name'] : '';
	$file_name = isset( $upload['name'] ) ? (string) $upload['name'] : '';

	if ( '' === $tmp_name || '' === $file_name ) {
		wc_add_notice( __( 'Please choose a valid image file.', 'velune' ), 'error' );
		return;
	}

	$filetype = wp_check_filetype_and_ext( $tmp_name, $file_name );
	$mime     = isset( $filetype['type'] ) ? (string) $filetype['type'] : '';

	if ( '' === $mime || 0 !== strpos( $mime, 'image/' ) ) {
		wc_add_notice( __( 'Only image files can be used for profile photos.', 'velune' ), 'error' );
		return;
	}

	if ( ! function_exists( 'media_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$overrides     = array(
		'test_form' => false,
		'mimes'     => array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'gif'          => 'image/gif',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
			'avif'         => 'image/avif',
		),
	);
	$attachment_id = media_handle_upload( 'velune_avatar_file', 0, array(), $overrides );

	if ( is_wp_error( $attachment_id ) ) {
		wc_add_notice( $attachment_id->get_error_message(), 'error' );
		return;
	}

	update_user_meta( $user_id, 'velune_avatar_id', (int) $attachment_id );
	wc_add_notice( __( 'Profile photo updated.', 'velune' ), 'success' );
}
add_action( 'woocommerce_save_account_details', 'velune_handle_account_avatar_upload', 20 );

/**
 * Get login page URL.
 *
 * @return string
 */
function velune_get_login_url() {
	$page = get_page_by_path( 'login' );

	if ( $page instanceof WP_Post && 'publish' === get_post_status( $page->ID ) ) {
		return get_permalink( $page );
	}

	return home_url( '/login/' );
}

/**
 * Get register page URL.
 *
 * @return string
 */
function velune_get_register_url() {
	$page = get_page_by_path( 'register' );

	if ( $page instanceof WP_Post && 'publish' === get_post_status( $page->ID ) ) {
		return get_permalink( $page );
	}

	return home_url( '/register/' );
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
	$page = get_page_by_path( 'forgot-password' );

	if ( $page instanceof WP_Post && 'publish' === get_post_status( $page->ID ) ) {
		return get_permalink( $page );
	}

	return home_url( '/forgot-password/' );
}

/**
 * Get reset password URL.
 *
 * @return string
 */
function velune_get_reset_password_url() {
	return velune_get_forgot_password_url();
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
 * Get searchable post types for storefront search.
 *
 * @return array<int, string>
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
 * Resolve and ensure a published My Account page on /my-account/.
 *
 * @return int
 */
function velune_get_or_create_wc_my_account_page_id() {
	$candidate_page = get_page_by_path( 'my-account' );

	if ( $candidate_page instanceof WP_Post && 'publish' === get_post_status( $candidate_page->ID ) ) {
		return (int) $candidate_page->ID;
	}

	$my_account_page_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'myaccount' ) : 0;

	if ( $my_account_page_id > 0 && 'publish' === get_post_status( $my_account_page_id ) ) {
		return $my_account_page_id;
	}

	$candidate_page = get_page_by_path( 'myaccount' );

	if ( $candidate_page instanceof WP_Post && 'publish' === get_post_status( $candidate_page->ID ) ) {
		return (int) $candidate_page->ID;
	}

	$created_page_id = wp_insert_post(
		array(
			'post_title'   => __( 'My account', 'velune' ),
			'post_name'    => 'my-account',
			'post_content' => '[woocommerce_my_account]',
			'post_type'    => 'page',
			'post_status'  => 'publish',
		),
		true
	);

	if ( ! is_wp_error( $created_page_id ) ) {
		return (int) $created_page_id;
	}

	return 0;
}

/**
 * Ensure frontend auth pages exist and are template-mapped.
 */
function velune_bootstrap_frontend_auth_pages() {
	$auth_pages = array(
		'login'           => array(
			'title'    => __( 'Login', 'velune' ),
			'template' => 'page-login.php',
		),
		'register'        => array(
			'title'    => __( 'Register', 'velune' ),
			'template' => 'page-register.php',
		),
		'forgot-password' => array(
			'title'    => __( 'Forgot password', 'velune' ),
			'template' => 'page-forgot-password.php',
		),
		'reset-password'  => array(
			'title'    => __( 'Reset password', 'velune' ),
			'template' => 'page-reset-password.php',
		),
	);

	foreach ( $auth_pages as $slug => $settings ) {
		velune_ensure_frontend_auth_page( $slug, $settings['title'], $settings['template'] );
	}
}
add_action( 'init', 'velune_bootstrap_frontend_auth_pages', 98 );

/**
 * Ensure WooCommerce account auth settings are always aligned with the theme auth flow.
 */
function velune_bootstrap_woocommerce_auth() {
	if ( ! velune_is_woocommerce_active() ) {
		return;
	}

	$did_update         = false;
	$my_account_page_id = velune_get_or_create_wc_my_account_page_id();

	if ( $my_account_page_id > 0 && (int) get_option( 'woocommerce_myaccount_page_id' ) !== $my_account_page_id ) {
		update_option( 'woocommerce_myaccount_page_id', $my_account_page_id );
		$did_update = true;
	}

	if ( 'yes' !== get_option( 'woocommerce_enable_myaccount_registration', 'yes' ) ) {
		update_option( 'woocommerce_enable_myaccount_registration', 'yes' );
		$did_update = true;
	}

	if ( 'lost-password' !== get_option( 'woocommerce_myaccount_lost_password_endpoint', 'lost-password' ) ) {
		update_option( 'woocommerce_myaccount_lost_password_endpoint', 'lost-password' );
		$did_update = true;
	}

	if ( 'orders' !== get_option( 'woocommerce_myaccount_orders_endpoint', 'orders' ) ) {
		update_option( 'woocommerce_myaccount_orders_endpoint', 'orders' );
		$did_update = true;
	}

	if ( 'edit-account' !== get_option( 'woocommerce_myaccount_edit_account_endpoint', 'edit-account' ) ) {
		update_option( 'woocommerce_myaccount_edit_account_endpoint', 'edit-account' );
		$did_update = true;
	}

	if ( 'no' !== get_option( 'woocommerce_registration_generate_password', 'no' ) ) {
		update_option( 'woocommerce_registration_generate_password', 'no' );
		$did_update = true;
	}

	if ( $did_update ) {
		flush_rewrite_rules( false );
	}
}
add_action( 'init', 'velune_bootstrap_woocommerce_auth', 99 );

/**
 * Redirect legacy custom auth pages to WooCommerce My Account endpoints.
 */
function velune_redirect_legacy_auth_pages() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}

	if ( ! is_page() ) {
		return;
	}

	$current_page = get_queried_object();

	if ( ! ( $current_page instanceof WP_Post ) ) {
		return;
	}

	$slug_map = array(
		'account'         => velune_get_account_url(),
		'myaccount'       => velune_get_account_url(),
		'reset-password'  => velune_get_reset_password_url(),
	);

	if ( isset( $slug_map[ $current_page->post_name ] ) ) {
		$target_url = $slug_map[ $current_page->post_name ];
		$query_keys = array( 'key', 'id', 'login', 'show-reset-form', 'action', 'reset-link-sent' );
		$query_args = array();

		foreach ( $query_keys as $query_key ) {
			if ( isset( $_GET[ $query_key ] ) ) {
				$query_args[ $query_key ] = sanitize_text_field( wp_unslash( $_GET[ $query_key ] ) );
			}
		}

		if ( ! empty( $query_args ) ) {
			$target_url = add_query_arg( $query_args, $target_url );
		}

		wp_safe_redirect( $target_url, 301 );
		exit;
	}
}
add_action( 'template_redirect', 'velune_redirect_legacy_auth_pages', 1 );

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
