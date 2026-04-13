<?php
/**
 * View Order
 *
 * Shows the details of a particular order on the account page.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/view-order.php.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.6.0
 */

defined( 'ABSPATH' ) || exit;

$notes = $order->get_customer_order_notes();
?>
<p class="velune-order-summary">
<?php
printf(
	/* translators: 1: order number 2: order date 3: order status */
	esc_html__( 'Order #%1$s was placed on %2$s and is currently %3$s.', 'woocommerce' ),
	'<span class="velune-order-summary__value velune-order-summary__value--number">' . esc_html( $order->get_order_number() ) . '</span>',
	'<span class="velune-order-summary__value velune-order-summary__value--date">' . esc_html( wc_format_datetime( $order->get_date_created() ) ) . '</span>',
	'<span class="velune-order-summary__value velune-order-summary__value--status">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</span>'
);
?>
</p>

<?php if ( $notes ) : ?>
	<h2><?php esc_html_e( 'Order updates', 'woocommerce' ); ?></h2>
	<ol class="woocommerce-OrderUpdates commentlist notes">
		<?php foreach ( $notes as $note ) : ?>
		<li class="woocommerce-OrderUpdate comment note">
			<div class="woocommerce-OrderUpdate-inner comment_container">
				<div class="woocommerce-OrderUpdate-text comment-text">
					<p class="woocommerce-OrderUpdate-meta meta"><?php echo date_i18n( esc_html__( 'l jS \o\f F Y, h:ia', 'woocommerce' ), strtotime( $note->comment_date ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<div class="woocommerce-OrderUpdate-description description">
						<?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?>
					</div>
					<div class="clear"></div>
				</div>
				<div class="clear"></div>
			</div>
		</li>
		<?php endforeach; ?>
	</ol>
<?php endif; ?>

<?php do_action( 'woocommerce_view_order', $order_id ); ?>
