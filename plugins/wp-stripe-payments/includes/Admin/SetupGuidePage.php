<?php

namespace WPStripePayments\Admin;

class SetupGuidePage
{
    public function render(): void
    {
        if (! AdminContext::canManage()) {
            AdminContext::denyAccess();
        }

        $settings = Settings::all();
        $webhookEndpoint = Settings::webhookEndpointUrl();
        $portalReturnUrl = Settings::billingPortalReturnUrl();
        $checkoutTestUrl = home_url('/subscription/');

        $testKeysConfigured = $settings['test_publishable_key'] !== '' && $settings['test_secret_key'] !== '';
        $liveKeysConfigured = $settings['live_publishable_key'] !== '' && $settings['live_secret_key'] !== '';
        $webhookConfigured = $settings['webhook_secret'] !== '';
        $billingPortalConfigured = $settings['billing_portal_enabled'] === 'yes';
        $gatewayEnabled = $settings['gateway_enabled'] === 'yes';
        $mode = $settings['testmode'] === 'yes' ? 'test' : 'live';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(AdminContext::BRAND_NAME . ' ' . __('Setup Guide', 'wp-stripe-payments')); ?></h1>
            <p class="wp-sp-page-intro"><?php esc_html_e('Follow these steps to configure Stripe safely. Complete test mode first, then switch to live mode only after end-to-end verification.', 'wp-stripe-payments'); ?></p>

            <div class="wp-sp-grid" style="margin-bottom:16px;">
                <div class="wp-sp-card">
                    <h3><?php esc_html_e('Setup Status', 'wp-stripe-payments'); ?></h3>
                    <ul class="wp-sp-checklist">
                        <li><strong><?php esc_html_e('API keys configured', 'wp-stripe-payments'); ?>:</strong> <span class="wp-sp-status-badge <?php echo esc_attr($testKeysConfigured || $liveKeysConfigured ? 'is-good' : 'is-warning'); ?>"><?php echo esc_html($testKeysConfigured || $liveKeysConfigured ? __('Yes', 'wp-stripe-payments') : __('No', 'wp-stripe-payments')); ?></span></li>
                        <li><strong><?php esc_html_e('Current mode', 'wp-stripe-payments'); ?>:</strong> <span class="wp-sp-status-badge is-neutral"><?php echo esc_html(strtoupper($mode)); ?></span></li>
                        <li><strong><?php esc_html_e('Webhook secret configured', 'wp-stripe-payments'); ?>:</strong> <span class="wp-sp-status-badge <?php echo esc_attr($webhookConfigured ? 'is-good' : 'is-warning'); ?>"><?php echo esc_html($webhookConfigured ? __('Yes', 'wp-stripe-payments') : __('No', 'wp-stripe-payments')); ?></span></li>
                        <li><strong><?php esc_html_e('Billing portal enabled', 'wp-stripe-payments'); ?>:</strong> <span class="wp-sp-status-badge <?php echo esc_attr($billingPortalConfigured ? 'is-good' : 'is-warning'); ?>"><?php echo esc_html($billingPortalConfigured ? __('Yes', 'wp-stripe-payments') : __('No', 'wp-stripe-payments')); ?></span></li>
                        <li><strong><?php esc_html_e('Gateway enabled', 'wp-stripe-payments'); ?>:</strong> <span class="wp-sp-status-badge <?php echo esc_attr($gatewayEnabled ? 'is-good' : 'is-warning'); ?>"><?php echo esc_html($gatewayEnabled ? __('Yes', 'wp-stripe-payments') : __('No', 'wp-stripe-payments')); ?></span></li>
                    </ul>
                </div>

                <div class="wp-sp-card">
                    <h3><?php esc_html_e('Plugin-Generated Values', 'wp-stripe-payments'); ?></h3>
                    <p><strong><?php esc_html_e('Webhook endpoint', 'wp-stripe-payments'); ?></strong></p>
                    <div class="wp-sp-copy-wrap">
                        <input id="wp-sp-guide-webhook-endpoint" class="regular-text wp-sp-copy-field" readonly value="<?php echo esc_attr($webhookEndpoint); ?>" />
                        <button type="button" class="button" data-copy-target="#wp-sp-guide-webhook-endpoint"><?php esc_html_e('Copy', 'wp-stripe-payments'); ?></button>
                    </div>
                    <p><strong><?php esc_html_e('Billing portal return URL', 'wp-stripe-payments'); ?></strong></p>
                    <div class="wp-sp-copy-wrap">
                        <input id="wp-sp-guide-portal-return" class="regular-text wp-sp-copy-field" readonly value="<?php echo esc_attr($portalReturnUrl); ?>" />
                        <button type="button" class="button" data-copy-target="#wp-sp-guide-portal-return"><?php esc_html_e('Copy', 'wp-stripe-payments'); ?></button>
                    </div>
                </div>
            </div>

            <div class="notice notice-warning"><p><?php esc_html_e('Safety rule: never switch to live mode until checkout, webhook, and subscription lifecycle are confirmed in test mode.', 'wp-stripe-payments'); ?></p></div>

            <div class="wp-sp-steps">
                <div class="wp-sp-step">
                    <h3><?php esc_html_e('Create or access your Stripe account', 'wp-stripe-payments'); ?></h3>
                    <p><?php esc_html_e('Open Stripe Dashboard and choose the correct account/workspace for this store.', 'wp-stripe-payments'); ?></p>
                    <p><a href="https://dashboard.stripe.com" target="_blank" rel="noopener noreferrer" class="button button-secondary"><?php esc_html_e('Open Stripe Dashboard', 'wp-stripe-payments'); ?></a></p>
                </div>

                <div class="wp-sp-step">
                    <h3><?php esc_html_e('Locate Test API keys', 'wp-stripe-payments'); ?></h3>
                    <p><?php esc_html_e('In Stripe Dashboard: Developers > API keys. Copy Test Publishable key (pk_test_) and Test Secret key (sk_test_).', 'wp-stripe-payments'); ?></p>
                </div>

                <div class="wp-sp-step">
                    <h3><?php esc_html_e('Save Test keys in plugin settings', 'wp-stripe-payments'); ?></h3>
                    <p><?php echo wp_kses_post(sprintf(__('Go to %s and paste test keys. Keep Test Mode enabled.', 'wp-stripe-payments'), '<a href="' . esc_url(admin_url('admin.php?page=wp-stripe-payments-settings')) . '">' . esc_html__('Settings', 'wp-stripe-payments') . '</a>')); ?></p>
                </div>

                <div class="wp-sp-step">
                    <h3><?php esc_html_e('Configure webhook endpoint and secret', 'wp-stripe-payments'); ?></h3>
                    <p><?php esc_html_e('In Stripe Dashboard: Developers > Webhooks > Add endpoint. Use the endpoint URL above.', 'wp-stripe-payments'); ?></p>
                    <p><?php esc_html_e('Subscribe at minimum to: checkout.session.completed, customer.subscription.updated, customer.subscription.deleted, invoice.paid, invoice.payment_failed, invoice.finalized, invoice.voided, charge.refunded.', 'wp-stripe-payments'); ?></p>
                    <p><?php esc_html_e('Reveal the signing secret (whsec_) and save it in plugin Settings > Webhooks.', 'wp-stripe-payments'); ?></p>
                </div>

                <div class="wp-sp-step">
                    <h3><?php esc_html_e('Enable billing portal (optional but recommended)', 'wp-stripe-payments'); ?></h3>
                    <p><?php esc_html_e('In Stripe Dashboard, configure Billing Portal settings, then keep Billing Portal enabled in plugin settings.', 'wp-stripe-payments'); ?></p>
                </div>

                <div class="wp-sp-step">
                    <h3><?php esc_html_e('Test checkout and subscription lifecycle', 'wp-stripe-payments'); ?></h3>
                    <p><?php esc_html_e('Complete a test checkout using Stripe test cards and verify records in Customer Subscriptions, Billing History, Analytics, and Logs.', 'wp-stripe-payments'); ?></p>
                    <div class="wp-sp-copy-wrap">
                        <input id="wp-sp-guide-checkout-url" class="regular-text wp-sp-copy-field" readonly value="<?php echo esc_attr($checkoutTestUrl); ?>" />
                        <button type="button" class="button" data-copy-target="#wp-sp-guide-checkout-url"><?php esc_html_e('Copy Test Checkout URL', 'wp-stripe-payments'); ?></button>
                    </div>
                </div>

                <div class="wp-sp-step">
                    <h3><?php esc_html_e('Go live safely', 'wp-stripe-payments'); ?></h3>
                    <p><?php esc_html_e('Return to API keys and copy Live Publishable (pk_live_) and Live Secret (sk_live_) keys.', 'wp-stripe-payments'); ?></p>
                    <p><?php esc_html_e('Create or update a live webhook endpoint and save the live webhook signing secret.', 'wp-stripe-payments'); ?></p>
                    <p><?php esc_html_e('Switch plugin mode from Test to Live only after both live keys and webhook secret are confirmed.', 'wp-stripe-payments'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
}
