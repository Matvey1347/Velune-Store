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
				<span class="eyebrow"><?php esc_html_e( 'Login', 'velune' ); ?></span>
				<h1><?php esc_html_e( 'Welcome back.', 'velune' ); ?></h1>
				<p><?php esc_html_e( 'Sign in with native WooCommerce and WordPress authentication.', 'velune' ); ?></p>
				<div class="auth-form-shell" style="margin-top:24px;">
					<?php if ( function_exists( 'wc_print_notices' ) ) : ?>
						<?php wc_print_notices(); ?>
					<?php endif; ?>

					<form class="woocommerce-form woocommerce-form-login login form-stack" method="post" action="<?php echo esc_url( velune_get_login_url() ); ?>">
						<?php do_action( 'woocommerce_login_form_start' ); ?>

						<div class="input-group">
							<label for="username"><?php esc_html_e( 'Email or username', 'velune' ); ?></label>
							<input id="username" type="text" name="username" autocomplete="username" value="<?php echo isset( $_POST['username'] ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required>
						</div>

						<div class="input-group">
							<label for="password"><?php esc_html_e( 'Password', 'velune' ); ?></label>
							<input id="password" type="password" name="password" autocomplete="current-password" required>
						</div>

						<?php do_action( 'woocommerce_login_form' ); ?>

						<div class="form-actions">
							<button class="button button--primary" type="submit" name="login" value="<?php esc_attr_e( 'Log in', 'velune' ); ?>"><?php esc_html_e( 'Log in', 'velune' ); ?></button>
							<a class="text-link" href="<?php echo esc_url( velune_get_forgot_password_url() ); ?>"><?php esc_html_e( 'Forgot password?', 'velune' ); ?></a>
						</div>

						<label class="helper-text">
							<input type="checkbox" name="rememberme" value="forever">
							<?php esc_html_e( 'Remember me', 'velune' ); ?>
						</label>

						<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
						<input type="hidden" name="redirect" value="<?php echo esc_url( velune_get_account_url() ); ?>">

						<?php do_action( 'woocommerce_login_form_end' ); ?>
					</form>
				</div>
				<div class="inline-actions" style="margin-top:16px;">
					<span class="helper-text"><?php esc_html_e( 'No account yet?', 'velune' ); ?></span>
					<a class="text-link" href="<?php echo esc_url( velune_get_register_url() ); ?>"><?php esc_html_e( 'Register', 'velune' ); ?></a>
				</div>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
