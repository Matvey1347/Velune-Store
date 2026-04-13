<?php

namespace WPStripePayments\Admin;

use WPStripePayments\Subscriptions\PlanRepository;

class AdminAssets
{
    public function enqueue(string $hook): void
    {
        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        $postType = isset($_GET['post_type']) ? sanitize_key((string) wp_unslash($_GET['post_type'])) : '';
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        $pluginPages = [
            'wp-stripe-payments',
            'wp-stripe-payments-setup-guide',
            'wp-stripe-payments-settings',
            'wp-stripe-payments-customer-subscriptions',
            'wp-stripe-payments-analytics',
            'wp-stripe-payments-logs',
        ];

        $isPlanScreen = $postType === PlanRepository::POST_TYPE
            || ($screen instanceof \WP_Screen && $screen->post_type === PlanRepository::POST_TYPE);

        if (! in_array($page, $pluginPages, true) && ! $isPlanScreen) {
            return;
        }

        wp_enqueue_style(
            'wp-sp-admin',
            WP_STRIPE_PAYMENTS_URL . 'assets/css/admin-ui.css',
            [],
            WP_STRIPE_PAYMENTS_VERSION
        );

        wp_enqueue_script(
            'wp-sp-admin',
            WP_STRIPE_PAYMENTS_URL . 'assets/js/admin-ui.js',
            [],
            WP_STRIPE_PAYMENTS_VERSION,
            true
        );
    }
}
