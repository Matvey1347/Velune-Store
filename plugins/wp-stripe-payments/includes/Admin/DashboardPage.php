<?php

namespace WPStripePayments\Admin;

class DashboardPage
{
    public function render(): void
    {
        if (! current_user_can('manage_options') && ! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-stripe-payments'));
        }

        $settings = Settings::all();
        $mode = $settings['testmode'] === 'yes' ? __('Test', 'wp-stripe-payments') : __('Live', 'wp-stripe-payments');

        $testKeysConfigured = $settings['test_publishable_key'] !== '' && $settings['test_secret_key'] !== '';
        $liveKeysConfigured = $settings['live_publishable_key'] !== '' && $settings['live_secret_key'] !== '';
        $webhookConfigured = $settings['webhook_secret'] !== '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Stripe Payments Dashboard', 'wp-stripe-payments'); ?></h1>
            <p><?php esc_html_e('Plugin-owned control center for Stripe payment configuration and modules.', 'wp-stripe-payments'); ?></p>

            <table class="widefat striped" style="max-width: 860px;">
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e('Gateway Status', 'wp-stripe-payments'); ?></strong></td>
                        <td><?php echo esc_html($settings['gateway_enabled'] === 'yes' ? __('Enabled', 'wp-stripe-payments') : __('Disabled', 'wp-stripe-payments')); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Current Mode', 'wp-stripe-payments'); ?></strong></td>
                        <td><?php echo esc_html($mode); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Test Keys Configured', 'wp-stripe-payments'); ?></strong></td>
                        <td><?php echo esc_html($testKeysConfigured ? __('Yes', 'wp-stripe-payments') : __('No', 'wp-stripe-payments')); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Live Keys Configured', 'wp-stripe-payments'); ?></strong></td>
                        <td><?php echo esc_html($liveKeysConfigured ? __('Yes', 'wp-stripe-payments') : __('No', 'wp-stripe-payments')); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Webhook Secret Configured', 'wp-stripe-payments'); ?></strong></td>
                        <td><?php echo esc_html($webhookConfigured ? __('Yes', 'wp-stripe-payments') : __('No', 'wp-stripe-payments')); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}
