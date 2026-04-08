<?php
/**
 * Reset password page template.
 *
 * @package Velune
 */

$query_vars = array();

foreach ( array( 'key', 'id', 'login', 'show-reset-form' ) as $query_key ) {
	if ( isset( $_GET[ $query_key ] ) ) {
		$query_vars[ $query_key ] = sanitize_text_field( wp_unslash( $_GET[ $query_key ] ) );
	}
}

if ( ! empty( $query_vars ) ) {
	$target_url = add_query_arg( $query_vars, velune_get_forgot_password_url() );
	wp_safe_redirect( $target_url );
	exit;
}

get_header();
?>
<main class="auth-layout">
	<section class="page-hero">
		<div class="container">
			<div class="form-card fade-in-up">
				<span class="eyebrow"><?php esc_html_e( 'Reset password', 'velune' ); ?></span>
				<h1><?php esc_html_e( 'Create a new password.', 'velune' ); ?></h1>
				<p><?php esc_html_e( 'Use the email reset link to continue securely.', 'velune' ); ?></p>
				<div class="form-actions" style="margin-top:24px;">
					<a class="button button--primary" href="<?php echo esc_url( velune_get_forgot_password_url() ); ?>"><?php esc_html_e( 'Request reset link', 'velune' ); ?></a>
					<a class="text-link" href="<?php echo esc_url( velune_get_login_url() ); ?>"><?php esc_html_e( 'Back to login', 'velune' ); ?></a>
				</div>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
