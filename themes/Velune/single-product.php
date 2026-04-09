<?php
/**
 * WooCommerce single product template.
 *
 * @package Velune
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main class="product-page product-page--single">
	<section class="page-section product-single-section">
		<div class="container">
			<?php
			while ( have_posts() ) :
				the_post();

				wc_get_template_part( 'content', 'single-product' );
			endwhile;
			?>
		</div>
	</section>
</main>
<?php

get_footer();
