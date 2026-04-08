<?php
/**
 * Subscription informational page template.
 *
 * No subscription business logic is implemented in this phase.
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
				<p><?php esc_html_e( 'Simple options. Clear savings. Easy management from the account area.', 'velune' ); ?></p>
				<ul class="subscription-highlights">
					<li><?php esc_html_e( 'Save up to 20%', 'velune' ); ?></li>
					<li><?php esc_html_e( 'Monthly or every 60 days', 'velune' ); ?></li>
					<li><?php esc_html_e( 'Pause or cancel inside account', 'velune' ); ?></li>
				</ul>
			</div>
		</div>
	</section>

	<section class="page-section">
		<div class="container">
			<div class="plan-grid">
				<article class="plan-card fade-in-up">
					<span class="eyebrow"><?php esc_html_e( 'Starter', 'velune' ); ?></span>
					<h3><?php esc_html_e( 'Starter Bundle Subscription', 'velune' ); ?></h3>
					<div class="price-line"><strong>$70</strong><span><?php esc_html_e( '/ delivery', 'velune' ); ?></span></div>
					<p><?php esc_html_e( 'Body Wash + Face Cream. Entry point for customers who want a calmer routine.', 'velune' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Save 20% compared to one-time purchase', 'velune' ); ?></li>
						<li><?php esc_html_e( 'Monthly or every 60 days', 'velune' ); ?></li>
						<li><?php esc_html_e( 'Editable from account', 'velune' ); ?></li>
					</ul>
					<div class="stack-actions">
						<a class="button button--primary button--full" href="<?php echo esc_url( velune_get_account_url() ); ?>"><?php esc_html_e( 'Coming in phase 2', 'velune' ); ?></a>
					</div>
				</article>

				<article class="plan-card is-featured fade-in-up delay-1">
					<span class="eyebrow"><?php esc_html_e( 'Best value', 'velune' ); ?></span>
					<h3><?php esc_html_e( 'Complete Bundle Subscription', 'velune' ); ?></h3>
					<div class="price-line"><strong>$86</strong><span><?php esc_html_e( '/ delivery', 'velune' ); ?></span></div>
					<p><?php esc_html_e( 'Body Wash + Face Cream + Serum. Best choice for subscription-first revenue.', 'velune' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Save 20% compared to one-time purchase', 'velune' ); ?></li>
						<li><?php esc_html_e( 'Core high-AOV subscription option', 'velune' ); ?></li>
						<li><?php esc_html_e( 'Prepared for WooCommerce subscriptions + Stripe', 'velune' ); ?></li>
					</ul>
					<div class="stack-actions">
						<a class="button button--primary button--full" href="<?php echo esc_url( velune_get_account_url() ); ?>"><?php esc_html_e( 'Coming in phase 2', 'velune' ); ?></a>
					</div>
				</article>
			</div>

			<div class="subscription-layout" style="margin-top:24px;">
				<div class="page-card fade-in-up">
					<h3><?php esc_html_e( 'How it works', 'velune' ); ?></h3>
					<div class="feature-stack">
						<article>
							<h3><?php esc_html_e( 'Choose a bundle', 'velune' ); ?></h3>
							<p><?php esc_html_e( 'Subscription exists only where it improves retention and order value.', 'velune' ); ?></p>
						</article>
						<article>
							<h3><?php esc_html_e( 'Select cadence', 'velune' ); ?></h3>
							<p><?php esc_html_e( 'Monthly and 60-day delivery are enough. More choice is not better here.', 'velune' ); ?></p>
						</article>
						<article>
							<h3><?php esc_html_e( 'Manage in account', 'velune' ); ?></h3>
							<p><?php esc_html_e( 'Customers will be able to update payment method, skip, pause, or cancel.', 'velune' ); ?></p>
						</article>
					</div>
				</div>
				<aside class="sidebar-card fade-in-up delay-1">
					<h3><?php esc_html_e( 'Why bundles first', 'velune' ); ?></h3>
					<p><?php esc_html_e( 'Single-product subscriptions are weaker. Bundles lift AOV, reduce decision fatigue, and match the brand logic better.', 'velune' ); ?></p>
					<a class="button button--secondary button--full" href="<?php echo esc_url( velune_get_account_url() ); ?>"><?php esc_html_e( 'Preview account area', 'velune' ); ?></a>
				</aside>
			</div>
		</div>
	</section>
	<?php
	// TODO: Implement real subscription products and recurring billing logic in the next phase.
	?>
</main>
<?php
get_footer();
