<?php

namespace WPStripePayments\Subscriptions;

use WPStripePayments\Stripe\SubscriptionCheckoutService;
use WPStripePayments\Utils\Logger;

class CheckoutController
{
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
        add_action('admin_post_wp_sp_start_subscription_checkout', [$this, 'startCheckout']);
        add_action('admin_post_nopriv_wp_sp_start_subscription_checkout', [$this, 'startCheckout']);
        add_shortcode('wp_sp_subscription_plans', [$this, 'renderPlansShortcode']);
        add_action('template_redirect', [$this, 'handleReturn']);
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

        $successUrl = add_query_arg([
            'wp_sp_subscription_result' => 'success',
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ], home_url('/'));

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
            return;
        }

        wc_add_notice(__('Subscription checkout completed. Final status will sync automatically from Stripe webhooks.', 'wp-stripe-payments'), 'success');
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

        foreach ($plans as $plan) {
            $planId = (int) $plan['id'];
            $actionUrl = admin_url('admin-post.php');
            $price = wc_price((float) $plan['price']);

            echo '<div class="wp-sp-plan" style="border:1px solid #ddd;padding:16px;margin-bottom:16px;">';
            echo '<h3>' . esc_html($plan['title']) . '</h3>';

            if ($plan['description'] !== '') {
                echo '<p>' . wp_kses_post($plan['description']) . '</p>';
            }

            echo '<p><strong>' . wp_kses_post($price) . ' / ' . esc_html($plan['billing_interval']) . '</strong></p>';

            echo '<form method="post" action="' . esc_url($actionUrl) . '">';
            echo '<input type="hidden" name="action" value="wp_sp_start_subscription_checkout" />';
            echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) $planId) . '" />';
            wp_nonce_field('wp_sp_start_subscription_checkout_' . $planId);

            if (! is_user_logged_in()) {
                echo '<p><label>' . esc_html__('Email', 'wp-stripe-payments') . ' <input type="email" name="email" required /></label></p>';
            }

            echo '<button type="submit" class="button button-primary">' . esc_html__('Subscribe', 'wp-stripe-payments') . '</button>';
            echo '</form>';
            echo '</div>';
        }

        return (string) ob_get_clean();
    }
}
