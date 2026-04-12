<?php

namespace WPStripePayments\Core;

use WPStripePayments\Admin\CustomerSubscriptionsPage;
use WPStripePayments\Admin\DashboardPage;
use WPStripePayments\Admin\LogsPage;
use WPStripePayments\Admin\Menu;
use WPStripePayments\Admin\Settings;
use WPStripePayments\Admin\SettingsPage;
use WPStripePayments\Gateway\StripeGateway;
use WPStripePayments\Stripe\WebhookService;
use WPStripePayments\Subscriptions\CheckoutController;
use WPStripePayments\Subscriptions\CustomerSubscriptionBillingHistoryRepository;
use WPStripePayments\Subscriptions\CustomerSubscriptionRepository;
use WPStripePayments\Subscriptions\PlanService;
use WPStripePayments\Utils\Logger;

class Plugin
{
    private static ?self $instance = null;

    private Loader $loader;

    private Logger $logger;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void
    {
        $customerSubscriptionRepository = new CustomerSubscriptionRepository();
        $customerSubscriptionRepository->createTable();
        $billingHistoryRepository = new CustomerSubscriptionBillingHistoryRepository();
        $billingHistoryRepository->createTable();
        CheckoutController::registerEndpointForRewrite();
        flush_rewrite_rules();
        update_option('wp_sp_subscriptions_endpoint_flushed', '1', false);

        if (get_option(Settings::OPTION_KEY, null) === null) {
            update_option(Settings::OPTION_KEY, Settings::defaults(), false);
        }
    }

    private function __construct()
    {
        $this->loader = new Loader();
        $this->logger = new Logger();
    }

    public function init(): void
    {
        $customerSubscriptionRepository = new CustomerSubscriptionRepository();
        $customerSubscriptionRepository->maybeMigrate();
        $billingHistoryRepository = new CustomerSubscriptionBillingHistoryRepository();
        $billingHistoryRepository->maybeMigrate();

        $settings = new Settings();
        $settingsPage = new SettingsPage();
        $menu = new Menu(
            new DashboardPage(),
            $settingsPage,
            new CustomerSubscriptionsPage(),
            new LogsPage()
        );

        $planService = new PlanService();
        $webhookService = new WebhookService($this->logger);
        $checkoutController = new CheckoutController();

        $this->loader->addAction('admin_menu', $menu, 'register');
        $this->loader->addAction('admin_init', $settingsPage, 'maybeSave');

        $planService->register();
        $checkoutController->register();

        if (! class_exists('WooCommerce')) {
            $this->loader->addAction('admin_notices', $settings, 'missingWooCommerceNotice');
            $this->loader->run();
            return;
        }

        $this->loader->addFilter('woocommerce_payment_gateways', $this, 'registerGateway');
        $this->loader->addAction('rest_api_init', $webhookService, 'registerRoutes');
        $this->loader->addAction('admin_notices', $settings, 'gatewayMisconfigurationNotice');

        $this->loader->run();
    }

    /**
     * @param array<int, string> $gateways
     *
     * @return array<int, string>
     */
    public function registerGateway(array $gateways): array
    {
        $gateways[] = StripeGateway::class;
        return $gateways;
    }
}
