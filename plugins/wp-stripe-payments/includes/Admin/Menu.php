<?php

namespace WPStripePayments\Admin;

use WPStripePayments\Subscriptions\PlanRepository;

class Menu
{
    private DashboardPage $dashboardPage;

    private SetupGuidePage $setupGuidePage;

    private SettingsPage $settingsPage;

    private CustomerSubscriptionsPage $customerSubscriptionsPage;

    private AnalyticsPage $analyticsPage;

    private LogsPage $logsPage;

    public function __construct(
        DashboardPage $dashboardPage,
        SetupGuidePage $setupGuidePage,
        SettingsPage $settingsPage,
        CustomerSubscriptionsPage $customerSubscriptionsPage,
        AnalyticsPage $analyticsPage,
        LogsPage $logsPage
    ) {
        $this->dashboardPage = $dashboardPage;
        $this->setupGuidePage = $setupGuidePage;
        $this->settingsPage = $settingsPage;
        $this->customerSubscriptionsPage = $customerSubscriptionsPage;
        $this->analyticsPage = $analyticsPage;
        $this->logsPage = $logsPage;
    }

    public function register(): void
    {
        add_menu_page(
            AdminContext::BRAND_NAME,
            AdminContext::BRAND_NAME,
            AdminContext::capability(),
            'wp-stripe-payments',
            [$this->dashboardPage, 'render'],
            'dashicons-money-alt',
            56
        );

        add_submenu_page(
            'wp-stripe-payments',
            __('Dashboard', 'wp-stripe-payments'),
            __('Dashboard', 'wp-stripe-payments'),
            AdminContext::capability(),
            'wp-stripe-payments',
            [$this->dashboardPage, 'render']
        );

        add_submenu_page(
            'wp-stripe-payments',
            __('Setup Guide', 'wp-stripe-payments'),
            __('Setup Guide', 'wp-stripe-payments'),
            AdminContext::capability(),
            'wp-stripe-payments-setup-guide',
            [$this->setupGuidePage, 'render']
        );

        add_submenu_page(
            'wp-stripe-payments',
            __('Settings', 'wp-stripe-payments'),
            __('Settings', 'wp-stripe-payments'),
            AdminContext::capability(),
            'wp-stripe-payments-settings',
            [$this->settingsPage, 'render']
        );

        add_submenu_page(
            'wp-stripe-payments',
            __('Customer Subscriptions', 'wp-stripe-payments'),
            __('Customer Subscriptions', 'wp-stripe-payments'),
            AdminContext::capability(),
            'wp-stripe-payments-customer-subscriptions',
            [$this->customerSubscriptionsPage, 'render']
        );

        add_submenu_page(
            'wp-stripe-payments',
            __('Analytics', 'wp-stripe-payments'),
            __('Analytics', 'wp-stripe-payments'),
            AdminContext::capability(),
            'wp-stripe-payments-analytics',
            [$this->analyticsPage, 'render']
        );

        add_submenu_page(
            'wp-stripe-payments',
            __('Plans', 'wp-stripe-payments'),
            __('Plans', 'wp-stripe-payments'),
            AdminContext::capability(),
            'edit.php?post_type=' . PlanRepository::POST_TYPE
        );

        add_submenu_page(
            'wp-stripe-payments',
            __('Logs', 'wp-stripe-payments'),
            __('Logs', 'wp-stripe-payments'),
            AdminContext::capability(),
            'wp-stripe-payments-logs',
            [$this->logsPage, 'render']
        );
    }
}
