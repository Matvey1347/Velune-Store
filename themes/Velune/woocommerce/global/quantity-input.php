<?php
/**
 * Quantity input.
 *
 * @package Velune
 */

defined( 'ABSPATH' ) || exit;

if ( $max_value && $min_value === $max_value ) {
	?>
	<div class="quantity hidden">
		<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" class="qty" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $min_value ); ?>" />
	</div>
	<?php
} else {
	$label = ! empty( $product_name )
		? sprintf( esc_html__( '%s quantity', 'velune' ), wp_strip_all_tags( $product_name ) )
		: esc_html__( 'Quantity', 'velune' );
	?>
	<div class="quantity velune-qty" data-qty-root>
		<button type="button" class="velune-qty__button" data-qty-action="decrease" aria-label="<?php esc_attr_e( 'Decrease quantity', 'velune' ); ?>" <?php echo $readonly ? 'disabled' : ''; ?>>
			<span aria-hidden="true">−</span>
		</button>
		<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $label ); ?></label>
		<input
			type="number"
			id="<?php echo esc_attr( $input_id ); ?>"
			class="<?php echo esc_attr( join( ' ', (array) $classes ) ); ?>"
			name="<?php echo esc_attr( $input_name ); ?>"
			value="<?php echo esc_attr( $input_value ); ?>"
			aria-label="<?php echo esc_attr( $label ); ?>"
			min="<?php echo esc_attr( $min_value ); ?>"
			<?php if ( 0 < $max_value ) : ?>
				max="<?php echo esc_attr( $max_value ); ?>"
			<?php endif; ?>
			<?php if ( ! $readonly ) : ?>
				step="<?php echo esc_attr( $step ); ?>"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				inputmode="<?php echo esc_attr( $inputmode ); ?>"
				autocomplete="<?php echo esc_attr( isset( $autocomplete ) ? $autocomplete : 'on' ); ?>"
			<?php endif; ?>
		/>
		<button type="button" class="velune-qty__button" data-qty-action="increase" aria-label="<?php esc_attr_e( 'Increase quantity', 'velune' ); ?>" <?php echo $readonly ? 'disabled' : ''; ?>>
			<span aria-hidden="true">+</span>
		</button>
	</div>
	<?php
}
