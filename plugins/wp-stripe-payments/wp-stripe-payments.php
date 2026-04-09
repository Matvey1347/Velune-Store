<?php
/**
 * Plugin Name: WP Stripe Payments Gateway
 * Plugin URI:  https://example.com/
 * Description: Custom Stripe payment gateway for WooCommerce with extensible architecture.
 * Version:     1.0.0
 * Author:      WP Stripe Payments
 * Text Domain: wp-stripe-payments
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 */

if (! defined('ABSPATH')) {
    exit;
}

define('WP_STRIPE_PAYMENTS_VERSION', '1.0.0');
define('WP_STRIPE_PAYMENTS_FILE', __FILE__);
define('WP_STRIPE_PAYMENTS_PATH', plugin_dir_path(__FILE__));
define('WP_STRIPE_PAYMENTS_URL', plugin_dir_url(__FILE__));
define('WP_STRIPE_PAYMENTS_TEXT_DOMAIN', 'wp-stripe-payments');

spl_autoload_register(static function (string $class): void {
    $prefix = 'WPStripePayments\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    $file = WP_STRIPE_PAYMENTS_PATH . 'includes/' . $relativePath;

    if (file_exists($file)) {
        require_once $file;
    }
});

add_action('plugins_loaded', static function (): void {
    load_plugin_textdomain(
        WP_STRIPE_PAYMENTS_TEXT_DOMAIN,
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

    \WPStripePayments\Core\Plugin::instance()->init();
}, 20);
