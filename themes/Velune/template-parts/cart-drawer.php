<?php
/**
 * Cart drawer template part.
 *
 * @package Velune
 */

$cart_url     = velune_get_cart_url();
$checkout_url = velune_get_checkout_url();
?>
<aside class="cart-drawer" data-cart-drawer aria-hidden="true">
	<div class="cart-drawer__overlay" data-cart-close></div>
	<div class="cart-drawer__panel">
		<div class="cart-drawer__header">
			<div>
				<span class="eyebrow"><?php esc_html_e( 'Cart', 'velune' ); ?></span>
				<h2><?php esc_html_e( 'Your selection', 'velune' ); ?></h2>
			</div>
			<button class="icon-close" type="button" aria-label="<?php esc_attr_e( 'Close cart', 'velune' ); ?>" data-cart-close>&times;</button>
		</div>

		<div class="cart-drawer__body" data-cart-items>
			<?php echo wp_kses_post( velune_get_cart_items_html() ); ?>
		</div>

		<div class="cart-drawer__footer">
			<div class="cart-total-row">
				<span><?php esc_html_e( 'Subtotal', 'velune' ); ?></span>
				<strong data-cart-subtotal><?php echo wp_kses_post( velune_get_cart_subtotal_html() ); ?></strong>
			</div>
			<div class="stack-actions">
				<a class="button button--secondary button--full" href="<?php echo esc_url( $cart_url ); ?>"><?php esc_html_e( 'View cart', 'velune' ); ?></a>
				<a class="button button--primary button--full" href="<?php echo esc_url( $checkout_url ); ?>"><?php esc_html_e( 'Checkout', 'velune' ); ?></a>
			</div>
		</div>
	</div>
</aside>
