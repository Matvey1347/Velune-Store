<?php
/**
 * Shared subscription bundle section.
 *
 * @package Velune
 *
 * @var array<string,mixed> $args Template arguments.
 */

$active_subscription_plan = isset( $args['active_subscription_plan'] ) && is_array( $args['active_subscription_plan'] ) ? $args['active_subscription_plan'] : null;
$bundle_media_image       = isset( $args['bundle_media_image'] ) ? (string) $args['bundle_media_image'] : get_theme_file_uri( '/assets/images/bundle/bundle.webp' );
$bundle_media_alt         = isset( $args['bundle_media_alt'] ) ? (string) $args['bundle_media_alt'] : __( 'VELUNE skincare bundle set', 'velune' );
$checkout_error_message   = isset( $args['checkout_error_message'] ) ? (string) $args['checkout_error_message'] : '';
?>
<section class="bundle-section section-lg" id="subscription">
	<div class="container bundle-grid">
		<div class="bundle-copy fade-in-up">
			<span class="eyebrow"><?php esc_html_e( 'Core business', 'velune' ); ?></span>
			<h2><?php esc_html_e( 'Build the routine around bundles.', 'velune' ); ?></h2>
			<p><?php esc_html_e( 'Bundles are where the experience becomes simpler. One selection. Better value. Less friction. The subscription layer is designed around that principle.', 'velune' ); ?></p>
		</div>
		<div class="bundle-media fade-in-up delay-1">
			<div class="bundle-media__frame">
				<img src="<?php echo esc_url( $bundle_media_image ); ?>" alt="<?php echo esc_attr( $bundle_media_alt ); ?>" loading="lazy" />
			</div>
		</div>
		<div class="bundle-copy fade-in-up delay-1">
			<?php if ( '' !== $checkout_error_message ) : ?>
				<p><?php echo esc_html( $checkout_error_message ); ?></p>
			<?php endif; ?>

			<?php if ( is_array( $active_subscription_plan ) ) : ?>
				<?php
				$plan_id             = isset( $active_subscription_plan['id'] ) ? (int) $active_subscription_plan['id'] : 0;
				$plan_title          = isset( $active_subscription_plan['title'] ) ? (string) $active_subscription_plan['title'] : '';
				$plan_description    = isset( $active_subscription_plan['description'] ) ? (string) $active_subscription_plan['description'] : '';
				$plan_price          = isset( $active_subscription_plan['price'] ) ? wc_price( (float) $active_subscription_plan['price'] ) : wc_price( 0 );
				$plan_billing_raw    = isset( $active_subscription_plan['billing_interval'] ) ? (string) $active_subscription_plan['billing_interval'] : 'month';
				$plan_billing_labels = array(
					'day'   => __( 'daily', 'velune' ),
					'week'  => __( 'weekly', 'velune' ),
					'month' => __( 'monthly', 'velune' ),
					'year'  => __( 'yearly', 'velune' ),
				);
				$plan_billing_label = $plan_billing_labels[ $plan_billing_raw ] ?? $plan_billing_raw;
				$checkout_is_ready  = ! empty( $active_subscription_plan['stripe_price_id'] );
				$checkout_action    = home_url( '/' );
				?>
				<div class="feature-stack">
					<article>
						<h3><?php echo esc_html( '' !== $plan_title ? $plan_title : __( 'Subscription plan', 'velune' ) ); ?></h3>
						<?php if ( '' !== $plan_description ) : ?>
							<p><?php echo wp_kses_post( $plan_description ); ?></p>
						<?php endif; ?>
					</article>
				</div>
				<div class="price-line"><strong><?php echo wp_kses_post( $plan_price ); ?></strong><span> / <?php echo esc_html( $plan_billing_label ); ?></span></div>
				<form method="post" action="<?php echo esc_url( $checkout_action ); ?>" class="stack-actions" style="margin-top:16px;">
					<input type="hidden" name="wp_sp_front_checkout" value="1" />
					<input type="hidden" name="action" value="wp_sp_start_subscription_checkout" />
					<input type="hidden" name="plan_id" value="<?php echo esc_attr( (string) $plan_id ); ?>" />
					<?php wp_nonce_field( 'wp_sp_start_subscription_checkout_' . $plan_id ); ?>
					<?php if ( ! is_user_logged_in() ) : ?>
						<p><label><?php esc_html_e( 'Email', 'velune' ); ?> <input type="email" name="email" required /></label></p>
					<?php endif; ?>
					<?php if ( ! $checkout_is_ready ) : ?>
						<p class="helper-text"><?php esc_html_e( 'This plan is not ready for checkout yet. Please contact support.', 'velune' ); ?></p>
					<?php endif; ?>
					<button type="submit" class="button button--primary button--full" <?php disabled( $checkout_is_ready, false ); ?>><?php esc_html_e( 'Subscribe', 'velune' ); ?></button>
				</form>
			<?php else : ?>
				<div class="feature-stack">
					<article>
						<h3><?php esc_html_e( 'Subscription plan unavailable', 'velune' ); ?></h3>
						<p><?php esc_html_e( 'No active admin-managed subscription plan is available yet.', 'velune' ); ?></p>
					</article>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
