<?php

namespace WPStripePayments\Admin;

use WPStripePayments\Subscriptions\CustomerSubscriptionService;

class CustomerSubscriptionsPage
{
    private CustomerSubscriptionService $customerSubscriptionService;

    public function __construct(?CustomerSubscriptionService $customerSubscriptionService = null)
    {
        $this->customerSubscriptionService = $customerSubscriptionService ?? new CustomerSubscriptionService();
    }

    public function render(): void
    {
        if (! current_user_can('manage_options') && ! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-stripe-payments'));
        }

        $rows = $this->customerSubscriptionService->listForAdmin(300);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Customer Subscriptions', 'wp-stripe-payments'); ?></h1>
            <p><?php esc_html_e('Local subscription records synced from Stripe checkout and billing webhooks.', 'wp-stripe-payments'); ?></p>

            <?php if (empty($rows)) : ?>
                <p><?php esc_html_e('No subscription records found yet.', 'wp-stripe-payments'); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                    <tr>
                        <th><?php esc_html_e('Email', 'wp-stripe-payments'); ?></th>
                        <th><?php esc_html_e('Plan', 'wp-stripe-payments'); ?></th>
                        <th><?php esc_html_e('Amount', 'wp-stripe-payments'); ?></th>
                        <th><?php esc_html_e('Interval', 'wp-stripe-payments'); ?></th>
                        <th><?php esc_html_e('Status', 'wp-stripe-payments'); ?></th>
                        <th><?php esc_html_e('Auto-renew', 'wp-stripe-payments'); ?></th>
                        <th><?php esc_html_e('Next Billing', 'wp-stripe-payments'); ?></th>
                        <th><?php esc_html_e('Stripe Subscription ID', 'wp-stripe-payments'); ?></th>
                        <th><?php esc_html_e('Stripe Customer ID', 'wp-stripe-payments'); ?></th>
                        <th><?php esc_html_e('Updated', 'wp-stripe-payments'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td><?php echo esc_html((string) ($row['customer_email'] ?? '')); ?></td>
                            <td>
                                <?php
                                $planTitle = (string) ($row['plan_snapshot_title'] ?? '');
                                $planId = (int) ($row['plan_id'] ?? 0);
                                if ($planTitle !== '') {
                                    echo esc_html($planTitle);
                                } elseif ($planId > 0) {
                                    echo esc_html(get_the_title($planId));
                                } else {
                                    echo esc_html__('Unknown', 'wp-stripe-payments');
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $amount = isset($row['plan_snapshot_price']) ? (float) $row['plan_snapshot_price'] : 0;
                                echo wp_kses_post(wc_price($amount));
                                ?>
                            </td>
                            <td><?php echo esc_html((string) ($row['billing_interval'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($row['status'] ?? '')); ?></td>
                            <td><?php echo esc_html(! empty($row['cancel_at_period_end']) ? __('Off', 'wp-stripe-payments') : __('On', 'wp-stripe-payments')); ?></td>
                            <td><?php echo esc_html((string) ($row['current_period_end'] ?? $row['next_billing_date'] ?? '')); ?></td>
                            <td><code><?php echo esc_html((string) ($row['stripe_subscription_id'] ?? '')); ?></code></td>
                            <td><code><?php echo esc_html((string) ($row['stripe_customer_id'] ?? '')); ?></code></td>
                            <td><?php echo esc_html((string) ($row['updated_at'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
