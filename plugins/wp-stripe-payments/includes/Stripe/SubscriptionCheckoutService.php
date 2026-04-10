<?php

namespace WPStripePayments\Stripe;

use WP_Error;
use WPStripePayments\Subscriptions\PlanRepository;
use WPStripePayments\Utils\Logger;

class SubscriptionCheckoutService
{
    private PlanRepository $planRepository;

    private Client $client;

    private SubscriptionPlanSyncService $planSyncService;

    private Logger $logger;

    public function __construct(
        ?PlanRepository $planRepository = null,
        ?Client $client = null,
        ?SubscriptionPlanSyncService $planSyncService = null,
        ?Logger $logger = null
    )
    {
        $this->planRepository = $planRepository ?? new PlanRepository();
        $this->client = $client ?? new Client();
        $this->planSyncService = $planSyncService ?? new SubscriptionPlanSyncService($this->planRepository, $this->client);
        $this->logger = $logger ?? new Logger();
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function createSession(int $planId, string $email, int $userId, string $successUrl, string $cancelUrl)
    {
        $plan = $this->planRepository->findById($planId);
        $planStatus = is_array($plan) ? (string) ($plan['status'] ?? 'inactive') : 'missing';
        $planStripePriceId = is_array($plan) ? sanitize_text_field((string) ($plan['stripe_price_id'] ?? '')) : '';
        $checkoutContext = [
            'plan_id' => $planId,
            'stripe_price_id' => $planStripePriceId,
            'plan_status' => $planStatus,
            'plan_is_active' => $planStatus === 'active',
            'stripe_secret_key_configured' => $this->client->isSecretKeyConfigured(),
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];

        if ($plan === null) {
            return new WP_Error('wp_sp_plan_not_found', __('Subscription plan not found.', 'wp-stripe-payments'), $checkoutContext);
        }

        if ($plan['status'] !== 'active') {
            return new WP_Error('wp_sp_plan_inactive', __('This subscription plan is not active.', 'wp-stripe-payments'), $checkoutContext);
        }

        $priceIdResult = $this->resolveCheckoutPriceId($planId, $plan);
        if (is_wp_error($priceIdResult)) {
            $priceIdResult = $this->enrichError($priceIdResult, $checkoutContext);
            return $priceIdResult;
        }

        $priceId = (string) $priceIdResult;
        $checkoutContext['stripe_price_id'] = $priceId;

        $params = [
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => $priceId,
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
            $session = $this->enrichError($session, $checkoutContext);
            $this->logger->error('Unable to create Stripe subscription Checkout session.', [
                'error_code' => $session->get_error_code(),
                'error' => $session->get_error_message(),
                'error_data' => $session->get_error_data(),
                'stripe_response_body' => $this->extractStripeResponseBody($session),
                'stripe_error_response' => $this->extractStripeErrorResponse($session),
                'mode' => 'subscription',
                'plan_id' => $planId,
                'stripe_price_id' => $priceId,
                'plan_status' => (string) ($plan['status'] ?? ''),
                'plan_is_active' => ((string) ($plan['status'] ?? '')) === 'active',
                'stripe_secret_key_configured' => $this->client->isSecretKeyConfigured(),
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);
            return $session;
        }

        $this->logger->info('Stripe subscription Checkout session created.', [
            'plan_id' => $planId,
            'session_id' => (string) ($session['id'] ?? ''),
        ]);

        return $session;
    }

    /**
     * @param array<string, string> $plan
     * @return string|WP_Error
     */
    private function resolveCheckoutPriceId(int $planId, array $plan)
    {
        $priceId = sanitize_text_field((string) ($plan['stripe_price_id'] ?? ''));

        if ($priceId === '') {
            return new WP_Error('wp_sp_plan_missing_price', __('This plan is not synced with Stripe yet.', 'wp-stripe-payments'));
        }

        if (strpos($priceId, 'price_') !== 0) {
            $this->logger->warning('Subscription plan has invalid Stripe price ID format. Attempting resync.', [
                'plan_id' => $planId,
                'stripe_price_id' => $priceId,
            ]);

            return $this->resyncPlanPriceId($planId, $plan);
        }

        $price = $this->client->get('/prices/' . rawurlencode($priceId));
        if (is_wp_error($price)) {
            $this->logger->warning('Stripe price lookup failed for subscription checkout. Attempting resync.', [
                'plan_id' => $planId,
                'stripe_price_id' => $priceId,
                'error_code' => $price->get_error_code(),
                'error' => $price->get_error_message(),
                'error_data' => $price->get_error_data(),
            ]);

            return $this->resyncPlanPriceId($planId, $plan);
        }

        $isRecurring = isset($price['recurring']) && is_array($price['recurring']);
        $isActive = isset($price['active']) ? (bool) $price['active'] : true;

        if (! $isRecurring || ! $isActive) {
            $this->logger->warning('Stripe price on subscription plan is not an active recurring price. Attempting resync.', [
                'plan_id' => $planId,
                'stripe_price_id' => $priceId,
                'stripe_price_type' => (string) ($price['type'] ?? ''),
                'stripe_price_active' => (bool) ($price['active'] ?? false),
            ]);

            return $this->resyncPlanPriceId($planId, $plan);
        }

        return $priceId;
    }

    /**
     * @param array<string, string> $plan
     * @return string|WP_Error
     */
    private function resyncPlanPriceId(int $planId, array $plan)
    {
        $syncResult = $this->planSyncService->syncPlan($planId);
        if (is_wp_error($syncResult)) {
            return $syncResult;
        }

        $freshPriceId = sanitize_text_field((string) ($syncResult['stripe_price_id'] ?? ''));
        if ($freshPriceId === '') {
            return new WP_Error('wp_sp_plan_missing_price', __('This plan is not synced with Stripe yet.', 'wp-stripe-payments'));
        }

        $this->logger->info('Subscription checkout plan price was refreshed via Stripe sync.', [
            'plan_id' => $planId,
            'previous_stripe_price_id' => (string) ($plan['stripe_price_id'] ?? ''),
            'stripe_price_id' => $freshPriceId,
        ]);

        return $freshPriceId;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function enrichError(WP_Error $error, array $context): WP_Error
    {
        $existing = $error->get_error_data();
        $merged = is_array($existing)
            ? array_merge($existing, $context)
            : array_merge(['original_error_data' => $existing], $context);
        $error->add_data($merged);

        return $error;
    }

    /**
     * @return string|null
     */
    private function extractStripeResponseBody(WP_Error $error): ?string
    {
        $data = $error->get_error_data();
        if (! is_array($data)) {
            return null;
        }

        if (isset($data['response_body']) && is_string($data['response_body']) && $data['response_body'] !== '') {
            return $data['response_body'];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractStripeErrorResponse(WP_Error $error): ?array
    {
        $data = $error->get_error_data();
        if (! is_array($data)) {
            return null;
        }

        if (isset($data['response']) && is_array($data['response'])) {
            return $data['response'];
        }

        return null;
    }
}
