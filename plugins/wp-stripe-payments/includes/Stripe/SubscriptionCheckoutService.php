<?php

namespace WPStripePayments\Stripe;

use WP_Error;
use WPStripePayments\Subscriptions\PlanRepository;
use WPStripePayments\Utils\Logger;

class SubscriptionCheckoutService
{
    private PlanRepository $planRepository;

    private Client $client;

    private Logger $logger;

    public function __construct(?PlanRepository $planRepository = null, ?Client $client = null, ?Logger $logger = null)
    {
        $this->planRepository = $planRepository ?? new PlanRepository();
        $this->client = $client ?? new Client();
        $this->logger = $logger ?? new Logger();
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function createSession(int $planId, string $email, int $userId, string $successUrl, string $cancelUrl)
    {
        $plan = $this->planRepository->findById($planId);

        if ($plan === null) {
            return new WP_Error('wp_sp_plan_not_found', __('Subscription plan not found.', 'wp-stripe-payments'));
        }

        if ($plan['status'] !== 'active') {
            return new WP_Error('wp_sp_plan_inactive', __('This subscription plan is not active.', 'wp-stripe-payments'));
        }

        if ($plan['stripe_price_id'] === '') {
            return new WP_Error('wp_sp_plan_missing_price', __('This plan is not synced with Stripe yet.', 'wp-stripe-payments'));
        }

        $params = [
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => $plan['stripe_price_id'],
                    'quantity' => 1,
                ],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $email,
            'metadata' => [
                'flow' => 'subscription_checkout',
                'plan_id' => (string) $planId,
                'user_id' => $userId > 0 ? (string) $userId : '0',
                'email' => $email,
            ],
            'subscription_data' => [
                'metadata' => [
                    'flow' => 'subscription_checkout',
                    'plan_id' => (string) $planId,
                    'user_id' => $userId > 0 ? (string) $userId : '0',
                ],
            ],
        ];

        if ($userId > 0) {
            $params['client_reference_id'] = (string) $userId;
        }

        $session = $this->client->post('/checkout/sessions', $params);

        if (is_wp_error($session)) {
            $this->logger->error('Unable to create Stripe subscription Checkout session.', [
                'plan_id' => $planId,
                'error' => $session->get_error_message(),
            ]);
            return $session;
        }

        $this->logger->info('Stripe subscription Checkout session created.', [
            'plan_id' => $planId,
            'session_id' => (string) ($session['id'] ?? ''),
        ]);

        return $session;
    }
}
