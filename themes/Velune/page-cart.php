<?php
/**
 * Cart page template.
 *
 * @package Velune
 */

get_header();

$checkout_url     = velune_get_checkout_url();
$subscription_url = velune_get_subscription_url();
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
			<section class="cart-page fade-in-up">
				<div class="cart-page__items" data-cart-page-items>
					<?php echo wp_kses_post( velune_get_cart_items_html() ); ?>
				</div>
				<aside class="cart-page__summary">
					<span class="eyebrow"><?php esc_html_e( 'Summary', 'velune' ); ?></span>
					<h3><?php esc_html_e( 'Order summary', 'velune' ); ?></h3>
					<div class="summary-stack">
						<div class="summary-line"><span><?php esc_html_e( 'Subtotal', 'velune' ); ?></span><strong data-cart-page-subtotal><?php echo wp_kses_post( velune_get_cart_subtotal_html() ); ?></strong></div>
						<div class="summary-line"><span><?php esc_html_e( 'Shipping', 'velune' ); ?></span><strong data-cart-page-shipping><?php echo wp_kses_post( velune_get_cart_shipping_html() ); ?></strong></div>
						<div class="summary-line"><span><?php esc_html_e( 'Total', 'velune' ); ?></span><strong data-cart-page-total><?php echo wp_kses_post( velune_get_cart_total_html() ); ?></strong></div>
					</div>
					<div class="stack-actions">
						<a class="button button--primary button--full" href="<?php echo esc_url( $checkout_url ); ?>"><?php esc_html_e( 'Proceed to checkout', 'velune' ); ?></a>
						<a class="button button--secondary button--full" href="<?php echo esc_url( $subscription_url ); ?>"><?php esc_html_e( 'Subscribe & save', 'velune' ); ?></a>
					</div>
					<p class="helper-text"><?php esc_html_e( 'Complimentary shipping on bundles and subscription orders.', 'velune' ); ?></p>
				</aside>
			</section>
		</div>
	</section>
</main>
<?php
get_footer();
