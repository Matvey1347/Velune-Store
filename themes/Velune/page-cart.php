<?php
/**
 * Cart page template.
 *
 * @package Velune
 */

get_header();
?>
<main class="cart-page">
	<section class="page-hero">
		<div class="container">
			<div class="page-hero__content fade-in-up">
				<div class="breadcrumbs"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'velune' ); ?></a><span>/</span><span><?php esc_html_e( 'Cart', 'velune' ); ?></span></div>
				<span class="eyebrow"><?php esc_html_e( 'Cart', 'velune' ); ?></span>
				<h1><?php esc_html_e( 'Review the routine before checkout.', 'velune' ); ?></h1>
				<p><?php esc_html_e( 'Adjust quantities, remove noise, and move directly into checkout.', 'velune' ); ?></p>
			</div>
		</div>
	</section>

	<section class="page-section">
		<div class="container">
			<div class="checkout-card fade-in-up">
				<?php echo do_shortcode( '[woocommerce_cart]' ); ?>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
