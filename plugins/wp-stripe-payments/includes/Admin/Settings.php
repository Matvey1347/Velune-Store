<?php

namespace WPStripePayments\Admin;

use WPStripePayments\Core\Hooks;

class Settings
{
    public const OPTION_KEY = 'wp_stripe_payments_settings';

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'gateway_enabled' => 'no',
            'testmode' => 'yes',
            'test_publishable_key' => '',
            'test_secret_key' => '',
            'live_publishable_key' => '',
            'live_secret_key' => '',
            'webhook_secret' => '',
            'title' => __('Stripe (Custom)', 'wp-stripe-payments'),
            'description' => __('Pay securely via Stripe.', 'wp-stripe-payments'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        self::maybeMigrateLegacySettings();

        $settings = get_option(self::OPTION_KEY, []);
        if (! is_array($settings)) {
            $settings = [];
        }

        return array_merge(self::defaults(), self::sanitize($settings));
    }

    public static function get(string $key, string $default = ''): string
    {
        $settings = self::all();
        return $settings[$key] ?? $default;
    }

    public static function isTestMode(): bool
    {
        return self::get('testmode', 'yes') === 'yes';
    }

    public static function isGatewayEnabled(): bool
    {
        return self::get('gateway_enabled', 'no') === 'yes';
    }

    public static function isConfiguredForCurrentMode(): bool
    {
        $secretKey = self::isTestMode() ? self::get('test_secret_key') : self::get('live_secret_key');
        return $secretKey !== '';
    }

    public static function save(array $raw): void
    {
        $settings = array_merge(self::all(), self::sanitize($raw));
        update_option(self::OPTION_KEY, $settings, false);
    }

    public function missingWooCommerceNotice(): void
    {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html__('WP Stripe Payments Gateway requires WooCommerce to be installed and active.', 'wp-stripe-payments');
        echo '</p></div>';
    }

    public function gatewayMisconfigurationNotice(): void
    {
        if (! current_user_can('manage_woocommerce') && ! current_user_can('manage_options')) {
            return;
        }

        if (! self::isGatewayEnabled()) {
            return;
        }

        if (self::isConfiguredForCurrentMode()) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('WP Stripe Payments Gateway is enabled but no Stripe secret key is configured for the current mode.', 'wp-stripe-payments');
        echo '</p></div>';
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, string>
     */
    public static function sanitize(array $settings): array
    {
        $defaults = self::defaults();
        $sanitized = [];

        foreach ($defaults as $key => $default) {
            $value = $settings[$key] ?? $default;

            if (in_array($key, ['gateway_enabled', 'testmode'], true)) {
                $sanitized[$key] = $value === 'yes' || $value === '1' || $value === 1 ? 'yes' : 'no';
                continue;
            }

            $sanitized[$key] = sanitize_text_field((string) $value);
        }

        return $sanitized;
    }

    private static function maybeMigrateLegacySettings(): void
    {
        if (get_option(self::OPTION_KEY, null) !== null) {
            return;
        }

        $legacy = get_option('woocommerce_' . Hooks::GATEWAY_ID . '_settings', []);
        if (! is_array($legacy) || empty($legacy)) {
            update_option(self::OPTION_KEY, self::defaults(), false);
            return;
        }

        $migrated = [
            'gateway_enabled' => ($legacy['enabled'] ?? 'no') === 'yes' ? 'yes' : 'no',
            'testmode' => ($legacy['testmode'] ?? 'yes') === 'yes' ? 'yes' : 'no',
            'test_publishable_key' => (string) ($legacy['test_publishable_key'] ?? ''),
            'test_secret_key' => (string) ($legacy['test_secret_key'] ?? ''),
            'live_publishable_key' => (string) ($legacy['live_publishable_key'] ?? ''),
            'live_secret_key' => (string) ($legacy['live_secret_key'] ?? ''),
            'webhook_secret' => (string) ($legacy['webhook_secret'] ?? ''),
            'title' => (string) ($legacy['title'] ?? __('Stripe (Custom)', 'wp-stripe-payments')),
            'description' => (string) ($legacy['description'] ?? __('Pay securely via Stripe.', 'wp-stripe-payments')),
        ];

        update_option(self::OPTION_KEY, self::sanitize($migrated), false);
    }
}
