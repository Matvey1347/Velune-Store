<?php
/**
 * Search results template.
 *
 * @package Velune
 */

get_header();

$search_query_text = get_search_query();
?>
<main>
	<?php
	get_template_part(
		'template-parts/common/page-hero',
		null,
		array(
			'breadcrumbs'  => array(
				array(
					'label' => __( 'Home', 'velune' ),
					'url'   => home_url( '/' ),
				),
				array(
					'label' => __( 'Search', 'velune' ),
				),
			),
			'eyebrow'      => __( 'Search', 'velune' ),
			'title'        => sprintf( __( 'Results for "%s"', 'velune' ), $search_query_text ),
			'content_class' => 'page-hero__content fade-in-up is-visible',
		)
	);
	?>

	<section class="page-section">
		<div class="container narrow-container">
			<div class="search-field">
				<?php get_search_form(); ?>
			</div>

			<div class="search-results-grid">
				<?php if ( have_posts() ) : ?>
					<?php
					while ( have_posts() ) :
						the_post();
						$post_type = get_post_type();
						$label     = velune_get_search_type_label( $post_type ? $post_type : 'post' );
						?>
						<article <?php post_class( 'search-result-card fade-in-up is-visible' ); ?>>
							<span class="meta-kicker"><?php echo esc_html( $label ); ?></span>
							<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24, '...' ) ); ?></p>
						</article>
					<?php endwhile; ?>
				<?php else : ?>
					<article class="search-result-card fade-in-up is-visible">
						<span class="meta-kicker"><?php esc_html_e( 'Search', 'velune' ); ?></span>
						<h3><?php esc_html_e( 'No results found', 'velune' ); ?></h3>
						<p><?php esc_html_e( 'Try a broader term or browse shop categories from the main menu.', 'velune' ); ?></p>
					</article>
				<?php endif; ?>
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
