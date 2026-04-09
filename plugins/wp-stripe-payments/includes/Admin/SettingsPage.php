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

        if (! current_user_can('manage_options') && ! current_user_can('manage_woocommerce')) {
            return;
        }

        check_admin_referer('wp_stripe_payments_save_settings', 'wp_stripe_payments_settings_nonce');

        $raw = isset($_POST['wp_stripe_payments']) && is_array($_POST['wp_stripe_payments'])
            ? wp_unslash($_POST['wp_stripe_payments'])
            : [];

        $raw['gateway_enabled'] = isset($raw['gateway_enabled']) ? 'yes' : 'no';
        $raw['testmode'] = isset($raw['testmode']) ? 'yes' : 'no';

        Settings::save($raw);

        wp_safe_redirect(add_query_arg([
            'page' => 'wp-stripe-payments-settings',
            'updated' => '1',
        ], admin_url('admin.php')));
        exit;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options') && ! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-stripe-payments'));
        }

        $settings = Settings::all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Stripe Payments Settings', 'wp-stripe-payments'); ?></h1>
            <p><?php esc_html_e('Configure Stripe once here. WooCommerce gateway uses these values automatically.', 'wp-stripe-payments'); ?></p>

            <?php if (isset($_GET['updated']) && $_GET['updated'] === '1') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'wp-stripe-payments'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=wp-stripe-payments-settings')); ?>">
                <?php wp_nonce_field('wp_stripe_payments_save_settings', 'wp_stripe_payments_settings_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Gateway', 'wp-stripe-payments'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wp_stripe_payments[gateway_enabled]" value="yes" <?php checked($settings['gateway_enabled'], 'yes'); ?> />
                                <?php esc_html_e('Enable Stripe payment method in WooCommerce checkout', 'wp-stripe-payments'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Test Mode', 'wp-stripe-payments'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wp_stripe_payments[testmode]" value="yes" <?php checked($settings['testmode'], 'yes'); ?> />
                                <?php esc_html_e('Use Stripe test mode', 'wp-stripe-payments'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wp_stripe_test_publishable_key"><?php esc_html_e('Test Publishable Key', 'wp-stripe-payments'); ?></label></th>
                        <td><input name="wp_stripe_payments[test_publishable_key]" id="wp_stripe_test_publishable_key" class="regular-text" type="text" value="<?php echo esc_attr($settings['test_publishable_key']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wp_stripe_test_secret_key"><?php esc_html_e('Test Secret Key', 'wp-stripe-payments'); ?></label></th>
                        <td><input name="wp_stripe_payments[test_secret_key]" id="wp_stripe_test_secret_key" class="regular-text" type="password" value="<?php echo esc_attr($settings['test_secret_key']); ?>" autocomplete="new-password" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wp_stripe_live_publishable_key"><?php esc_html_e('Live Publishable Key', 'wp-stripe-payments'); ?></label></th>
                        <td><input name="wp_stripe_payments[live_publishable_key]" id="wp_stripe_live_publishable_key" class="regular-text" type="text" value="<?php echo esc_attr($settings['live_publishable_key']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wp_stripe_live_secret_key"><?php esc_html_e('Live Secret Key', 'wp-stripe-payments'); ?></label></th>
                        <td><input name="wp_stripe_payments[live_secret_key]" id="wp_stripe_live_secret_key" class="regular-text" type="password" value="<?php echo esc_attr($settings['live_secret_key']); ?>" autocomplete="new-password" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wp_stripe_webhook_secret"><?php esc_html_e('Webhook Secret', 'wp-stripe-payments'); ?></label></th>
                        <td><input name="wp_stripe_payments[webhook_secret]" id="wp_stripe_webhook_secret" class="regular-text" type="password" value="<?php echo esc_attr($settings['webhook_secret']); ?>" autocomplete="new-password" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wp_stripe_gateway_title"><?php esc_html_e('Public Gateway Title', 'wp-stripe-payments'); ?></label></th>
                        <td><input name="wp_stripe_payments[title]" id="wp_stripe_gateway_title" class="regular-text" type="text" value="<?php echo esc_attr($settings['title']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wp_stripe_gateway_description"><?php esc_html_e('Public Gateway Description', 'wp-stripe-payments'); ?></label></th>
                        <td><textarea name="wp_stripe_payments[description]" id="wp_stripe_gateway_description" class="large-text" rows="4"><?php echo esc_textarea($settings['description']); ?></textarea></td>
                    </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Save Settings', 'wp-stripe-payments')); ?>
            </form>
        </div>
        <?php
    }
}
