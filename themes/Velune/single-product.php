<?php
/**
 * WooCommerce single product template.
 *
 * @package Velune
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$product = wc_get_product( get_the_ID() );

	if ( ! $product instanceof WC_Product ) {
		continue;
	}

	$categories    = wp_get_post_terms( get_the_ID(), 'product_cat' );
	$category_name = ! empty( $categories[0] ) ? $categories[0]->name : __( 'Product', 'velune' );
	$size_label    = $product->get_attribute( 'pa_size' );

	if ( ! $size_label && $product->get_weight() ) {
		$size_label = sprintf(
			/* translators: %1$s: product weight value, %2$s: weight unit. */
			__( '%1$s %2$s', 'velune' ),
			$product->get_weight(),
			get_option( 'woocommerce_weight_unit' )
		);
	}

	if ( ! $size_label ) {
		$size_label = esc_html__( 'Standard size', 'velune' );
	}

	$short_description = $product->get_short_description();

	if ( ! $short_description ) {
		$short_description = wp_trim_words( wp_strip_all_tags( $product->get_description() ), 26, '...' );
	}

	$feature_lines = array();

	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! $attribute->get_visible() ) {
			continue;
		}

		if ( $attribute->is_taxonomy() ) {
			$values = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
		} else {
			$options = $attribute->get_options();
			$values  = is_array( $options ) ? $options : array_map( 'trim', explode( '|', (string) $options ) );
		}

		$values = array_filter( array_map( 'wp_strip_all_tags', $values ) );

		if ( empty( $values ) ) {
			continue;
		}

		$feature_lines[] = implode( ', ', $values );
	}

	if ( empty( $feature_lines ) ) {
		$feature_lines = array(
			esc_html__( 'Clean composition', 'velune' ),
			esc_html__( 'Soft-finish texture', 'velune' ),
			esc_html__( 'Pairs with bundles', 'velune' ),
		);
	}

	$gallery_image_ids = $product->get_gallery_image_ids();
	?>
	<main class="product-page">
		<section class="page-hero">
			<div class="container">
				<div class="page-hero__content fade-in-up">
					<div class="breadcrumbs"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'velune' ); ?></a><span>/</span><a href="<?php echo esc_url( velune_get_shop_url() ); ?>"><?php esc_html_e( 'Shop', 'velune' ); ?></a><span>/</span><span><?php the_title(); ?></span></div>
					<span class="eyebrow"><?php echo esc_html( $category_name ); ?></span>
					<h1><?php the_title(); ?></h1>
					<p><?php echo esc_html( $short_description ); ?></p>
				</div>
			</div>
		</section>

		<section class="page-section">
			<div class="container product-layout">
				<div class="product-gallery fade-in-up">
					<?php
					if ( has_post_thumbnail() ) {
						the_post_thumbnail( 'large', array( 'loading' => 'eager' ) );
					} else {
						echo wp_kses_post( wc_placeholder_img( 'large' ) );
					}
					?>
					<?php if ( ! empty( $gallery_image_ids ) ) : ?>
						<div class="product-gallery-thumbs">
							<?php foreach ( array_slice( $gallery_image_ids, 0, 3 ) as $gallery_image_id ) : ?>
								<?php echo wp_kses_post( wp_get_attachment_image( $gallery_image_id, 'thumbnail', false, array( 'loading' => 'lazy' ) ) ); ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="product-copy fade-in-up delay-1">
					<div class="price-line"><strong><?php echo wp_kses_post( $product->get_price_html() ); ?></strong><span><?php echo esc_html( $size_label ); ?></span></div>
					<ul class="product-specs">
						<?php foreach ( array_slice( $feature_lines, 0, 3 ) as $feature_line ) : ?>
							<li><?php echo esc_html( $feature_line ); ?></li>
						<?php endforeach; ?>
					</ul>

					<?php if ( $product->get_short_description() ) : ?>
						<p><?php echo esc_html( wp_strip_all_tags( $product->get_short_description() ) ); ?></p>
					<?php endif; ?>

					<?php if ( $product->get_description() ) : ?>
						<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $product->get_description() ), 40, '...' ) ); ?></p>
					<?php endif; ?>

					<div class="hero-actions single-product-actions">
						<?php woocommerce_template_single_add_to_cart(); ?>
						<a class="button button--secondary" href="<?php echo esc_url( velune_get_subscription_url() ); ?>"><?php esc_html_e( 'View bundles', 'velune' ); ?></a>
					</div>

					<div class="feature-stack">
						<article>
							<h3><?php esc_html_e( 'Best use', 'velune' ); ?></h3>
							<p><?php esc_html_e( 'Built to fit into a calm daily ritual without extra steps.', 'velune' ); ?></p>
						</article>
						<article>
							<h3><?php esc_html_e( 'Positioning', 'velune' ); ?></h3>
							<p><?php esc_html_e( 'Premium essentials, presented like editorial skincare rather than loud ecommerce.', 'velune' ); ?></p>
						</article>
					</div>
				</div>
			</div>
		</section>
	</main>
	<?php
endwhile;

get_footer();
