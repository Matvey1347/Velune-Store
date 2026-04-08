<?php
/**
 * Site header template.
 *
 * @package Velune
 */

$subscription_url = velune_get_subscription_url();
$account_url      = velune_get_account_url();
$cart_count       = velune_get_cart_count();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="shortcut icon" href="<?php echo esc_url( get_theme_file_uri( '/assets/images/favicon.ico' ) ); ?>" type="image/x-icon">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="site-shell">
	<div class="announcement-bar">
		<div class="container announcement-bar__inner">
			<p><?php esc_html_e( 'Complimentary shipping on bundles. Subscription savings up to 20%.', 'velune' ); ?></p>
			<a href="<?php echo esc_url( $subscription_url ); ?>"><?php esc_html_e( 'Subscribe & save', 'velune' ); ?></a>
		</div>
	</div>

	<header class="site-header" id="top">
		<div class="container site-header__inner">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="brand-mark" aria-label="<?php esc_attr_e( 'VELUNE homepage', 'velune' ); ?>">VELUNE</a>

			<nav class="site-nav" aria-label="<?php esc_attr_e( 'Primary navigation', 'velune' ); ?>">
				<?php velune_render_navigation_links(); ?>
			</nav>

			<div class="header-actions">
				<button class="icon-button search-toggle" type="button" aria-label="<?php esc_attr_e( 'Open search', 'velune' ); ?>">
					<span><?php esc_html_e( 'Search', 'velune' ); ?></span>
				</button>
				<a class="icon-button" href="<?php echo esc_url( $account_url ); ?>" aria-label="<?php esc_attr_e( 'Open account', 'velune' ); ?>">
					<span><?php esc_html_e( 'Account', 'velune' ); ?></span>
				</a>
				<button class="icon-button cart-toggle" type="button" aria-label="<?php esc_attr_e( 'Open cart', 'velune' ); ?>" data-cart-toggle>
					<span><?php esc_html_e( 'Cart', 'velune' ); ?></span>
					<span class="cart-count" data-cart-count><?php echo esc_html( (string) $cart_count ); ?></span>
				</button>
				<button class="mobile-nav-toggle" type="button" aria-label="<?php esc_attr_e( 'Toggle navigation', 'velune' ); ?>" data-mobile-nav-toggle>
					<span></span><span></span>
				</button>
			</div>
		</div>

		<div class="mobile-nav" data-mobile-nav>
			<?php velune_render_navigation_links(); ?>
		</div>
	</header>
