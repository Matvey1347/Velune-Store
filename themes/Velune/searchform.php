<?php
/**
 * Search form template.
 *
 * @package Velune
 */
?>
<form role="search" method="get" class="search-field__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<input type="search" placeholder="<?php esc_attr_e( 'Search ritual, texture, subscription', 'velune' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" />
</form>
