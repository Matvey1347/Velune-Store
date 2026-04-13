<?php
/**
 * Shared helpers for URLs, identity, and navigation.
 *
 * @package Velune
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
	if ( velune_is_woocommerce_active() && function_exists( 'wc_get_account_endpoint_url' ) ) {
		$orders_url = wc_get_account_endpoint_url( 'orders' );

		if ( $orders_url ) {
			return $orders_url;
		}
	}

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
 * Get homepage anchor URL for subscription section.
 *
 * @return string
 */
function velune_get_subscription_anchor_url() {
	return home_url( '/#subscription' );
}

/**
 * Get currently active subscription plan from the Stripe plans repository.
 *
 * @return array<string,mixed>|null
 */
function velune_get_active_subscription_plan() {
	if ( ! class_exists( '\\WPStripePayments\\Subscriptions\\PlanRepository' ) ) {
		return null;
	}

	$plan_repository = new \WPStripePayments\Subscriptions\PlanRepository();
	$active_plans    = $plan_repository->getActivePlans();

	if ( ! empty( $active_plans[0] ) && is_array( $active_plans[0] ) ) {
		return $active_plans[0];
	}

	return null;
}

/**
 * Resolve bundle media image and alt text for subscription sections.
 *
 * @param array<string,mixed>|null $active_subscription_plan Active plan data.
 * @return array<string,string>
 */
function velune_get_subscription_bundle_media( $active_subscription_plan ) {
	$bundle_media_image = get_theme_file_uri( '/assets/images/bundle/bundle.webp' );
	$bundle_media_alt   = __( 'VELUNE skincare bundle set', 'velune' );

	if ( is_array( $active_subscription_plan ) && ! empty( $active_subscription_plan['image'] ) ) {
		$bundle_media_image = (string) $active_subscription_plan['image'];
		$bundle_media_alt   = sprintf(
			/* translators: %s: subscription plan title */
			__( '%s subscription plan image', 'velune' ),
			(string) $active_subscription_plan['title']
		);
	}

	return array(
		'image' => $bundle_media_image,
		'alt'   => $bundle_media_alt,
	);
}

/**
 * Read and map subscription checkout error message from query args.
 *
 * @return string
 */
function velune_get_subscription_checkout_error_message() {
	if ( ! isset( $_GET['wp_sp_sub_error'] ) ) {
		return '';
	}

	$error_code = sanitize_key( (string) wp_unslash( $_GET['wp_sp_sub_error'] ) );

	switch ( $error_code ) {
		case 'missing_email':
			return __( 'Please provide an email address to continue.', 'velune' );
		case 'checkout_failed':
			return __( 'Unable to start Stripe Checkout right now. Please try again.', 'velune' );
		case 'missing_redirect':
			return __( 'Stripe Checkout could not be opened. Please try again.', 'velune' );
		default:
			return __( 'Unable to start checkout. Please try again.', 'velune' );
	}
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
		$subscription_page_url   = velune_get_subscription_url();
		$subscription_anchor_url = velune_get_subscription_anchor_url();

		foreach ( $links as $index => $link ) {
			$url   = isset( $link['url'] ) ? (string) $link['url'] : '';
			$label = isset( $link['label'] ) ? (string) $link['label'] : '';

			if ( '' === $url ) {
				continue;
			}

			$normalized_url   = untrailingslashit( strtok( $url, '#' ) );
			$subscription_url = untrailingslashit( $subscription_page_url );
			$is_subscription  = ( '' !== $label && false !== stripos( $label, 'subscription' ) ) || ( '' !== $subscription_url && $normalized_url === $subscription_url );

			if ( $is_subscription ) {
				$links[ $index ]['url'] = $subscription_anchor_url;
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
			'url'   => velune_get_subscription_anchor_url(),
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
