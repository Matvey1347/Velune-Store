<?php
/**
 * Register page template.
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
				<span class="eyebrow"><?php esc_html_e( 'Register', 'velune' ); ?></span>
				<h1><?php esc_html_e( 'Create your account.', 'velune' ); ?></h1>
				<p><?php esc_html_e( 'Registration is handled by native WooCommerce customer auth.', 'velune' ); ?></p>
				<div class="auth-form-shell" style="margin-top:24px;">
					<?php if ( function_exists( 'wc_print_notices' ) ) : ?>
						<?php wc_print_notices(); ?>
					<?php endif; ?>

					<form method="post" class="woocommerce-form woocommerce-form-register register form-stack" action="<?php echo esc_url( velune_get_register_url() ); ?>">
						<?php do_action( 'woocommerce_register_form_start' ); ?>

						<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
							<div class="input-group">
								<label for="reg_username"><?php esc_html_e( 'Username', 'velune' ); ?></label>
								<input id="reg_username" type="text" name="username" autocomplete="username" value="<?php echo isset( $_POST['username'] ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required>
							</div>
						<?php endif; ?>

						<div class="input-group">
							<label for="reg_email"><?php esc_html_e( 'Email', 'velune' ); ?></label>
							<input id="reg_email" type="email" name="email" autocomplete="email" value="<?php echo isset( $_POST['email'] ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" required>
						</div>

						<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
							<div class="input-group">
								<label for="reg_password"><?php esc_html_e( 'Password', 'velune' ); ?></label>
								<input id="reg_password" type="password" name="password" autocomplete="new-password" required>
							</div>
						<?php else : ?>
							<p class="helper-text"><?php esc_html_e( 'A secure password will be generated and sent by email.', 'velune' ); ?></p>
						<?php endif; ?>

						<?php do_action( 'woocommerce_register_form' ); ?>

						<div class="form-actions">
							<button class="button button--primary" type="submit" name="register" value="<?php esc_attr_e( 'Register', 'velune' ); ?>"><?php esc_html_e( 'Register', 'velune' ); ?></button>
						</div>

						<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
						<?php do_action( 'woocommerce_register_form_end' ); ?>
					</form>
				</div>
				<div class="inline-actions" style="margin-top:16px;">
					<span class="helper-text"><?php esc_html_e( 'Already have an account?', 'velune' ); ?></span>
					<a class="text-link" href="<?php echo esc_url( velune_get_login_url() ); ?>"><?php esc_html_e( 'Login', 'velune' ); ?></a>
				</div>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
