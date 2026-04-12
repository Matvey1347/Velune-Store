<?php
/**
 * Front page template.
 *
 * @package Velune
 */

get_header();

$shop_url         = velune_get_shop_url();
$blog_url         = velune_get_blog_url();

$product_query = new WP_Query(
	array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => true,
	)
);

$blog_posts = get_posts(
	array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'numberposts'         => 3,
		'ignore_sticky_posts' => true,
	)
);

$active_subscription_plan = velune_get_active_subscription_plan();
$bundle_media             = velune_get_subscription_bundle_media( $active_subscription_plan );
?>
<main>
	<section class="hero-section">
		<div class="container hero-grid">
			<div class="hero-copy fade-in-up">
				<span class="eyebrow"><?php esc_html_e( 'Minimal luxury skincare', 'velune' ); ?></span>
				<h1><?php esc_html_e( 'Quiet formulas for a refined daily ritual.', 'velune' ); ?></h1>
				<p><?php esc_html_e( 'VELUNE brings together a calm cleansing body wash, restorative cream, concentrated serum, and curated bundles designed for a simpler routine.', 'velune' ); ?></p>
				<div class="hero-actions">
					<a class="button button--primary" href="#shop"><?php esc_html_e( 'Shop products', 'velune' ); ?></a>
					<a class="button button--secondary" href="#subscription"><?php esc_html_e( 'Subscribe & save', 'velune' ); ?></a>
				</div>
				<ul class="hero-points">
					<li><?php esc_html_e( 'Warm, skin-first formulas', 'velune' ); ?></li>
					<li><?php esc_html_e( 'Flexible bundle subscriptions', 'velune' ); ?></li>
					<li><?php esc_html_e( 'Stable WooCommerce checkout flow', 'velune' ); ?></li>
				</ul>
			</div>

			<div class="hero-media fade-in-up delay-1">
				<div class="hero-media__frame">
					<img src="<?php echo esc_url( get_theme_file_uri( '/assets/images/hero/hero.webp' ) ); ?>" alt="<?php esc_attr_e( 'VELUNE skincare hero product arrangement', 'velune' ); ?>" loading="eager" />
				</div>
			</div>
		</div>
	</section>

	<section class="brand-strip section-sm">
		<div class="container brand-strip__grid">
			<article>
				<span>01</span>
				<p><?php esc_html_e( 'Soft neutrals. Clean rituals. No excess.', 'velune' ); ?></p>
			</article>
			<article>
				<span>02</span>
				<p><?php esc_html_e( 'Built around products people actually finish.', 'velune' ); ?></p>
			</article>
			<article>
				<span>03</span>
				<p><?php esc_html_e( 'Subscriptions centered on bundles, not confusion.', 'velune' ); ?></p>
			</article>
		</div>
	</section>

	<section class="products-section section" id="shop">
		<div class="container">
			<div class="section-heading">
				<div>
					<span class="eyebrow"><?php esc_html_e( 'Shop essentials', 'velune' ); ?></span>
					<h2 class="mb-5"><?php esc_html_e( 'The daily lineup', 'velune' ); ?></h2>
					<p><?php esc_html_e( 'Every product is presented with one job: fit into a calm, premium routine without adding noise.', 'velune' ); ?></p>
				</div>
			</div>

			<div class="product-grid">
				<?php if ( $product_query->have_posts() ) : ?>
					<?php
					while ( $product_query->have_posts() ) :
						$product_query->the_post();
						$product = wc_get_product( get_the_ID() );

						if ( ! $product instanceof WC_Product ) {
							continue;
						}

						get_template_part( 'template-parts/product', 'card', array( 'product' => $product ) );
					endwhile;
					wp_reset_postdata();
					?>
				<?php else : ?>
					<article class="info-card">
						<h3><?php esc_html_e( 'No products yet', 'velune' ); ?></h3>
						<p><?php esc_html_e( 'Publish WooCommerce products to populate this section.', 'velune' ); ?></p>
						<a class="button button--primary" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Open shop', 'velune' ); ?></a>
					</article>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<?php
	get_template_part(
		'template-parts/subscription/bundle-section',
		null,
		array(
			'active_subscription_plan' => $active_subscription_plan,
			'bundle_media_image'       => $bundle_media['image'],
			'bundle_media_alt'         => $bundle_media['alt'],
		)
	);
	?>

	<section class="ritual-section section-lg" id="ritual">
		<div class="container ritual-grid">
			<div>
				<span class="eyebrow"><?php esc_html_e( 'The ritual', 'velune' ); ?></span>
				<h2><?php esc_html_e( 'Simple enough to repeat. Good enough to keep.', 'velune' ); ?></h2>
			</div>
			<div class="ritual-steps">
				<article>
					<span><?php esc_html_e( 'Step 1', 'velune' ); ?></span>
					<h3><?php esc_html_e( 'Cleanse', 'velune' ); ?></h3>
					<p><?php esc_html_e( 'Start with the Body Wash to reset the skin without stripping.', 'velune' ); ?></p>
				</article>
				<article>
					<span><?php esc_html_e( 'Step 2', 'velune' ); ?></span>
					<h3><?php esc_html_e( 'Restore', 'velune' ); ?></h3>
					<p><?php esc_html_e( 'Apply Cream for balanced comfort and a soft finish.', 'velune' ); ?></p>
				</article>
				<article>
					<span><?php esc_html_e( 'Step 3', 'velune' ); ?></span>
					<h3><?php esc_html_e( 'Refine', 'velune' ); ?></h3>
					<p><?php esc_html_e( 'Use Serum as the final layer where you want more precision.', 'velune' ); ?></p>
				</article>
			</div>
		</div>
	</section>

	<section class="editorial-section section">
		<div class="container editorial-grid">
			<article class="editorial-card editorial-card--large">
				<span class="eyebrow"><?php esc_html_e( 'Brand principle', 'velune' ); ?></span>
				<h3><?php esc_html_e( 'Premium is mostly subtraction.', 'velune' ); ?></h3>
				<p><?php esc_html_e( 'The interface, photography, and copy stay intentionally restrained so the products can carry the weight.', 'velune' ); ?></p>
			</article>
			<article class="editorial-card">
				<span class="eyebrow"><?php esc_html_e( 'Account', 'velune' ); ?></span>
				<h3><?php esc_html_e( 'Orders and profile in one calm space.', 'velune' ); ?></h3>
				<p><?php esc_html_e( 'My Account uses native WooCommerce endpoints with the same visual system.', 'velune' ); ?></p>
			</article>
			<article class="editorial-card">
				<span class="eyebrow"><?php esc_html_e( 'Checkout', 'velune' ); ?></span>
				<h3><?php esc_html_e( 'Frictionless purchase flow with clear totals.', 'velune' ); ?></h3>
				<p><?php esc_html_e( 'Cart drawer, cart page, checkout blocks, and quantity controls stay synced with WooCommerce cart data.', 'velune' ); ?></p>
			</article>
		</div>
	</section>

	<section class="blog-preview section-lg">
		<div class="container">
			<div class="section-heading">
				<div>
					<span class="eyebrow"><?php esc_html_e( 'Journal', 'velune' ); ?></span>
					<h2><?php esc_html_e( 'Content that supports the brand', 'velune' ); ?></h2>
				</div>
				<a class="text-link" href="<?php echo esc_url( $blog_url ); ?>"><?php esc_html_e( 'View all articles', 'velune' ); ?></a>
			</div>

			<div class="blog-filter-row" data-blog-filters>
				<button class="filter-chip is-active" type="button" data-filter="all"><?php esc_html_e( 'All', 'velune' ); ?></button>
				<button class="filter-chip" type="button" data-filter="ritual"><?php esc_html_e( 'Ritual', 'velune' ); ?></button>
				<button class="filter-chip" type="button" data-filter="ingredients"><?php esc_html_e( 'Ingredients', 'velune' ); ?></button>
				<button class="filter-chip" type="button" data-filter="subscription"><?php esc_html_e( 'Subscription', 'velune' ); ?></button>
			</div>

			<div class="blog-grid" data-blog-grid>
				<?php if ( ! empty( $blog_posts ) ) : ?>
					<?php foreach ( $blog_posts as $blog_post ) : ?>
						<?php
						$categories     = get_the_category( $blog_post->ID );
						$category_name  = ! empty( $categories[0] ) ? $categories[0]->name : __( 'Journal', 'velune' );
						$category_slug  = ! empty( $categories[0] ) ? $categories[0]->slug : 'journal';
						?>
						<article class="blog-card" data-category="<?php echo esc_attr( $category_slug ); ?>">
							<span class="blog-card__category"><?php echo esc_html( $category_name ); ?></span>
							<h3><a href="<?php echo esc_url( get_permalink( $blog_post->ID ) ); ?>"><?php echo esc_html( get_the_title( $blog_post->ID ) ); ?></a></h3>
							<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $blog_post->ID ), 18, '...' ) ); ?></p>
						</article>
					<?php endforeach; ?>
				<?php else : ?>
					<article class="blog-card" data-category="journal">
						<span class="blog-card__category"><?php esc_html_e( 'Journal', 'velune' ); ?></span>
						<h3><?php esc_html_e( 'Publish your first article', 'velune' ); ?></h3>
						<p><?php esc_html_e( 'This section will auto-populate with real WordPress posts.', 'velune' ); ?></p>
					</article>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="faq-section section">
		<div class="container narrow-container">
			<div class="section-heading section-heading--centered">
				<span class="eyebrow"><?php esc_html_e( 'FAQ', 'velune' ); ?></span>
				<h2><?php esc_html_e( 'Everything the customer should know fast.', 'velune' ); ?></h2>
			</div>

			<div class="faq-list">
				<details>
					<summary><?php esc_html_e( 'Can I buy without subscribing?', 'velune' ); ?></summary>
					<p><?php esc_html_e( 'Yes. One-time purchase is always available. Subscription is intentionally deferred to the next implementation phase.', 'velune' ); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e( 'Can I manage my subscription myself?', 'velune' ); ?></summary>
					<p><?php esc_html_e( 'Subscription controls will be added to the account area in a dedicated phase.', 'velune' ); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e( 'Do you ship bundles internationally?', 'velune' ); ?></summary>
					<p><?php esc_html_e( 'Shipping zones and rates are managed through native WooCommerce shipping settings.', 'velune' ); ?></p>
				</details>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
