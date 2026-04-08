<?php
/**
 * Single post template.
 *
 * @package Velune
 */

get_header();

if ( ! have_posts() ) {
	get_template_part( 'index' );
	return;
}

while ( have_posts() ) :
	the_post();

	$categories      = get_the_category();
	$category_name   = ! empty( $categories[0] ) ? $categories[0]->name : __( 'Journal', 'velune' );
	$word_count      = str_word_count( wp_strip_all_tags( get_the_content() ) );
	$read_minutes    = max( 1, (int) ceil( $word_count / 220 ) );
	$related_args    = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 3,
		'post__not_in'   => array( get_the_ID() ),
	);

	if ( ! empty( $categories[0] ) ) {
		$related_args['category__in'] = array( (int) $categories[0]->term_id );
	}

	$related_posts = get_posts( $related_args );
	$shop_products = function_exists( 'wc_get_products' ) ? wc_get_products(
		array(
			'status' => 'publish',
			'limit'  => 3,
		)
	) : array();
	?>
	<main class="article-page">
		<section class="page-hero">
			<div class="container">
				<div class="page-hero__content fade-in-up">
					<div class="breadcrumbs"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'velune' ); ?></a><span>/</span><a href="<?php echo esc_url( velune_get_blog_url() ); ?>"><?php esc_html_e( 'Journal', 'velune' ); ?></a><span>/</span><span><?php esc_html_e( 'Article', 'velune' ); ?></span></div>
					<span class="eyebrow"><?php echo esc_html( $category_name ); ?></span>
					<h1><?php the_title(); ?></h1>
					<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22, '...' ) ); ?></p>
					<ul class="article-meta">
						<li><?php echo esc_html( sprintf( _n( '%d min read', '%d min read', $read_minutes, 'velune' ), $read_minutes ) ); ?></li>
						<li><?php echo esc_html( get_the_date() ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'Category: %s', 'velune' ), $category_name ) ); ?></li>
					</ul>
				</div>
			</div>
		</section>

		<section class="page-section">
			<div class="container blog-layout">
				<article class="article-layout fade-in-up">
					<div class="article-content">
						<?php if ( has_post_thumbnail() ) : ?>
							<div class="article-featured-image">
								<?php the_post_thumbnail( 'large', array( 'loading' => 'eager' ) ); ?>
							</div>
						<?php endif; ?>
						<?php the_content(); ?>
					</div>
				</article>

				<aside class="article-aside">
					<div class="sidebar-card fade-in-up">
						<h3><?php esc_html_e( 'Related reading', 'velune' ); ?></h3>
						<div class="blog-list">
							<?php if ( ! empty( $related_posts ) ) : ?>
								<?php foreach ( $related_posts as $related_post ) : ?>
									<a href="<?php echo esc_url( get_permalink( $related_post->ID ) ); ?>"><?php echo esc_html( get_the_title( $related_post->ID ) ); ?></a>
								<?php endforeach; ?>
							<?php else : ?>
								<a href="<?php echo esc_url( velune_get_blog_url() ); ?>"><?php esc_html_e( 'Browse all articles', 'velune' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
					<div class="sidebar-card fade-in-up delay-1">
						<h3><?php esc_html_e( 'Shop the routine', 'velune' ); ?></h3>
						<div class="summary-stack">
							<?php if ( ! empty( $shop_products ) ) : ?>
								<?php foreach ( $shop_products as $shop_product ) : ?>
									<?php $can_add = $shop_product->is_type( 'simple' ) && $shop_product->is_purchasable() && $shop_product->is_in_stock(); ?>
									<div class="summary-line">
										<span><?php echo esc_html( $shop_product->get_name() ); ?></span>
										<?php if ( $can_add ) : ?>
											<button class="text-link" type="button" data-add-to-cart="<?php echo esc_attr( $shop_product->get_id() ); ?>"><?php esc_html_e( 'Add', 'velune' ); ?></button>
										<?php else : ?>
											<a class="text-link" href="<?php echo esc_url( $shop_product->get_permalink() ); ?>"><?php esc_html_e( 'View', 'velune' ); ?></a>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							<?php else : ?>
								<div class="summary-line"><span><?php esc_html_e( 'No products available', 'velune' ); ?></span><a class="text-link" href="<?php echo esc_url( velune_get_shop_url() ); ?>"><?php esc_html_e( 'Shop', 'velune' ); ?></a></div>
							<?php endif; ?>
						</div>
					</div>
				</aside>
			</div>
		</section>
	</main>
	<?php
endwhile;

get_footer();
