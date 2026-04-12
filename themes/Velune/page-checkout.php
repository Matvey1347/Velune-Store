<?php
/**
 * Checkout page template.
 *
 * @package Velune
 */

get_header();
?>
<main>
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
					'url'   => velune_get_cart_url(),
				),
				array(
					'label' => __( 'Checkout', 'velune' ),
				),
			),
			'title'       => __( 'Calm checkout. No extra friction.', 'velune' ),
			'description' => __( 'Billing, shipping, and payment are powered by native WooCommerce checkout.', 'velune' ),
		)
	);
	?>

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
