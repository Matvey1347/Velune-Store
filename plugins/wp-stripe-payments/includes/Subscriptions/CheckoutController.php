<?php

namespace WPStripePayments\Subscriptions;

use WPStripePayments\Stripe\SubscriptionCheckoutService;
use WPStripePayments\Utils\Logger;

class CheckoutController
{
    public const ACCOUNT_ENDPOINT = 'wp-sp-subscriptions';
    private const ENDPOINT_FLUSHED_OPTION = 'wp_sp_subscriptions_endpoint_flushed';

    private PlanRepository $planRepository;

    private SubscriptionCheckoutService $subscriptionCheckoutService;

    private CustomerSubscriptionService $customerSubscriptionService;

    private Logger $logger;

    public function __construct(
        ?PlanRepository $planRepository = null,
        ?SubscriptionCheckoutService $subscriptionCheckoutService = null,
        ?CustomerSubscriptionService $customerSubscriptionService = null,
        ?Logger $logger = null
    ) {
        $this->planRepository = $planRepository ?? new PlanRepository();
        $this->subscriptionCheckoutService = $subscriptionCheckoutService ?? new SubscriptionCheckoutService($this->planRepository);
        $this->customerSubscriptionService = $customerSubscriptionService ?? new CustomerSubscriptionService();
        $this->logger = $logger ?? new Logger();
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerAccountEndpoint']);
        add_action('init', [$this, 'handleFrontendCheckoutPost'], 1);
        add_action('init', [$this, 'maybeFlushEndpointRewrite'], 20);
        add_action('admin_post_wp_sp_start_subscription_checkout', [$this, 'startCheckout']);
        add_action('admin_post_nopriv_wp_sp_start_subscription_checkout', [$this, 'startCheckout']);
        add_action('admin_post_wp_sp_cancel_subscription_auto_renew', [$this, 'handleCancelAutoRenew']);
        add_action('admin_post_wp_sp_resume_subscription_auto_renew', [$this, 'handleResumeAutoRenew']);
        add_action('admin_post_wp_sp_open_subscription_portal', [$this, 'handleOpenBillingPortal']);
        add_shortcode('wp_sp_subscription_plans', [$this, 'renderPlansShortcode']);
        add_action('template_redirect', [$this, 'handleReturn']);
        add_filter('woocommerce_account_menu_items', [$this, 'addAccountMenuItem'], 35);
        add_action('woocommerce_account_' . self::ACCOUNT_ENDPOINT . '_endpoint', [$this, 'renderAccountEndpoint']);
    }

    public function handleFrontendCheckoutPost(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return;
        }

        $isFrontendCheckoutPost = isset($_POST['wp_sp_front_checkout'])
            && sanitize_text_field((string) wp_unslash($_POST['wp_sp_front_checkout'])) === '1';

        if (! $isFrontendCheckoutPost) {
            return;
        }

        $this->startCheckout();
    }

    public static function registerEndpointForRewrite(): void
    {
        add_rewrite_endpoint(self::ACCOUNT_ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public function registerAccountEndpoint(): void
    {
        self::registerEndpointForRewrite();
    }

    public function maybeFlushEndpointRewrite(): void
    {
        if (get_option(self::ENDPOINT_FLUSHED_OPTION, '0') === '1') {
            return;
        }

        flush_rewrite_rules(false);
        update_option(self::ENDPOINT_FLUSHED_OPTION, '1', false);
    }

    /**
     * @param array<string, string> $items
     *
     * @return array<string, string>
     */
    public function addAccountMenuItem(array $items): array
    {
        $result = [];
        $inserted = false;

        foreach ($items as $key => $label) {
            $result[$key] = $label;

            if ($key === 'orders') {
                $result[self::ACCOUNT_ENDPOINT] = __('Subscriptions', 'wp-stripe-payments');
                $inserted = true;
            }
        }

        if (! $inserted) {
            $result[self::ACCOUNT_ENDPOINT] = __('Subscriptions', 'wp-stripe-payments');
        }

        return $result;
    }

    public function startCheckout(): void
    {
        $planId = isset($_POST['plan_id']) ? (int) wp_unslash($_POST['plan_id']) : 0;
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';

        if (! wp_verify_nonce($nonce, 'wp_sp_start_subscription_checkout_' . $planId)) {
            wp_die(esc_html__('Security check failed.', 'wp-stripe-payments'));
        }

        $email = '';
        $userId = get_current_user_id();

        if ($userId > 0) {
            $user = get_user_by('id', $userId);
            if ($user instanceof \WP_User) {
                $email = (string) $user->user_email;
            }
        }

        if ($email === '' && isset($_POST['email'])) {
            $email = sanitize_email((string) wp_unslash($_POST['email']));
        }

        if ($email === '') {
            wp_safe_redirect(add_query_arg('wp_sp_sub_error', 'missing_email', wp_get_referer() ?: home_url('/')));
            exit;
        }

        $requestOrigin = $this->detectRequestOrigin();
        $subscriptionFallback = $requestOrigin !== ''
            ? trailingslashit($requestOrigin) . 'subscription/'
            : home_url('/subscription/');
        $accountFallback = $requestOrigin !== ''
            ? trailingslashit($requestOrigin) . ltrim(wp_parse_url($this->getAccountSubscriptionsUrl(), PHP_URL_PATH) ?: 'my-account/' . self::ACCOUNT_ENDPOINT . '/', '/')
            : $this->getAccountSubscriptionsUrl();

        $refererUrl = $this->normalizeAbsoluteUrl(wp_get_referer(), $subscriptionFallback);
        $refererUrl = $this->ensureStripeCompatibleUrl($refererUrl, $subscriptionFallback);

        $successBaseUrl = $userId > 0
            ? $this->getAccountSubscriptionsUrl()
            : $refererUrl;
        $successBaseUrl = $this->normalizeAbsoluteUrl($successBaseUrl, $accountFallback);
        $successBaseUrl = $this->ensureStripeCompatibleUrl($successBaseUrl, $accountFallback);

        $successUrl = add_query_arg([
            'wp_sp_subscription_result' => 'success',
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ], $successBaseUrl);
        $successUrl = $this->ensureStripeCompatibleUrl($successUrl, add_query_arg([
            'wp_sp_subscription_result' => 'success',
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ], $subscriptionFallback));

        $cancelBaseUrl = $this->normalizeAbsoluteUrl($refererUrl, $subscriptionFallback);
        $cancelBaseUrl = $this->ensureStripeCompatibleUrl($cancelBaseUrl, $subscriptionFallback);

        $cancelUrl = add_query_arg([
            'wp_sp_subscription_result' => 'cancel',
            'plan_id' => (string) $planId,
        ], $cancelBaseUrl);
        $cancelUrl = $this->ensureStripeCompatibleUrl($cancelUrl, add_query_arg([
            'wp_sp_subscription_result' => 'cancel',
            'plan_id' => (string) $planId,
        ], $subscriptionFallback));

        $plan = $this->planRepository->findById($planId);
        $planExists = $plan !== null;
        $planStatus = $planExists ? (string) ($plan['status'] ?? 'inactive') : 'missing';
        $stripePriceId = $planExists ? (string) ($plan['stripe_price_id'] ?? '') : '';

        $session = $this->subscriptionCheckoutService->createSession($planId, $email, $userId, $successUrl, $cancelUrl);

        if (is_wp_error($session)) {
            $this->logger->error('Subscription checkout start failed.', [
                'plan_id' => $planId,
                'error_code' => $session->get_error_code(),
                'error' => $session->get_error_message(),
                'error_data' => $session->get_error_data(),
                'stripe_response_body' => $this->extractStripeResponseBody($session),
                'stripe_error_response' => $this->extractStripeErrorResponse($session),
                'plan_exists' => $planExists,
                'plan_status' => $planStatus,
                'plan_is_active' => $planStatus === 'active',
                'stripe_price_id' => $stripePriceId,
                'stripe_secret_key_configured' => $this->subscriptionCheckoutServiceSecretConfigured(),
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);

            wp_safe_redirect(add_query_arg('wp_sp_sub_error', 'checkout_failed', wp_get_referer() ?: home_url('/')));
            exit;
        }

        $redirectUrl = (string) ($session['url'] ?? '');

        if ($redirectUrl === '') {
            wp_safe_redirect(add_query_arg('wp_sp_sub_error', 'missing_redirect', wp_get_referer() ?: home_url('/')));
            exit;
        }

        if (! $this->isStripeCheckoutUrl($redirectUrl)) {
            $this->logger->error('Stripe checkout URL is invalid or unexpected.', [
                'plan_id' => $planId,
                'session_id' => (string) ($session['id'] ?? ''),
                'redirect_url' => $redirectUrl,
            ]);
            wp_safe_redirect(add_query_arg('wp_sp_sub_error', 'missing_redirect', wp_get_referer() ?: home_url('/')));
            exit;
        }

        wp_redirect($redirectUrl, 303, 'WP Stripe Payments');
        exit;
    }

    public function handleReturn(): void
    {
        if (! isset($_GET['wp_sp_subscription_result'])) {
            return;
        }

        $result = sanitize_text_field(wp_unslash($_GET['wp_sp_subscription_result']));

        if ($result !== 'success') {
            return;
        }

        $sessionId = isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : '';
        if ($sessionId === '') {
            return;
        }

        $syncResult = $this->customerSubscriptionService->syncFromCheckoutSessionId($sessionId);
        if (is_wp_error($syncResult)) {
            $this->logger->error('Subscription return sync failed.', [
                'session_id' => $sessionId,
                'error' => $syncResult->get_error_message(),
            ]);
            $this->addWooNotice(__('Subscription checkout completed, but syncing status failed. Please refresh in a moment.', 'wp-stripe-payments'), 'error');
            return;
        }

        $this->addWooNotice(__('Subscription checkout completed. Status is now visible in your account subscriptions.', 'wp-stripe-payments'), 'success');
    }

    public function handleCancelAutoRenew(): void
    {
        if (! is_user_logged_in()) {
            wp_safe_redirect(wp_login_url($this->getAccountSubscriptionsUrl()));
            exit;
        }

        $userId = get_current_user_id();
        $subscriptionRowId = isset($_POST['subscription_id']) ? (int) wp_unslash($_POST['subscription_id']) : 0;
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';

        if (! wp_verify_nonce($nonce, 'wp_sp_cancel_auto_renew_' . $subscriptionRowId)) {
            $this->addWooNotice(__('Security check failed. Please try again.', 'wp-stripe-payments'), 'error');
            wp_safe_redirect($this->getAccountSubscriptionsUrl());
            exit;
        }

        $result = $this->customerSubscriptionService->cancelAutoRenewForUser($userId, $subscriptionRowId);
        if (is_wp_error($result)) {
            $this->logger->warning('Customer cancel auto-renew failed.', [
                'user_id' => $userId,
                'subscription_row_id' => $subscriptionRowId,
                'error' => $result->get_error_message(),
            ]);
            $this->addWooNotice($result->get_error_message(), 'error');
        } else {
            $this->addWooNotice(__('Auto-renew has been disabled. Your subscription stays active until period end.', 'wp-stripe-payments'), 'success');
        }

        wp_safe_redirect($this->getAccountSubscriptionsUrl());
        exit;
    }

    public function handleResumeAutoRenew(): void
    {
        if (! is_user_logged_in()) {
            wp_safe_redirect(wp_login_url($this->getAccountSubscriptionsUrl()));
            exit;
        }

        $userId = get_current_user_id();
        $subscriptionRowId = isset($_POST['subscription_id']) ? (int) wp_unslash($_POST['subscription_id']) : 0;
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';

        if (! wp_verify_nonce($nonce, 'wp_sp_resume_auto_renew_' . $subscriptionRowId)) {
            $this->addWooNotice(__('Security check failed. Please try again.', 'wp-stripe-payments'), 'error');
            wp_safe_redirect($this->getAccountSubscriptionsUrl());
            exit;
        }

        $result = $this->customerSubscriptionService->resumeAutoRenewForUser($userId, $subscriptionRowId);
        if (is_wp_error($result)) {
            $this->logger->warning('Customer resume auto-renew failed.', [
                'user_id' => $userId,
                'subscription_row_id' => $subscriptionRowId,
                'error' => $result->get_error_message(),
            ]);
            $this->addWooNotice($result->get_error_message(), 'error');
        } else {
            $this->addWooNotice(__('Auto-renew has been re-enabled for this subscription.', 'wp-stripe-payments'), 'success');
        }

        wp_safe_redirect($this->getAccountSubscriptionsUrl());
        exit;
    }

    public function handleOpenBillingPortal(): void
    {
        if (! is_user_logged_in()) {
            wp_safe_redirect(wp_login_url($this->getAccountSubscriptionsUrl()));
            exit;
        }

        $userId = get_current_user_id();
        $subscriptionRowId = isset($_POST['subscription_id']) ? (int) wp_unslash($_POST['subscription_id']) : 0;
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';

        if (! wp_verify_nonce($nonce, 'wp_sp_open_subscription_portal_' . $subscriptionRowId)) {
            $this->addWooNotice(__('Security check failed. Please try again.', 'wp-stripe-payments'), 'error');
            wp_safe_redirect($this->getAccountSubscriptionsUrl());
            exit;
        }

        $portalSession = $this->customerSubscriptionService->createBillingPortalSessionForUser(
            $userId,
            $subscriptionRowId,
            $this->getAccountSubscriptionsUrl()
        );

        if (is_wp_error($portalSession)) {
            $this->logger->warning('Customer billing portal open failed.', [
                'user_id' => $userId,
                'subscription_row_id' => $subscriptionRowId,
                'error_code' => $portalSession->get_error_code(),
                'error' => $portalSession->get_error_message(),
                'error_data' => $portalSession->get_error_data(),
            ]);
            $this->addWooNotice(__('Unable to open Stripe subscription management right now. Please try again.', 'wp-stripe-payments'), 'error');
            wp_safe_redirect($this->getAccountSubscriptionsUrl());
            exit;
        }

        $portalUrl = (string) ($portalSession['url'] ?? '');
        if (! $this->isStripePortalUrl($portalUrl)) {
            $this->logger->error('Stripe billing portal URL is invalid or unexpected.', [
                'user_id' => $userId,
                'subscription_row_id' => $subscriptionRowId,
                'portal_url' => $portalUrl,
            ]);
            $this->addWooNotice(__('Unable to open Stripe subscription management right now. Please try again.', 'wp-stripe-payments'), 'error');
            wp_safe_redirect($this->getAccountSubscriptionsUrl());
            exit;
        }

        wp_redirect($portalUrl, 303, 'WP Stripe Payments');
        exit;
    }

    public function renderAccountEndpoint(): void
    {
        if (! is_user_logged_in()) {
            echo '<p>' . esc_html__('Please log in to manage your subscriptions.', 'wp-stripe-payments') . '</p>';
            return;
        }

        $subscriptions = $this->customerSubscriptionService->getAccountSubscriptionsForUser(get_current_user_id());

        echo '<div class="wp-sp-account-subscriptions">';
        echo '<h3>' . esc_html__('Your Subscriptions', 'wp-stripe-payments') . '</h3>';

        if (empty($subscriptions)) {
            echo '<p>' . esc_html__('You do not have any subscriptions yet.', 'wp-stripe-payments') . '</p>';
            echo '<p><a class="button" href="' . esc_url(home_url('/subscription/')) . '">' . esc_html__('Browse plans', 'wp-stripe-payments') . '</a></p>';
            echo '</div>';
            return;
        }

        echo '<div class="wp-sp-subscription-cards">';

        foreach ($subscriptions as $subscription) {
            $id = (int) $subscription['id'];
            $statusClass = 'status-' . sanitize_html_class((string) $subscription['status']);
            $stripeSubscriptionId = (string) ($subscription['stripe_subscription_id'] ?? '');

            echo '<article class="wp-sp-subscription-card">';
            echo '<header class="wp-sp-subscription-card__header">';
            echo '<div>';
            echo '<h4 class="wp-sp-subscription-card__title">' . esc_html((string) $subscription['plan_name']) . '</h4>';
            echo '</div>';
            echo '<span class="wp-sp-subscription-card__status ' . esc_attr($statusClass) . '">' . esc_html((string) $subscription['status_label']) . '</span>';
            echo '</header>';

            echo '<p class="wp-sp-subscription-card__description">' . esc_html((string) $subscription['status_description']) . '</p>';

            echo '<dl class="wp-sp-subscription-card__stats">';
            echo '<div><dt>' . esc_html__('Amount', 'wp-stripe-payments') . '</dt><dd>' . wp_kses_post((string) $subscription['amount_formatted']) . '</dd></div>';
            echo '<div><dt>' . esc_html__('Billing', 'wp-stripe-payments') . '</dt><dd>' . esc_html((string) $subscription['billing_interval_label']) . '</dd></div>';
            echo '<div><dt>' . esc_html__('Next billing', 'wp-stripe-payments') . '</dt><dd>' . esc_html((string) $subscription['next_billing_date_label']) . '</dd></div>';
            echo '<div><dt>' . esc_html__('Auto-renew', 'wp-stripe-payments') . '</dt><dd>' . esc_html(! empty($subscription['auto_renew']) ? __('On', 'wp-stripe-payments') : __('Off', 'wp-stripe-payments')) . '</dd></div>';
            echo '</dl>';

            echo '<div class="wp-sp-subscription-card__actions">';
            if ($stripeSubscriptionId !== '') {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-sp-subscription-card__action-form">';
                echo '<input type="hidden" name="action" value="wp_sp_open_subscription_portal" />';
                echo '<input type="hidden" name="subscription_id" value="' . esc_attr((string) $id) . '" />';
                wp_nonce_field('wp_sp_open_subscription_portal_' . $id);
                echo '<button type="submit" class="button button--secondary">' . esc_html__('Edit subscription', 'wp-stripe-payments') . '</button>';
                echo '</form>';
            }

            if (! empty($subscription['can_cancel'])) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-sp-subscription-card__action-form">';
                echo '<input type="hidden" name="action" value="wp_sp_cancel_subscription_auto_renew" />';
                echo '<input type="hidden" name="subscription_id" value="' . esc_attr((string) $id) . '" />';
                wp_nonce_field('wp_sp_cancel_auto_renew_' . $id);
                echo '<button type="submit" class="button">' . esc_html__('Cancel auto-renew', 'wp-stripe-payments') . '</button>';
                echo '</form>';
            }

            if (! empty($subscription['can_resume'])) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wp-sp-subscription-card__action-form">';
                echo '<input type="hidden" name="action" value="wp_sp_resume_subscription_auto_renew" />';
                echo '<input type="hidden" name="subscription_id" value="' . esc_attr((string) $id) . '" />';
                wp_nonce_field('wp_sp_resume_auto_renew_' . $id);
                echo '<button type="submit" class="button">' . esc_html__('Resume auto-renew', 'wp-stripe-payments') . '</button>';
                echo '</form>';
            }

            if (empty($subscription['can_cancel']) && empty($subscription['can_resume'])) {
                echo '<span class="description">' . esc_html__('No auto-renew action available', 'wp-stripe-payments') . '</span>';
            }

            echo '</div>';
            echo '</article>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderPlansShortcode(array $attributes = []): string
    {
        $defaults = [
            'limit' => '0',
            'context' => 'default',
        ];

        $attributes = shortcode_atts($defaults, $attributes, 'wp_sp_subscription_plans');
        $limit = max(0, (int) $attributes['limit']);
        $context = sanitize_key((string) $attributes['context']);

        $plans = $this->planRepository->getActivePlans();
        if ($limit > 0) {
            $plans = array_slice($plans, 0, $limit);
        }

        if (empty($plans)) {
            return '<p>' . esc_html__('No subscription plans are available.', 'wp-stripe-payments') . '</p>';
        }

        ob_start();

        if (isset($_GET['wp_sp_sub_error'])) {
            $errorCode = sanitize_key((string) wp_unslash($_GET['wp_sp_sub_error']));
            $errorMessage = __('Unable to start checkout. Please try again.', 'wp-stripe-payments');

            if ($errorCode === 'missing_email') {
                $errorMessage = __('Please provide an email address to continue.', 'wp-stripe-payments');
            } elseif ($errorCode === 'checkout_failed') {
                $errorMessage = __('Unable to start Stripe Checkout right now. Please try again.', 'wp-stripe-payments');
            } elseif ($errorCode === 'missing_redirect') {
                $errorMessage = __('Stripe Checkout could not be opened. Please try again.', 'wp-stripe-payments');
            }

            echo '<p>' . esc_html($errorMessage) . '</p>';
        }

        $gridClass = $context === 'bundle'
            ? 'plan-grid wp-sp-plan-grid wp-sp-plan-grid--bundle'
            : 'plan-grid wp-sp-plan-grid';

        echo '<div class="' . esc_attr($gridClass) . '">';

        foreach ($plans as $plan) {
            $planId = (int) $plan['id'];
            $actionUrl = home_url('/');
            $price = wc_price((float) $plan['price']);
            $intervalLabel = $this->formatPlanIntervalLabel((string) $plan['billing_interval']);
            $isCheckoutAvailable = $plan['stripe_price_id'] !== '';

            echo '<article class="plan-card wp-sp-plan-card">';
            echo '<span class="eyebrow">' . esc_html__('Subscription', 'wp-stripe-payments') . '</span>';
            echo '<h3>' . esc_html($plan['title']) . '</h3>';

            if ($plan['image'] !== '') {
                echo '<p><img src="' . esc_url($plan['image']) . '" alt="' . esc_attr($plan['title']) . '" loading="lazy" style="max-width:120px;height:auto;border-radius:10px;" /></p>';
            }

            if ($plan['description'] !== '') {
                echo '<p>' . wp_kses_post($plan['description']) . '</p>';
            }

            echo '<div class="price-line"><strong>' . wp_kses_post($price) . '</strong><span> / ' . esc_html($intervalLabel) . '</span></div>';

            echo '<form method="post" action="' . esc_url($actionUrl) . '" class="stack-actions" style="margin-top:16px;">';
            echo '<input type="hidden" name="wp_sp_front_checkout" value="1" />';
            echo '<input type="hidden" name="action" value="wp_sp_start_subscription_checkout" />';
            echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) $planId) . '" />';
            wp_nonce_field('wp_sp_start_subscription_checkout_' . $planId);

            if (! is_user_logged_in()) {
                echo '<p><label>' . esc_html__('Email', 'wp-stripe-payments') . ' <input type="email" name="email" required /></label></p>';
            }

            if (! $isCheckoutAvailable) {
                echo '<p class="helper-text">' . esc_html__('This plan is not ready for checkout yet. Please contact support.', 'wp-stripe-payments') . '</p>';
            }

            echo '<button type="submit" class="button button--primary button--full"' . disabled($isCheckoutAvailable, false, false) . '>' . esc_html__('Subscribe', 'wp-stripe-payments') . '</button>';
            echo '</form>';
            echo '</article>';
        }

        echo '</div>';

        return (string) ob_get_clean();
    }

    private function getAccountSubscriptionsUrl(): string
    {
        if (function_exists('wc_get_account_endpoint_url')) {
            return wc_get_account_endpoint_url(self::ACCOUNT_ENDPOINT);
        }

        return home_url('/my-account/' . self::ACCOUNT_ENDPOINT . '/');
    }

    private function formatPlanIntervalLabel(string $interval): string
    {
        switch ($interval) {
            case 'day':
                return __('daily', 'wp-stripe-payments');

            case 'week':
                return __('weekly', 'wp-stripe-payments');

            case 'month':
                return __('monthly', 'wp-stripe-payments');

            case 'year':
                return __('yearly', 'wp-stripe-payments');

            default:
                return $interval !== '' ? $interval : __('month', 'wp-stripe-payments');
        }
    }

    private function normalizeAbsoluteUrl($candidate, string $fallback): string
    {
        $candidate = is_string($candidate) ? trim($candidate) : '';
        if ($candidate === '') {
            return $fallback;
        }

        $parts = wp_parse_url($candidate);
        $hasScheme = is_array($parts) && ! empty($parts['scheme']) && ! empty($parts['host']);

        if ($hasScheme) {
            return $candidate;
        }

        if (strpos($candidate, '/') !== 0) {
            $candidate = '/' . ltrim($candidate, '/');
        }

        return home_url($candidate);
    }

    private function ensureStripeCompatibleUrl(string $candidate, string $fallback): string
    {
        $candidate = $this->normalizeAbsoluteUrl($candidate, $fallback);
        if ($this->isStripeCompatibleRedirectUrl($candidate)) {
            return $candidate;
        }

        $normalizedFallback = $this->normalizeAbsoluteUrl($fallback, home_url('/subscription/'));
        if ($this->isStripeCompatibleRedirectUrl($normalizedFallback)) {
            return $normalizedFallback;
        }

        return home_url('/subscription/');
    }

    private function isStripeCompatibleRedirectUrl(string $url): bool
    {
        if ($url === '' || ! wp_http_validate_url($url)) {
            return false;
        }

        $parts = wp_parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        $isIpAddress = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $isLocalhost = $host === 'localhost';
        $hasDomainDot = strpos($host, '.') !== false;

        return $isIpAddress || $isLocalhost || $hasDomainDot;
    }

    private function detectRequestOrigin(): string
    {
        $hostHeader = '';

        if (! empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $hostHeader = (string) $_SERVER['HTTP_X_FORWARDED_HOST'];
        } elseif (! empty($_SERVER['HTTP_HOST'])) {
            $hostHeader = (string) $_SERVER['HTTP_HOST'];
        }

        $host = trim(explode(',', $hostHeader)[0] ?? '');
        if ($host === '') {
            return '';
        }

        $host = preg_replace('/[^a-z0-9\\-\\.:\\[\\]]/i', '', $host);
        if (! is_string($host) || $host === '') {
            return '';
        }

        $scheme = 'http';
        if (! empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $proto = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0] ?? '');
            if (in_array(strtolower($proto), ['http', 'https'], true)) {
                $scheme = strtolower($proto);
            }
        } elseif (is_ssl()) {
            $scheme = 'https';
        }

        $origin = $scheme . '://' . $host;

        return wp_http_validate_url($origin) ? $origin : '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractStripeErrorResponse(\WP_Error $error): ?array
    {
        $errorData = $error->get_error_data();
        if (! is_array($errorData)) {
            return null;
        }

        if (isset($errorData['response']) && is_array($errorData['response'])) {
            return $errorData['response'];
        }

        return null;
    }

    private function extractStripeResponseBody(\WP_Error $error): ?string
    {
        $errorData = $error->get_error_data();
        if (! is_array($errorData)) {
            return null;
        }

        if (isset($errorData['response_body']) && is_string($errorData['response_body']) && $errorData['response_body'] !== '') {
            return $errorData['response_body'];
        }

        return null;
    }

    private function subscriptionCheckoutServiceSecretConfigured(): bool
    {
        $client = new \WPStripePayments\Stripe\Client();

        return $client->isSecretKeyConfigured();
    }

    private function addWooNotice(string $message, string $type = 'notice'): void
    {
        if (function_exists('wc_add_notice')) {
            wc_add_notice($message, $type);
            return;
        }

        $this->logger->warning('WooCommerce notice function is unavailable. Notice message was not displayed to user.', [
            'message' => $message,
            'type' => $type,
        ]);
    }

    private function isStripeCheckoutUrl(string $url): bool
    {
        if (! wp_http_validate_url($url)) {
            return false;
        }

        $parts = wp_parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme !== 'https' || $host === '') {
            return false;
        }

        return $host === 'checkout.stripe.com' || $host === 'pay.stripe.com';
    }

    private function isStripePortalUrl(string $url): bool
    {
        if (! wp_http_validate_url($url)) {
            return false;
        }

        $parts = wp_parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme !== 'https' || $host === '') {
            return false;
        }

        return $host === 'billing.stripe.com';
    }
}
