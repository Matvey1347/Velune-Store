<?php
/**
 * Site header template.
 *
 * @package Velune
 */

$subscription_url = velune_get_subscription_url();
$is_logged_in     = is_user_logged_in();
$account_url      = $is_logged_in ? velune_get_account_url() : velune_get_login_url();
$account_icon_aria_label = $is_logged_in ? __( 'Open account', 'velune' ) : __( 'Open login', 'velune' );
$account_avatar_url      = '';
$account_initials        = '';

if ( $is_logged_in ) {
	$current_user = wp_get_current_user();

	if ( $current_user instanceof WP_User && $current_user->ID > 0 ) {
		$account_avatar_url = velune_get_user_avatar_url( (int) $current_user->ID, 56 );
		$account_initials   = velune_get_user_initials( $current_user );
	}
}

$account_initials = $account_initials ? $account_initials : esc_html__( 'U', 'velune' );
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
				<a class="icon-button account-button<?php echo $is_logged_in ? ' is-authenticated' : ''; ?>" href="<?php echo esc_url( $account_url ); ?>" aria-label="<?php echo esc_attr( $account_icon_aria_label ); ?>">
					<?php if ( $is_logged_in ) : ?>
						<?php if ( $account_avatar_url ) : ?>
							<img class="account-avatar" src="<?php echo esc_url( $account_avatar_url ); ?>" alt="<?php esc_attr_e( 'Account avatar', 'velune' ); ?>" width="28" height="28" loading="lazy" decoding="async">
						<?php else : ?>
							<span class="account-initials" aria-hidden="true"><?php echo esc_html( $account_initials ); ?></span>
						<?php endif; ?>
					<?php else : ?>
						<span class="header-icon account-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true">
								<circle cx="12" cy="8" r="3.4" stroke="currentColor" stroke-width="1.6"></circle>
								<path d="M5.2 19c1.7-2.8 4-4.2 6.8-4.2s5.1 1.4 6.8 4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path>
							</svg>
						</span>
					<?php endif; ?>
					<span class="header-label-sr"><?php esc_html_e( 'Account', 'velune' ); ?></span>
				</a>
				<button class="icon-button cart-toggle" type="button" aria-label="<?php esc_attr_e( 'Open cart', 'velune' ); ?>" data-cart-toggle>
					<span class="header-icon cart-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true">
							<circle cx="9" cy="19" r="1.5" fill="currentColor"></circle>
							<circle cx="17" cy="19" r="1.5" fill="currentColor"></circle>
							<path d="M3.5 5.5h2.3l1.7 8.2h10l2-6.6H7.1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path>
						</svg>
					</span>
					<span class="header-label-sr"><?php esc_html_e( 'Cart', 'velune' ); ?></span>
					<span class="cart-count" data-cart-count><?php echo esc_html( (string) $cart_count ); ?></span>
				</button>
				<button class="icon-button search-toggle" type="button" aria-label="<?php esc_attr_e( 'Open search', 'velune' ); ?>" aria-controls="velune-header-search-panel" aria-expanded="false" data-search-toggle>
					<span class="header-icon search-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true">
							<circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.6"></circle>
							<path d="M16.2 16.2L20 20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path>
						</svg>
					</span>
					<span class="header-label-sr"><?php esc_html_e( 'Search', 'velune' ); ?></span>
				</button>
				<button class="mobile-nav-toggle" type="button" aria-label="<?php esc_attr_e( 'Toggle navigation', 'velune' ); ?>" data-mobile-nav-toggle>
					<span></span><span></span>
				</button>
			</div>
		</div>

		<div class="mobile-nav" data-mobile-nav>
			<?php velune_render_navigation_links(); ?>
		</div>

		<div class="header-search-panel" id="velune-header-search-panel" aria-hidden="true" data-search-panel>
			<div class="container">
				<div class="header-search-shell">
					<form class="header-search-form" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search" data-live-search-form>
						<label class="header-label-sr" for="velune-live-search-input"><?php esc_html_e( 'Search the store', 'velune' ); ?></label>
						<span class="header-icon search-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true">
								<circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.6"></circle>
								<path d="M16.2 16.2L20 20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path>
							</svg>
						</span>
						<input id="velune-live-search-input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search products, articles, and pages', 'velune' ); ?>" autocomplete="off" data-live-search-input>
						<button class="header-search-close" type="button" aria-label="<?php esc_attr_e( 'Close search', 'velune' ); ?>" data-search-close>
							<span aria-hidden="true">×</span>
						</button>
					</form>
					<div class="header-live-search-results" data-live-search-results aria-live="polite"></div>
				</div>
			</div>
		</div>
	</header>
