<?php
/**
 * Subscription page template.
 *
 * @package Velune
 */

get_header();
?>
<main class="subscription-page">
	<section class="page-hero">
		<div class="container">
			<div class="page-hero__content fade-in-up">
				<div class="breadcrumbs"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'velune' ); ?></a><span>/</span><span><?php esc_html_e( 'Subscription', 'velune' ); ?></span></div>
				<span class="eyebrow"><?php esc_html_e( 'Subscription', 'velune' ); ?></span>
				<h1><?php esc_html_e( 'Recurring skincare built around bundles.', 'velune' ); ?></h1>
				<p><?php esc_html_e( 'Choose an active plan below and start securely with Stripe Checkout.', 'velune' ); ?></p>
				<ul class="subscription-highlights">
					<li><?php esc_html_e( 'Managed recurring billing with Stripe', 'velune' ); ?></li>
					<li><?php esc_html_e( 'Cancel or resume auto-renew from account', 'velune' ); ?></li>
					<li><?php esc_html_e( 'Clear subscription status and renewal dates', 'velune' ); ?></li>
				</ul>
			</div>
		</div>
	</section>

	<section class="page-section">
		<div class="container">
			<?php echo do_shortcode( '[wp_sp_subscription_plans]' ); ?>
		</div>
	</section>
</main>
<?php
get_footer();
