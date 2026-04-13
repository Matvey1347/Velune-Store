<?php

namespace WPStripePayments\Admin;

class AdminContext
{
    public const BRAND_NAME = 'CommerceKit Stripe Billing';
    public const MENU_SLUG = 'wp-stripe-payments';

    public static function canManage(): bool
    {
        return current_user_can('manage_woocommerce') || current_user_can('manage_options');
    }

    public static function capability(): string
    {
        return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
    }

    public static function denyAccess(): void
    {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-stripe-payments'));
    }

    /**
     * @return array<string, string>
     */
    public static function statusMeta(bool $isOk): array
    {
        if ($isOk) {
            return [
                'label' => __('Healthy', 'wp-stripe-payments'),
                'class' => 'is-good',
            ];
        }

        return [
            'label' => __('Action required', 'wp-stripe-payments'),
            'class' => 'is-warning',
        ];
    }
}
