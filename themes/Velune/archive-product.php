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
	<section class="page-hero">
		<div class="container">
			<div class="page-hero__content fade-in-up">
				<div class="breadcrumbs"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'velune' ); ?></a><span>/</span><span><?php esc_html_e( 'Shop', 'velune' ); ?></span></div>
				<span class="eyebrow"><?php esc_html_e( 'Shop', 'velune' ); ?></span>
				<h1><?php esc_html_e( 'The daily lineup', 'velune' ); ?></h1>
				<p><?php esc_html_e( 'Choose from real WooCommerce products with the original Velune visual style.', 'velune' ); ?></p>
			</div>
		</div>
	</section>

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
