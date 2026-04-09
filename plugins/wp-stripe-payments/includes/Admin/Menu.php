<?php

namespace WPStripePayments\Admin;

class Menu
{
    private DashboardPage $dashboardPage;

    private SettingsPage $settingsPage;

    private CustomersPage $customersPage;

    private LogsPage $logsPage;

    public function __construct(
        DashboardPage $dashboardPage,
        SettingsPage $settingsPage,
        CustomersPage $customersPage,
        LogsPage $logsPage
    ) {
        $this->dashboardPage = $dashboardPage;
        $this->settingsPage = $settingsPage;
        $this->customersPage = $customersPage;
        $this->logsPage = $logsPage;
    }

    public function register(): void
    {
        add_menu_page(
            __('Stripe Payments', 'wp-stripe-payments'),
            __('Stripe Payments', 'wp-stripe-payments'),
            $this->capability(),
            'wp-stripe-payments',
            [$this->dashboardPage, 'render'],
            'dashicons-money-alt',
            56
        );

        add_submenu_page(
            'wp-stripe-payments',
            __('Dashboard', 'wp-stripe-payments'),
            __('Dashboard', 'wp-stripe-payments'),
            $this->capability(),
            'wp-stripe-payments',
            [$this->dashboardPage, 'render']
        );

        add_submenu_page(
            'wp-stripe-payments',
            __('Settings', 'wp-stripe-payments'),
            __('Settings', 'wp-stripe-payments'),
            $this->capability(),
            'wp-stripe-payments-settings',
            [$this->settingsPage, 'render']
        );

        add_submenu_page(
            'wp-stripe-payments',
            __('Customers', 'wp-stripe-payments'),
            __('Customers', 'wp-stripe-payments'),
            $this->capability(),
            'wp-stripe-payments-customers',
            [$this->customersPage, 'render']
        );

        add_submenu_page(
            'wp-stripe-payments',
            __('Logs', 'wp-stripe-payments'),
            __('Logs', 'wp-stripe-payments'),
            $this->capability(),
            'wp-stripe-payments-logs',
            [$this->logsPage, 'render']
        );
    }

    private function capability(): string
    {
        return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
    }
}
