<?php
/**
 * Product card template part.
 *
 * @package Velune
 *
 * @var array<string, mixed> $args Template args.
 */

if ( empty( $args['product'] ) || ! $args['product'] instanceof WC_Product ) {
	return;
}

$product = $args['product'];

$image_url = get_the_post_thumbnail_url( $product->get_id(), 'large' );

if ( ! $image_url ) {
	$image_url = wc_placeholder_img_src( 'large' );
}

$description = $product->get_short_description();

if ( ! $description ) {
	$description = wp_trim_words( wp_strip_all_tags( $product->get_description() ), 18, '...' );
}

$meta_text = $product->get_attribute( 'volume' );

if ( ! $meta_text && $product->get_weight() ) {
	$meta_text = sprintf(
		/* translators: %1$s: product weight value, %2$s: unit. */
		__( '%1$s %2$s', 'velune' ),
		$product->get_weight(),
		get_option( 'woocommerce_weight_unit' )
	);
}

if ( ! $meta_text ) {
	$meta_text = esc_html__( 'Standard size', 'velune' );
}

$can_direct_add = $product->is_purchasable() && $product->is_in_stock() && $product->is_type( 'simple' );
?>
<article class="product-card" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
	<div class="product-card__media">
		<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" loading="lazy" />
	</div>
	<div class="product-card__content">
		<h3><?php echo esc_html( $product->get_name() ); ?></h3>
		<p><?php echo esc_html( $description ); ?></p>
		<div class="product-meta">
			<span><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
			<span><?php echo esc_html( $meta_text ); ?></span>
		</div>
		<div class="product-card__actions" data-product-actions data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
			<?php if ( $can_direct_add ) : ?>
				<button class="button button--primary add-to-cart" data-product-add data-add-to-cart="<?php echo esc_attr( $product->get_id() ); ?>">
					<?php esc_html_e( 'Add to cart', 'velune' ); ?>
				</button>
				<div class="qty-control hidden" data-product-qty-control>
					<button type="button" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-product-change="-1" aria-label="<?php esc_attr_e( 'Decrease quantity', 'velune' ); ?>">−</button>
					<span data-product-qty-value>1</span>
					<button type="button" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-product-change="1" aria-label="<?php esc_attr_e( 'Increase quantity', 'velune' ); ?>">+</button>
				</div>
			<?php else : ?>
				<a class="button button--primary" href="<?php echo esc_url( $product->get_permalink() ); ?>">
					<?php esc_html_e( 'Choose options', 'velune' ); ?>
				</a>
			<?php endif; ?>
			<a class="text-link" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php esc_html_e( 'View details', 'velune' ); ?></a>
		</div>
	</div>
</article>
