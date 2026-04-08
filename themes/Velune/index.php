<?php
/**
 * Fallback template.
 *
 * @package Velune
 */

get_header();
?>
<main class="page-section">
	<div class="container narrow-container">
		<div class="section-heading section-heading--centered">
			<span class="eyebrow"><?php esc_html_e( 'Content', 'velune' ); ?></span>
			<h1><?php esc_html_e( 'Latest updates', 'velune' ); ?></h1>
		</div>

		<div class="blog-grid">
			<?php if ( have_posts() ) : ?>
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article <?php post_class( 'blog-card fade-in-up is-visible' ); ?>>
						<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20, '...' ) ); ?></p>
					</article>
					<?php
				endwhile;
				?>
			<?php else : ?>
				<article class="blog-card fade-in-up is-visible">
					<h3><?php esc_html_e( 'No content found', 'velune' ); ?></h3>
					<p><?php esc_html_e( 'Please publish posts or pages to see content here.', 'velune' ); ?></p>
				</article>
			<?php endif; ?>
		</div>
	</div>
</main>
<?php
get_footer();
