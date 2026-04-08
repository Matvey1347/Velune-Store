<?php
/**
 * Blog archive template.
 *
 * @package Velune
 */

get_header();

$subscription_url = velune_get_subscription_url();
$category_filters = get_categories(
	array(
		'taxonomy'   => 'category',
		'hide_empty' => true,
		'number'     => 4,
	)
);
?>
<main>
	<section class="page-hero">
		<div class="container">
			<div class="page-hero__content fade-in-up">
				<div class="breadcrumbs"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'velune' ); ?></a><span>/</span><span><?php esc_html_e( 'Journal', 'velune' ); ?></span></div>
				<span class="eyebrow"><?php esc_html_e( 'Journal', 'velune' ); ?></span>
				<h1><?php esc_html_e( 'Editorial skincare, not content noise.', 'velune' ); ?></h1>
				<p><?php esc_html_e( 'Articles designed to reinforce trust and product understanding without sounding salesy.', 'velune' ); ?></p>
			</div>
		</div>
	</section>

	<section class="page-section">
		<div class="container">
			<div class="blog-filter-row">
				<button class="filter-chip is-active" type="button" data-filter="all"><?php esc_html_e( 'All', 'velune' ); ?></button>
				<?php foreach ( $category_filters as $category_filter ) : ?>
					<button class="filter-chip" type="button" data-filter="<?php echo esc_attr( $category_filter->slug ); ?>"><?php echo esc_html( $category_filter->name ); ?></button>
				<?php endforeach; ?>
			</div>

			<div class="blog-layout">
				<div class="blog-grid" data-blog-grid>
					<?php if ( have_posts() ) : ?>
						<?php
						$index = 0;
						while ( have_posts() ) :
							the_post();
							$categories    = get_the_category();
							$category_name = ! empty( $categories[0] ) ? $categories[0]->name : __( 'Journal', 'velune' );
							$category_slug = ! empty( $categories[0] ) ? $categories[0]->slug : 'journal';
							$word_count    = str_word_count( wp_strip_all_tags( get_the_content() ) );
							$read_minutes  = max( 1, (int) ceil( $word_count / 220 ) );
							$card_classes  = 'blog-card fade-in-up';

							if ( 0 === $index ) {
								$card_classes .= ' blog-card--featured';
							}
							?>
							<article class="<?php echo esc_attr( $card_classes ); ?>" data-category="<?php echo esc_attr( $category_slug ); ?>">
								<span class="blog-card__category"><?php echo esc_html( $category_name ); ?></span>
								<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
								<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20, '...' ) ); ?></p>
								<div class="blog-meta-row">
									<span class="pill"><?php echo esc_html( sprintf( _n( '%d min read', '%d min read', $read_minutes, 'velune' ), $read_minutes ) ); ?></span>
									<span class="pill"><?php echo esc_html( get_the_date() ); ?></span>
								</div>
							</article>
							<?php
							++$index;
						endwhile;
						?>
					<?php else : ?>
						<article class="blog-card fade-in-up">
							<span class="blog-card__category"><?php esc_html_e( 'Journal', 'velune' ); ?></span>
							<h3><?php esc_html_e( 'No articles found', 'velune' ); ?></h3>
							<p><?php esc_html_e( 'Publish posts to populate the journal archive.', 'velune' ); ?></p>
						</article>
					<?php endif; ?>
				</div>

				<aside class="sidebar-stack">
					<div class="sidebar-card fade-in-up">
						<h3><?php esc_html_e( 'Search articles', 'velune' ); ?></h3>
						<div class="search-field">
							<?php get_search_form(); ?>
						</div>
					</div>
					<div class="sidebar-card fade-in-up delay-1">
						<h3><?php esc_html_e( 'Categories', 'velune' ); ?></h3>
						<div class="account-nav">
							<?php foreach ( $category_filters as $category_filter ) : ?>
								<a href="<?php echo esc_url( get_category_link( $category_filter->term_id ) ); ?>"><?php echo esc_html( $category_filter->name ); ?></a>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="sidebar-card fade-in-up delay-1">
						<h3><?php esc_html_e( 'Subscribe & save', 'velune' ); ?></h3>
						<p><?php esc_html_e( 'Subscription layout is visible now; automation arrives in phase 2.', 'velune' ); ?></p>
						<a class="button button--primary button--full" href="<?php echo esc_url( $subscription_url ); ?>"><?php esc_html_e( 'View subscription', 'velune' ); ?></a>
					</div>
				</aside>
			</div>

			<?php
			the_posts_pagination(
				array(
					'mid_size'  => 1,
					'prev_text' => __( 'Previous', 'velune' ),
					'next_text' => __( 'Next', 'velune' ),
				)
			);
			?>
		</div>
	</section>
</main>
<?php
get_footer();
