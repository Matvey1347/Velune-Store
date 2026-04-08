<?php
/**
 * Site footer template.
 *
 * @package Velune
 */

$shop_url        = velune_get_shop_url();
$cart_url        = velune_get_cart_url();
$checkout_url    = velune_get_checkout_url();
$login_url       = velune_get_login_url();
$register_url    = velune_get_register_url();
$account_url     = velune_get_account_url();
$blog_url        = velune_get_blog_url();
$subscription_url = velune_get_subscription_url();
$forgot_password = velune_get_forgot_password_url();
?>
	<footer class="site-footer">
		<div class="container footer-grid">
			<div>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="brand-mark brand-mark--footer">VELUNE</a>
				<p><?php esc_html_e( 'Simple, calm, expensive.', 'velune' ); ?></p>
			</div>
			<div>
				<h3><?php esc_html_e( 'Shop', 'velune' ); ?></h3>
				<a href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Products', 'velune' ); ?></a>
				<a href="<?php echo esc_url( $cart_url ); ?>"><?php esc_html_e( 'Cart', 'velune' ); ?></a>
				<a href="<?php echo esc_url( $checkout_url ); ?>"><?php esc_html_e( 'Checkout', 'velune' ); ?></a>
			</div>
			<div>
				<h3><?php esc_html_e( 'Account', 'velune' ); ?></h3>
				<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Login', 'velune' ); ?></a>
				<a href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Register', 'velune' ); ?></a>
				<a href="<?php echo esc_url( $account_url ); ?>"><?php esc_html_e( 'My account', 'velune' ); ?></a>
			</div>
			<div>
				<h3><?php esc_html_e( 'Journal', 'velune' ); ?></h3>
				<a href="<?php echo esc_url( $blog_url ); ?>"><?php esc_html_e( 'Blog', 'velune' ); ?></a>
				<a href="<?php echo esc_url( $subscription_url ); ?>"><?php esc_html_e( 'Subscription', 'velune' ); ?></a>
				<a href="<?php echo esc_url( $forgot_password ); ?>"><?php esc_html_e( 'Forgot password', 'velune' ); ?></a>
			</div>
		</div>
	</footer>

	<?php get_template_part( 'template-parts/cart', 'drawer' ); ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
