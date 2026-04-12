<?php
/**
 * Velune theme bootstrap.
 *
 * @package Velune
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$velune_includes = array(
	'/inc/setup.php',
	'/inc/helpers.php',
	'/inc/account.php',
	'/inc/search.php',
	'/inc/cart.php',
	'/inc/auth.php',
);

foreach ( $velune_includes as $velune_include_file ) {
	require_once get_theme_file_path( $velune_include_file );
}
