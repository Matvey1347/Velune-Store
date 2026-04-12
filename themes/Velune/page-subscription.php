<?php
/**
 * Subscription page template.
 *
 * @package Velune
 */

get_header();

$active_subscription_plan = velune_get_active_subscription_plan();
$bundle_media             = velune_get_subscription_bundle_media( $active_subscription_plan );
$checkout_error_message   = velune_get_subscription_checkout_error_message();

$hero_after_html  = '<ul class="subscription-highlights">';
$hero_after_html .= '<li>' . esc_html__( 'Managed recurring billing with Stripe', 'velune' ) . '</li>';
$hero_after_html .= '<li>' . esc_html__( 'Cancel or resume auto-renew from account', 'velune' ) . '</li>';
$hero_after_html .= '<li>' . esc_html__( 'Clear subscription status and renewal dates', 'velune' ) . '</li>';
$hero_after_html .= '</ul>';
?>
<main class="subscription-page">
	<?php
	get_template_part(
		'template-parts/common/page-hero',
		null,
		array(
			'breadcrumbs' => array(
				array(
					'label' => __( 'Home', 'velune' ),
					'url'   => home_url( '/' ),
				),
				array(
					'label' => __( 'Subscription', 'velune' ),
				),
			),
			'eyebrow'     => __( 'Subscription', 'velune' ),
			'title'       => __( 'Recurring skincare built around bundles.', 'velune' ),
			'description' => __( 'Choose an active plan below and start securely with Stripe Checkout.', 'velune' ),
			'after_html'  => $hero_after_html,
		)
	);

	get_template_part(
		'template-parts/subscription/bundle-section',
		null,
		array(
			'active_subscription_plan' => $active_subscription_plan,
			'bundle_media_image'       => $bundle_media['image'],
			'bundle_media_alt'         => $bundle_media['alt'],
			'checkout_error_message'   => $checkout_error_message,
		)
	);
	?>
</main>
<?php
get_footer();
