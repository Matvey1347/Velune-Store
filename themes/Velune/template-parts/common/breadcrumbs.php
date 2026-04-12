<?php
/**
 * Breadcrumb trail.
 *
 * @package Velune
 *
 * @var array<string,mixed> $args Template arguments.
 */

$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();

if ( empty( $items ) ) {
	return;
}
?>
<div class="breadcrumbs">
	<?php foreach ( array_values( $items ) as $index => $item ) : ?>
		<?php
		$label = isset( $item['label'] ) ? (string) $item['label'] : '';
		$url   = isset( $item['url'] ) ? (string) $item['url'] : '';

		if ( '' === $label ) {
			continue;
		}
		?>
		<?php if ( '' !== $url ) : ?>
			<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php else : ?>
			<span><?php echo esc_html( $label ); ?></span>
		<?php endif; ?>

		<?php if ( $index < count( $items ) - 1 ) : ?>
			<span>/</span>
		<?php endif; ?>
	<?php endforeach; ?>
</div>
