<?php
/**
 * My Account page template.
 *
 * @package Velune
 */

get_header();
?>
<main class="account-page">
	<section class="page-hero">
		<div class="container">
			<div class="page-hero__content fade-in-up">
				<div class="breadcrumbs"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'velune' ); ?></a><span>/</span><span><?php esc_html_e( 'Account', 'velune' ); ?></span></div>
				<span class="eyebrow"><?php esc_html_e( 'Account', 'velune' ); ?></span>
				<h1><?php esc_html_e( 'Orders and profile in one calm workspace.', 'velune' ); ?></h1>
				<p><?php esc_html_e( 'Dashboard, orders, addresses, account details, and logout are handled by native WooCommerce endpoints.', 'velune' ); ?></p>
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
