<?php
/**
 * Forgot password page template.
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
			<div class="form-card fade-in-up">
				<span class="eyebrow"><?php esc_html_e( 'Password reset', 'velune' ); ?></span>
				<h1><?php esc_html_e( 'Request a reset link.', 'velune' ); ?></h1>
				<p><?php esc_html_e( 'Simple email-first reset flow using native WooCommerce account handling.', 'velune' ); ?></p>
				<div style="margin-top:24px;">
					<?php if ( function_exists( 'wc_print_notices' ) ) : ?>
						<?php wc_print_notices(); ?>
					<?php endif; ?>
					<?php if ( class_exists( 'WC_Shortcode_My_Account' ) ) : ?>
						<?php WC_Shortcode_My_Account::lost_password(); ?>
					<?php else : ?>
						<form class="form-stack" action="<?php echo esc_url( wp_lostpassword_url() ); ?>" method="post">
							<div class="input-group">
								<label for="user_login"><?php esc_html_e( 'Email', 'velune' ); ?></label>
								<input id="user_login" type="email" name="user_login" required />
							</div>
							<div class="form-actions">
								<button class="button button--primary" type="submit"><?php esc_html_e( 'Send reset link', 'velune' ); ?></button>
								<a class="text-link" href="<?php echo esc_url( velune_get_login_url() ); ?>"><?php esc_html_e( 'Back to login', 'velune' ); ?></a>
							</div>
						</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
