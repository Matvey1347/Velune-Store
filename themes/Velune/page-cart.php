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
	<?php
	get_template_part(
		'template-parts/common/page-hero',
		null,
		array(
			'breadcrumbs' => array(
				array(
					'label' => __( 'Home', 'velune' ),
					'url'   => home_url( '/' ),
				),
				array(
					'label' => __( 'Cart', 'velune' ),
				),
			),
			'title'       => __( 'Review the routine before checkout.', 'velune' ),
			'description' => __( 'Adjust quantities, remove noise, and move directly into checkout.', 'velune' ),
		)
	);
	?>

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
