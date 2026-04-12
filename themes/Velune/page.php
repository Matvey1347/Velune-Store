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
						'label' => get_the_title(),
					),
				),
				'eyebrow'     => __( 'Page', 'velune' ),
				'title'       => get_the_title(),
				'description' => has_excerpt() ? get_the_excerpt() : '',
			)
		);
		?>
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
