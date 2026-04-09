<?php
/**
 * Related products.
 *
 * @package Velune
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $related_products ) ) {
	return;
}
?>
<section class="related products velune-related-products fade-in-up">
	<div class="section-heading section-heading--centered">
		<span class="eyebrow"><?php esc_html_e( 'Pairings', 'velune' ); ?></span>
		<h2><?php esc_html_e( 'Related Products', 'velune' ); ?></h2>
	</div>

	<div class="product-grid velune-related-grid">
		<?php foreach ( $related_products as $related_product ) : ?>
			<?php
			if ( ! $related_product instanceof WC_Product ) {
				continue;
			}

			$post_object = get_post( $related_product->get_id() );

			if ( ! $post_object instanceof WP_Post ) {
				continue;
			}

			$GLOBALS['post'] = $post_object;
			setup_postdata( $GLOBALS['post'] );
			get_template_part( 'template-parts/product', 'card', array( 'product' => $related_product ) );
			?>
		<?php endforeach; ?>
	</div>
</section>
<?php
wp_reset_postdata();
