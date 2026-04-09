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
        add_action('init', [$this, 'maybeFlushEndpointRewrite'], 20);
        add_action('admin_post_wp_sp_start_subscription_checkout', [$this, 'startCheckout']);
        add_action('admin_post_nopriv_wp_sp_start_subscription_checkout', [$this, 'startCheckout']);
        add_action('admin_post_wp_sp_cancel_subscription_auto_renew', [$this, 'handleCancelAutoRenew']);
        add_action('admin_post_wp_sp_resume_subscription_auto_renew', [$this, 'handleResumeAutoRenew']);
        add_shortcode('wp_sp_subscription_plans', [$this, 'renderPlansShortcode']);
        add_action('template_redirect', [$this, 'handleReturn']);
        add_filter('woocommerce_account_menu_items', [$this, 'addAccountMenuItem'], 35);
        add_action('woocommerce_account_' . self::ACCOUNT_ENDPOINT . '_endpoint', [$this, 'renderAccountEndpoint']);
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

        $successBaseUrl = $userId > 0
            ? $this->getAccountSubscriptionsUrl()
            : (wp_get_referer() ?: home_url('/subscription/'));

        $successUrl = add_query_arg([
            'wp_sp_subscription_result' => 'success',
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ], $successBaseUrl);

        $cancelUrl = add_query_arg([
            'wp_sp_subscription_result' => 'cancel',
            'plan_id' => (string) $planId,
        ], wp_get_referer() ?: home_url('/'));

        $session = $this->subscriptionCheckoutService->createSession($planId, $email, $userId, $successUrl, $cancelUrl);

        if (is_wp_error($session)) {
            $this->logger->error('Subscription checkout start failed.', [
                'plan_id' => $planId,
                'error' => $session->get_error_message(),
            ]);

            wp_safe_redirect(add_query_arg('wp_sp_sub_error', 'checkout_failed', wp_get_referer() ?: home_url('/')));
            exit;
        }

        $redirectUrl = (string) ($session['url'] ?? '');

        if ($redirectUrl === '') {
            wp_safe_redirect(add_query_arg('wp_sp_sub_error', 'missing_redirect', wp_get_referer() ?: home_url('/')));
            exit;
        }

        wp_safe_redirect($redirectUrl);
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
            wc_add_notice(__('Subscription checkout completed, but syncing status failed. Please refresh in a moment.', 'wp-stripe-payments'), 'error');
            return;
        }

        wc_add_notice(__('Subscription checkout completed. Status is now visible in your account subscriptions.', 'wp-stripe-payments'), 'success');
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
            wc_add_notice(__('Security check failed. Please try again.', 'wp-stripe-payments'), 'error');
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
            wc_add_notice($result->get_error_message(), 'error');
        } else {
            wc_add_notice(__('Auto-renew has been disabled. Your subscription stays active until period end.', 'wp-stripe-payments'), 'success');
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
            wc_add_notice(__('Security check failed. Please try again.', 'wp-stripe-payments'), 'error');
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
            wc_add_notice($result->get_error_message(), 'error');
        } else {
            wc_add_notice(__('Auto-renew has been re-enabled for this subscription.', 'wp-stripe-payments'), 'success');
        }

        wp_safe_redirect($this->getAccountSubscriptionsUrl());
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

        echo '<table class="shop_table shop_table_responsive my_account_orders">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Plan', 'wp-stripe-payments') . '</th>';
        echo '<th>' . esc_html__('Amount', 'wp-stripe-payments') . '</th>';
        echo '<th>' . esc_html__('Billing', 'wp-stripe-payments') . '</th>';
        echo '<th>' . esc_html__('Status', 'wp-stripe-payments') . '</th>';
        echo '<th>' . esc_html__('Next billing', 'wp-stripe-payments') . '</th>';
        echo '<th>' . esc_html__('Auto-renew', 'wp-stripe-payments') . '</th>';
        echo '<th>' . esc_html__('Actions', 'wp-stripe-payments') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($subscriptions as $subscription) {
            $id = (int) $subscription['id'];
            $statusClass = 'status-' . sanitize_html_class((string) $subscription['status']);

            echo '<tr>';
            echo '<td data-title="' . esc_attr__('Plan', 'wp-stripe-payments') . '">';
            echo '<strong>' . esc_html((string) $subscription['plan_name']) . '</strong>';
            if (! empty($subscription['stripe_subscription_id'])) {
                echo '<br/><small><code>' . esc_html((string) $subscription['stripe_subscription_id']) . '</code></small>';
            }
            echo '</td>';

            echo '<td data-title="' . esc_attr__('Amount', 'wp-stripe-payments') . '">' . wp_kses_post((string) $subscription['amount_formatted']) . '</td>';
            echo '<td data-title="' . esc_attr__('Billing', 'wp-stripe-payments') . '">' . esc_html((string) $subscription['billing_interval_label']) . '</td>';
            echo '<td data-title="' . esc_attr__('Status', 'wp-stripe-payments') . '"><span class="' . esc_attr($statusClass) . '"><strong>' . esc_html((string) $subscription['status_label']) . '</strong></span><br/><small>' . esc_html((string) $subscription['status_description']) . '</small></td>';
            echo '<td data-title="' . esc_attr__('Next billing', 'wp-stripe-payments') . '">' . esc_html((string) $subscription['next_billing_date_label']) . '</td>';
            echo '<td data-title="' . esc_attr__('Auto-renew', 'wp-stripe-payments') . '">' . esc_html(! empty($subscription['auto_renew']) ? __('On', 'wp-stripe-payments') : __('Off', 'wp-stripe-payments')) . '</td>';
            echo '<td data-title="' . esc_attr__('Actions', 'wp-stripe-payments') . '">';

            if (! empty($subscription['can_cancel'])) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:8px;">';
                echo '<input type="hidden" name="action" value="wp_sp_cancel_subscription_auto_renew" />';
                echo '<input type="hidden" name="subscription_id" value="' . esc_attr((string) $id) . '" />';
                wp_nonce_field('wp_sp_cancel_auto_renew_' . $id);
                echo '<button type="submit" class="button">' . esc_html__('Cancel auto-renew', 'wp-stripe-payments') . '</button>';
                echo '</form>';
            }

            if (! empty($subscription['can_resume'])) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;">';
                echo '<input type="hidden" name="action" value="wp_sp_resume_subscription_auto_renew" />';
                echo '<input type="hidden" name="subscription_id" value="' . esc_attr((string) $id) . '" />';
                wp_nonce_field('wp_sp_resume_auto_renew_' . $id);
                echo '<button type="submit" class="button">' . esc_html__('Resume auto-renew', 'wp-stripe-payments') . '</button>';
                echo '</form>';
            }

            if (empty($subscription['can_cancel']) && empty($subscription['can_resume'])) {
                echo '<span class="description">' . esc_html__('No actions available', 'wp-stripe-payments') . '</span>';
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderPlansShortcode(array $attributes = []): string
    {
        $plans = $this->planRepository->getActivePlans();

        if (empty($plans)) {
            return '<p>' . esc_html__('No subscription plans are available.', 'wp-stripe-payments') . '</p>';
        }

        ob_start();

        if (isset($_GET['wp_sp_sub_error'])) {
            echo '<p>' . esc_html__('Unable to start checkout. Please try again.', 'wp-stripe-payments') . '</p>';
        }

        echo '<div class="plan-grid wp-sp-plan-grid">';

        foreach ($plans as $plan) {
            $planId = (int) $plan['id'];
            $actionUrl = admin_url('admin-post.php');
            $price = wc_price((float) $plan['price']);

            echo '<article class="plan-card wp-sp-plan-card">';
            echo '<span class="eyebrow">' . esc_html__('Subscription', 'wp-stripe-payments') . '</span>';
            echo '<h3>' . esc_html($plan['title']) . '</h3>';

            if ($plan['image'] !== '') {
                echo '<p><img src="' . esc_url($plan['image']) . '" alt="' . esc_attr($plan['title']) . '" loading="lazy" style="max-width:120px;height:auto;border-radius:10px;" /></p>';
            }

            if ($plan['description'] !== '') {
                echo '<p>' . wp_kses_post($plan['description']) . '</p>';
            }

            echo '<div class="price-line"><strong>' . wp_kses_post($price) . '</strong><span> / ' . esc_html($plan['billing_interval']) . '</span></div>';

            echo '<form method="post" action="' . esc_url($actionUrl) . '" class="stack-actions" style="margin-top:16px;">';
            echo '<input type="hidden" name="action" value="wp_sp_start_subscription_checkout" />';
            echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) $planId) . '" />';
            wp_nonce_field('wp_sp_start_subscription_checkout_' . $planId);

            if (! is_user_logged_in()) {
                echo '<p><label>' . esc_html__('Email', 'wp-stripe-payments') . ' <input type="email" name="email" required /></label></p>';
            }

            echo '<button type="submit" class="button button--primary button--full">' . esc_html__('Subscribe', 'wp-stripe-payments') . '</button>';
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
}
