<?php

namespace WPStripePayments\Admin;

class CustomersPage
{
    public function render(): void
    {
        if (! current_user_can('manage_options') && ! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-stripe-payments'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Stripe Customers', 'wp-stripe-payments'); ?></h1>
            <p><?php esc_html_e('Customer management will be added in a future iteration.', 'wp-stripe-payments'); ?></p>
        </div>
        <?php
    }
}
