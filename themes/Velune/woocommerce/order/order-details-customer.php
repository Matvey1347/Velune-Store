<?php
/**
 * Order Customer Details
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-details-customer.php.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.7.0
 */

defined( 'ABSPATH' ) || exit;

$show_shipping            = ! wc_ship_to_billing_address_only() && $order->needs_shipping_address();
$billing_address_markup   = (string) $order->get_formatted_billing_address( '' );
$shipping_address_markup  = (string) $order->get_formatted_shipping_address( '' );
$has_billing_address      = '' !== trim( wp_strip_all_tags( $billing_address_markup ) );
$has_shipping_address     = '' !== trim( wp_strip_all_tags( $shipping_address_markup ) );
$has_billing_contact_info = '' !== trim( (string) $order->get_billing_phone() ) || '' !== trim( (string) $order->get_billing_email() );
$has_shipping_phone       = '' !== trim( (string) $order->get_shipping_phone() );
?>
<section class="woocommerce-customer-details">

	<?php if ( $show_shipping ) : ?>

	<section class="woocommerce-columns woocommerce-columns--2 woocommerce-columns--addresses col2-set addresses">
		<div class="woocommerce-column woocommerce-column--1 woocommerce-column--billing-address col-1">

	<?php endif; ?>

	<h2 class="woocommerce-column__title"><?php esc_html_e( 'Billing address', 'woocommerce' ); ?></h2>

	<?php if ( $has_billing_address || $has_billing_contact_info ) : ?>
		<address>
			<?php echo wp_kses_post( $billing_address_markup ); ?>

			<?php if ( $order->get_billing_phone() ) : ?>
				<p class="woocommerce-customer-details--phone"><?php echo esc_html( $order->get_billing_phone() ); ?></p>
			<?php endif; ?>

			<?php if ( $order->get_billing_email() ) : ?>
				<p class="woocommerce-customer-details--email"><?php echo esc_html( $order->get_billing_email() ); ?></p>
			<?php endif; ?>

			<?php
				do_action( 'woocommerce_order_details_after_customer_address', 'billing', $order );
			?>
		</address>
	<?php else : ?>
		<p class="velune-empty-address"><?php esc_html_e( 'No billing address provided', 'velune' ); ?></p>
	<?php endif; ?>

	<?php if ( $show_shipping ) : ?>

		</div><!-- /.col-1 -->

		<div class="woocommerce-column woocommerce-column--2 woocommerce-column--shipping-address col-2">
			<h2 class="woocommerce-column__title"><?php esc_html_e( 'Shipping address', 'woocommerce' ); ?></h2>
			<?php if ( $has_shipping_address || $has_shipping_phone ) : ?>
				<address>
					<?php echo wp_kses_post( $shipping_address_markup ); ?>

					<?php if ( $order->get_shipping_phone() ) : ?>
						<p class="woocommerce-customer-details--phone"><?php echo esc_html( $order->get_shipping_phone() ); ?></p>
					<?php endif; ?>

					<?php
						do_action( 'woocommerce_order_details_after_customer_address', 'shipping', $order );
					?>
				</address>
			<?php else : ?>
				<p class="velune-empty-address"><?php esc_html_e( 'No shipping address provided', 'velune' ); ?></p>
			<?php endif; ?>
		</div><!-- /.col-2 -->

	</section><!-- /.col2-set -->

	<?php endif; ?>

	<?php do_action( 'woocommerce_order_details_after_customer_details', $order ); ?>

</section>
