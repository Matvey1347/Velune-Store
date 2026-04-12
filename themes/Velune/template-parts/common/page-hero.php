<?php
/**
 * Shared page hero section.
 *
 * @package Velune
 *
 * @var array<string,mixed> $args Template arguments.
 */

$breadcrumbs   = isset( $args['breadcrumbs'] ) && is_array( $args['breadcrumbs'] ) ? $args['breadcrumbs'] : array();
$eyebrow       = isset( $args['eyebrow'] ) ? (string) $args['eyebrow'] : '';
$title         = isset( $args['title'] ) ? (string) $args['title'] : '';
$description   = isset( $args['description'] ) ? (string) $args['description'] : '';
$after_html    = isset( $args['after_html'] ) ? (string) $args['after_html'] : '';
$section_class = isset( $args['section_class'] ) ? (string) $args['section_class'] : 'page-hero';
$content_class = isset( $args['content_class'] ) ? (string) $args['content_class'] : 'page-hero__content fade-in-up';
$title_tag     = isset( $args['title_tag'] ) ? strtolower( (string) $args['title_tag'] ) : 'h1';

if ( ! in_array( $title_tag, array( 'h1', 'h2', 'h3' ), true ) ) {
	$title_tag = 'h1';
}
?>
<section class="<?php echo esc_attr( $section_class ); ?>">
	<div class="container">
		<div class="<?php echo esc_attr( $content_class ); ?>">
			<?php if ( ! empty( $breadcrumbs ) ) : ?>
				<?php get_template_part( 'template-parts/common/breadcrumbs', null, array( 'items' => $breadcrumbs ) ); ?>
			<?php endif; ?>

			<?php if ( '' !== $eyebrow ) : ?>
				<span class="eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
			<?php endif; ?>

			<?php if ( '' !== $title ) : ?>
				<<?php echo esc_attr( $title_tag ); ?>><?php echo esc_html( $title ); ?></<?php echo esc_attr( $title_tag ); ?>>
			<?php endif; ?>

			<?php if ( '' !== $description ) : ?>
				<p><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $after_html ) : ?>
				<?php echo wp_kses_post( $after_html ); ?>
			<?php endif; ?>
		</div>
	</div>
</section>
