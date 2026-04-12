<?php
/**
 * My Account page template.
 *
 * @package Velune
 */

if ( ! is_user_logged_in() ) {
	$query_keys = array( 'key', 'id', 'login', 'show-reset-form', 'action', 'reset-link-sent' );
	$query_args = array();

	foreach ( $query_keys as $query_key ) {
		if ( isset( $_GET[ $query_key ] ) ) {
			$query_args[ $query_key ] = sanitize_text_field( wp_unslash( $_GET[ $query_key ] ) );
		}
	}

	if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'lost-password' ) || is_wc_endpoint_url( 'reset-password' ) ) ) {
		$target_url = velune_get_forgot_password_url();

		if ( ! empty( $query_args ) ) {
			$target_url = add_query_arg( $query_args, $target_url );
		}
	} else {
		$target_url = velune_get_login_url();
	}

	$current_request = isset( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->request ) ? (string) $GLOBALS['wp']->request : '';
	$current_path    = wp_parse_url( home_url( '/' . ltrim( $current_request, '/' ) ), PHP_URL_PATH );
	$target_path     = wp_parse_url( $target_url, PHP_URL_PATH );

	if ( $current_path && $target_path && untrailingslashit( $current_path ) === untrailingslashit( $target_path ) ) {
		$target_url = home_url( '/login/' );

		if ( ! empty( $query_args ) ) {
			$target_url = add_query_arg( $query_args, $target_url );
		}
	}

	wp_safe_redirect( $target_url );
	exit;
}

get_header();
?>
<main class="account-page">
	<section class="page-hero">
		<div class="container">
			<div class="page-hero__content fade-in-up">
				<div class="breadcrumbs"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'velune' ); ?></a><span>/</span><span><?php esc_html_e( 'Account', 'velune' ); ?></span></div>
				<span class="eyebrow"><?php esc_html_e( 'Account', 'velune' ); ?></span>
				<h1><?php esc_html_e( 'Orders and profile in one calm workspace.', 'velune' ); ?></h1>
				<p><?php esc_html_e( 'Dashboard, orders, profile details, and address settings are handled in one WooCommerce account workspace.', 'velune' ); ?></p>
			</div>
		</div>
	</section>

	<section class="page-section">
		<div class="container">
			<div class="account-card fade-in-up">
				<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<?php echo do_shortcode( '[woocommerce_my_account]' ); ?>
				<?php else : ?>
					<p><?php esc_html_e( 'WooCommerce is required for account functionality.', 'velune' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
