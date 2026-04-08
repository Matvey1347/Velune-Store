<?php
/**
 * Default page template.
 *
 * @package Velune
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<main>
		<section class="page-hero">
			<div class="container">
				<div class="page-hero__content fade-in-up">
					<div class="breadcrumbs"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'velune' ); ?></a><span>/</span><span><?php the_title(); ?></span></div>
					<span class="eyebrow"><?php esc_html_e( 'Page', 'velune' ); ?></span>
					<h1><?php the_title(); ?></h1>
					<?php if ( has_excerpt() ) : ?>
						<p><?php echo esc_html( get_the_excerpt() ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</section>
		<section class="page-section">
			<div class="container narrow-container">
				<article class="page-card fade-in-up">
					<?php the_content(); ?>
				</article>
			</div>
		</section>
	</main>
	<?php
endwhile;

get_footer();
