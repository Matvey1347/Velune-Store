<?php
/**
 * WooCommerce fallback template.
 *
 * @package Velune
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main class="page-section">
	<div class="container">
		<div class="checkout-card fade-in-up is-visible">
			<?php woocommerce_content(); ?>
		</div>
	</div>
</main>
<?php
get_footer();
