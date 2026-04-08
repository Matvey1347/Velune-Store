<?php
/**
 * Checkout page template.
 *
 * @package Velune
 */

get_header();
?>
<main>
	<section class="page-hero">
		<div class="container">
			<div class="page-hero__content fade-in-up">
				<div class="breadcrumbs"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'velune' ); ?></a><span>/</span><a href="<?php echo esc_url( velune_get_cart_url() ); ?>"><?php esc_html_e( 'Cart', 'velune' ); ?></a><span>/</span><span><?php esc_html_e( 'Checkout', 'velune' ); ?></span></div>
				<span class="eyebrow"><?php esc_html_e( 'Checkout', 'velune' ); ?></span>
				<h1><?php esc_html_e( 'Calm checkout. No extra friction.', 'velune' ); ?></h1>
				<p><?php esc_html_e( 'Billing, shipping, and payment are powered by native WooCommerce checkout.', 'velune' ); ?></p>
			</div>
		</div>
	</section>

	<section class="page-section">
		<div class="container">
			<div class="checkout-card fade-in-up">
				<?php echo do_shortcode( '[woocommerce_checkout]' ); ?>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
