<?php
/**
 * Authentication redirects, account bootstrap, and admin access rules.
 *
 * @package Velune
 */

function velune_redirect_non_admins_from_dashboard() {
	if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	global $pagenow;

	if ( in_array( $pagenow, array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php' ), true ) ) {
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
