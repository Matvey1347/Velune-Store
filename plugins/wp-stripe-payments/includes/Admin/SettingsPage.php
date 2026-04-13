<?php

namespace WPStripePayments\Admin;

class SettingsPage
{
    public function maybeSave(): void
    {
        if (! is_admin()) {
            return;
        }

        if (! isset($_GET['page']) || $_GET['page'] !== 'wp-stripe-payments-settings') {
            return;
        }

        if (! isset($_POST['wp_stripe_payments_settings_nonce'])) {
            return;
        }

        if (! AdminContext::canManage()) {
            return;
        }

        check_admin_referer('wp_stripe_payments_save_settings', 'wp_stripe_payments_settings_nonce');

        $raw = isset($_POST['wp_stripe_payments']) && is_array($_POST['wp_stripe_payments'])
            ? wp_unslash($_POST['wp_stripe_payments'])
            : [];

        $raw['gateway_enabled'] = isset($raw['gateway_enabled']) ? 'yes' : 'no';
        $raw['testmode'] = isset($raw['testmode']) ? 'yes' : 'no';
        $raw['billing_portal_enabled'] = isset($raw['billing_portal_enabled']) ? 'yes' : 'no';
        $raw['debug_logging_enabled'] = isset($raw['debug_logging_enabled']) ? 'yes' : 'no';

        Settings::save($raw);

        wp_safe_redirect(add_query_arg([
            'page' => 'wp-stripe-payments-settings',
            'updated' => '1',
        ], admin_url('admin.php')));
        exit;
    }

    public function render(): void
    {
        if (! AdminContext::canManage()) {
            AdminContext::denyAccess();
        }

        $settings = Settings::all();
        $webhookEndpoint = Settings::webhookEndpointUrl();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(AdminContext::BRAND_NAME . ' ' . __('Settings', 'wp-stripe-payments')); ?></h1>
            <p class="wp-sp-page-intro"><?php esc_html_e('Configure Stripe mode, keys, webhook security, checkout text, billing portal behavior, and logging controls.', 'wp-stripe-payments'); ?></p>

            <?php if (isset($_GET['updated']) && $_GET['updated'] === '1') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'wp-stripe-payments'); ?></p></div>
            <?php endif; ?>

            <div class="notice notice-info"><p><?php echo wp_kses_post(sprintf(__('Need help? Use the %s page for a step-by-step Stripe setup workflow.', 'wp-stripe-payments'), '<a href="' . esc_url(admin_url('admin.php?page=wp-stripe-payments-setup-guide')) . '">' . esc_html__('Setup Guide', 'wp-stripe-payments') . '</a>')); ?></p></div>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=wp-stripe-payments-settings')); ?>">
                <?php wp_nonce_field('wp_stripe_payments_save_settings', 'wp_stripe_payments_settings_nonce'); ?>

                <div class="wp-sp-grid-2">
                    <div>
                        <div class="wp-sp-card" style="margin-bottom:12px;">
                            <h2><?php esc_html_e('General', 'wp-stripe-payments'); ?></h2>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Gateway', 'wp-stripe-payments'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="wp_stripe_payments[gateway_enabled]" value="yes" <?php checked($settings['gateway_enabled'], 'yes'); ?> />
                                            <?php esc_html_e('Enable this payment method in WooCommerce checkout', 'wp-stripe-payments'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Turn this off to pause new Stripe checkouts while keeping records accessible.', 'wp-stripe-payments'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Mode', 'wp-stripe-payments'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="wp_stripe_payments[testmode]" value="yes" <?php checked($settings['testmode'], 'yes'); ?> />
                                            <?php esc_html_e('Use Stripe Test Mode', 'wp-stripe-payments'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Test mode uses test API keys and does not charge real cards.', 'wp-stripe-payments'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="wp-sp-card" style="margin-bottom:12px;">
                            <h2><?php esc_html_e('API Keys', 'wp-stripe-payments'); ?></h2>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><label for="wp_stripe_test_publishable_key"><?php esc_html_e('Test Publishable Key', 'wp-stripe-payments'); ?></label></th>
                                    <td>
                                        <input name="wp_stripe_payments[test_publishable_key]" id="wp_stripe_test_publishable_key" class="regular-text" type="text" value="<?php echo esc_attr($settings['test_publishable_key']); ?>" />
                                        <p class="description"><?php esc_html_e('Starts with pk_test_. Used in browser-side Stripe flows.', 'wp-stripe-payments'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wp_stripe_test_secret_key"><?php esc_html_e('Test Secret Key', 'wp-stripe-payments'); ?></label></th>
                                    <td>
                                        <input name="wp_stripe_payments[test_secret_key]" id="wp_stripe_test_secret_key" class="regular-text" type="password" value="<?php echo esc_attr($settings['test_secret_key']); ?>" autocomplete="new-password" />
                                        <p class="description"><?php esc_html_e('Starts with sk_test_. Keep this private.', 'wp-stripe-payments'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wp_stripe_live_publishable_key"><?php esc_html_e('Live Publishable Key', 'wp-stripe-payments'); ?></label></th>
                                    <td>
                                        <input name="wp_stripe_payments[live_publishable_key]" id="wp_stripe_live_publishable_key" class="regular-text" type="text" value="<?php echo esc_attr($settings['live_publishable_key']); ?>" />
                                        <p class="description"><?php esc_html_e('Starts with pk_live_. Used after production go-live.', 'wp-stripe-payments'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wp_stripe_live_secret_key"><?php esc_html_e('Live Secret Key', 'wp-stripe-payments'); ?></label></th>
                                    <td>
                                        <input name="wp_stripe_payments[live_secret_key]" id="wp_stripe_live_secret_key" class="regular-text" type="password" value="<?php echo esc_attr($settings['live_secret_key']); ?>" autocomplete="new-password" />
                                        <p class="description"><?php esc_html_e('Starts with sk_live_. Keep this private.', 'wp-stripe-payments'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="wp-sp-card" style="margin-bottom:12px;">
                            <h2><?php esc_html_e('Webhooks', 'wp-stripe-payments'); ?></h2>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Webhook Endpoint URL', 'wp-stripe-payments'); ?></th>
                                    <td>
                                        <div class="wp-sp-copy-wrap">
                                            <input id="wp-sp-webhook-endpoint" class="regular-text wp-sp-copy-field" type="text" readonly value="<?php echo esc_attr($webhookEndpoint); ?>" />
                                            <button type="button" class="button" data-copy-target="#wp-sp-webhook-endpoint"><?php esc_html_e('Copy', 'wp-stripe-payments'); ?></button>
                                        </div>
                                        <p class="description"><?php esc_html_e('Use this URL in Stripe webhook endpoint configuration.', 'wp-stripe-payments'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wp_stripe_webhook_secret"><?php esc_html_e('Webhook Signing Secret', 'wp-stripe-payments'); ?></label></th>
                                    <td>
                                        <input name="wp_stripe_payments[webhook_secret]" id="wp_stripe_webhook_secret" class="regular-text" type="password" value="<?php echo esc_attr($settings['webhook_secret']); ?>" autocomplete="new-password" />
                                        <p class="description"><?php esc_html_e('Starts with whsec_. Used to validate Stripe webhook signatures.', 'wp-stripe-payments'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div>
                        <div class="wp-sp-card" style="margin-bottom:12px;">
                            <h2><?php esc_html_e('Checkout / Gateway Public Text', 'wp-stripe-payments'); ?></h2>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><label for="wp_stripe_gateway_title"><?php esc_html_e('Public Gateway Title', 'wp-stripe-payments'); ?></label></th>
                                    <td>
                                        <input name="wp_stripe_payments[title]" id="wp_stripe_gateway_title" class="regular-text" type="text" value="<?php echo esc_attr($settings['title']); ?>" />
                                        <p class="description"><?php esc_html_e('Displayed at checkout as the payment method label.', 'wp-stripe-payments'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wp_stripe_gateway_description"><?php esc_html_e('Public Gateway Description', 'wp-stripe-payments'); ?></label></th>
                                    <td>
                                        <textarea name="wp_stripe_payments[description]" id="wp_stripe_gateway_description" class="large-text" rows="4"><?php echo esc_textarea($settings['description']); ?></textarea>
                                        <p class="description"><?php esc_html_e('Short trust-focused copy shown below the payment title.', 'wp-stripe-payments'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="wp-sp-card" style="margin-bottom:12px;">
                            <h2><?php esc_html_e('Billing Portal', 'wp-stripe-payments'); ?></h2>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Portal Actions', 'wp-stripe-payments'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="wp_stripe_payments[billing_portal_enabled]" value="yes" <?php checked($settings['billing_portal_enabled'], 'yes'); ?> />
                                            <?php esc_html_e('Allow opening Stripe billing portal from account/admin actions', 'wp-stripe-payments'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Disable this if you do not want users/admins redirected to Stripe portal.', 'wp-stripe-payments'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wp_sp_portal_return_url"><?php esc_html_e('Portal Return URL', 'wp-stripe-payments'); ?></label></th>
                                    <td>
                                        <input name="wp_stripe_payments[billing_portal_return_url]" id="wp_sp_portal_return_url" class="regular-text" type="url" value="<?php echo esc_attr($settings['billing_portal_return_url']); ?>" />
                                        <p class="description"><?php esc_html_e('Optional custom return URL after billing portal. Leave blank for My Account.', 'wp-stripe-payments'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="wp-sp-card">
                            <h2><?php esc_html_e('Debug / Logging', 'wp-stripe-payments'); ?></h2>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Verbose Info Logs', 'wp-stripe-payments'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="wp_stripe_payments[debug_logging_enabled]" value="yes" <?php checked($settings['debug_logging_enabled'], 'yes'); ?> />
                                            <?php esc_html_e('Store informational logs in local plugin logs', 'wp-stripe-payments'); ?>
                                        </label>
                                        <p class="description"><?php printf(esc_html__('Local log history keeps up to %d entries.', 'wp-stripe-payments'), \WPStripePayments\Utils\Logger::maxStoredLogs()); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <?php submit_button(__('Save Settings', 'wp-stripe-payments')); ?>
            </form>
        </div>
        <?php
    }
}
