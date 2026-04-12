<?php
/**
 * WooCommerce product archive template.
 *
 * @package Velune
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main class="product-page">
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
					'label' => __( 'Shop', 'velune' ),
				),
			),
			'eyebrow'     => __( 'Shop', 'velune' ),
			'title'       => __( 'The daily lineup', 'velune' ),
			'description' => __( 'Choose from real WooCommerce products with the original Velune visual style.', 'velune' ),
		)
	);
	?>

	<section class="page-section">
		<div class="container">
			<div class="product-grid">
				<?php if ( woocommerce_product_loop() ) : ?>
					<?php while ( have_posts() ) : ?>
						<?php
						the_post();
						$product = wc_get_product( get_the_ID() );

						if ( ! $product instanceof WC_Product ) {
							continue;
						}

						get_template_part( 'template-parts/product', 'card', array( 'product' => $product ) );
						?>
					<?php endwhile; ?>
				<?php else : ?>
					<article class="info-card">
						<h3><?php esc_html_e( 'No products found', 'velune' ); ?></h3>
						<p><?php esc_html_e( 'Adjust filters or publish products to continue.', 'velune' ); ?></p>
					</article>
				<?php endif; ?>
			</div>
			<?php woocommerce_pagination(); ?>
		</div>
	</section>
</main>
<?php
get_footer();
