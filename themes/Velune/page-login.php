<?php
/**
 * Login page template.
 *
 * @package Velune
 */

if ( is_user_logged_in() ) {
	wp_safe_redirect( velune_get_account_url() );
	exit;
}

get_header();
?>
<main class="auth-layout">
	<section class="page-hero">
		<div class="container">
			<div class="auth-card fade-in-up">
				<span class="eyebrow"><?php esc_html_e( 'Account', 'velune' ); ?></span>
				<h1><?php esc_html_e( 'Sign in to manage orders and account details.', 'velune' ); ?></h1>
				<p><?php esc_html_e( 'Real WordPress/WooCommerce authentication flow.', 'velune' ); ?></p>
				<div class="auth-form-shell" style="margin-top:24px;">
					<?php echo do_shortcode( '[woocommerce_my_account]' ); ?>
				</div>
				<div class="inline-actions" style="margin-top:16px;">
					<span class="helper-text"><?php esc_html_e( 'No account yet?', 'velune' ); ?></span>
					<a class="text-link" href="<?php echo esc_url( velune_get_register_url() ); ?>"><?php esc_html_e( 'Create account', 'velune' ); ?></a>
				</div>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
