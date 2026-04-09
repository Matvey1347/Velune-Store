<?php
/**
 * Single product content template.
 *
 * @package Velune
 */

defined( 'ABSPATH' ) || exit;

global $product;

do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form();
	return;
}
?>
<article id="product-<?php the_ID(); ?>" <?php wc_product_class( 'velune-single-product', $product ); ?>>
	<div class="velune-product-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'velune' ); ?>">
		<?php
		woocommerce_breadcrumb(
			array(
				'wrap_before' => '<nav class="breadcrumbs">',
				'wrap_after'  => '</nav>',
			)
		);
		?>
	</div>

	<div class="velune-product-shell">
		<div class="velune-product-gallery fade-in-up">
			<?php do_action( 'woocommerce_before_single_product_summary' ); ?>
		</div>

		<div class="summary entry-summary velune-product-summary fade-in-up delay-1">
			<?php do_action( 'woocommerce_single_product_summary' ); ?>
		</div>
	</div>

	<?php if ( ! empty( apply_filters( 'woocommerce_product_tabs', array() ) ) ) : ?>
		<div class="velune-product-details fade-in-up">
			<?php woocommerce_output_product_data_tabs(); ?>
		</div>
	<?php endif; ?>

	<?php woocommerce_output_related_products(); ?>
	<?php do_action( 'woocommerce_after_single_product_summary' ); ?>
</article>
<?php do_action( 'woocommerce_after_single_product' ); ?>
