<?php

namespace WPStripePayments\Subscriptions;

use WP_Error;
use WPStripePayments\Stripe\Client;
use WPStripePayments\Utils\Logger;

class CustomerSubscriptionService
{
    private CustomerSubscriptionRepository $repository;

    private PlanRepository $planRepository;

    private Client $stripeClient;

    private Logger $logger;

    public function __construct(
        ?CustomerSubscriptionRepository $repository = null,
        ?PlanRepository $planRepository = null,
        ?Client $stripeClient = null,
        ?Logger $logger = null
    ) {
        $this->repository = $repository ?? new CustomerSubscriptionRepository();
        $this->planRepository = $planRepository ?? new PlanRepository();
        $this->stripeClient = $stripeClient ?? new Client();
        $this->logger = $logger ?? new Logger();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForAdmin(int $limit = 200): array
    {
        return $this->repository->findAll($limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        return array_values(array_filter($this->repository->findAll(500), static function (array $row) use ($userId): bool {
            return (int) ($row['user_id'] ?? 0) === $userId;
        }));
    }

    /**
     * @return bool|WP_Error
     */
    public function syncFromCheckoutSessionId(string $sessionId)
    {
        if ($sessionId === '') {
            return new WP_Error('wp_sp_missing_session_id', __('Missing Stripe Checkout session ID.', 'wp-stripe-payments'));
        }

        $session = $this->stripeClient->get('/checkout/sessions/' . rawurlencode($sessionId), [
            'expand' => ['subscription'],
        ]);

        if (is_wp_error($session)) {
            return $session;
        }

        return $this->syncFromCheckoutSession($session);
    }

    /**
     * @param array<string, mixed> $session
     */
    public function syncFromCheckoutSession(array $session): bool
    {
        $subscriptionId = (string) ($session['subscription'] ?? '');
        $customerId = (string) ($session['customer'] ?? '');
        $customerEmail = (string) ($session['customer_details']['email'] ?? $session['customer_email'] ?? '');
        $metadata = isset($session['metadata']) && is_array($session['metadata']) ? $session['metadata'] : [];
        $planId = isset($metadata['plan_id']) ? (int) $metadata['plan_id'] : 0;
        $userId = isset($metadata['user_id']) ? (int) $metadata['user_id'] : 0;
        $subscription = isset($session['subscription']) && is_array($session['subscription']) ? $session['subscription'] : [];

        if ($subscriptionId === '' && isset($subscription['id']) && is_string($subscription['id'])) {
            $subscriptionId = $subscription['id'];
        }

        if ($subscriptionId === '') {
            return false;
        }

        $priceId = (string) ($subscription['items']['data'][0]['price']['id'] ?? '');
        $status = (string) ($subscription['status'] ?? $session['status'] ?? 'incomplete');
        $currentPeriodEnd = $subscription['current_period_end'] ?? null;

        if ($planId === 0 && $priceId !== '') {
            $planId = $this->resolvePlanIdByPriceId($priceId);
        }

        $this->repository->upsertByStripeSubscriptionId([
            'user_id' => $userId > 0 ? $userId : null,
            'customer_email' => $customerEmail,
            'plan_id' => $planId,
            'stripe_customer_id' => $customerId,
            'stripe_subscription_id' => $subscriptionId,
            'stripe_price_id' => $priceId,
            'status' => $status,
            'next_billing_date' => $currentPeriodEnd,
            'last_checkout_session_id' => (string) ($session['id'] ?? ''),
        ]);

        $this->logger->info('Local customer subscription synced from Checkout session.', [
            'stripe_subscription_id' => $subscriptionId,
            'plan_id' => $planId,
            'status' => $status,
        ]);

        return true;
    }

    /**
     * @param array<string, mixed> $subscription
     */
    public function syncFromSubscriptionObject(array $subscription): bool
    {
        $subscriptionId = (string) ($subscription['id'] ?? '');
        if ($subscriptionId === '') {
            return false;
        }

        $metadata = isset($subscription['metadata']) && is_array($subscription['metadata']) ? $subscription['metadata'] : [];
        $planId = isset($metadata['plan_id']) ? (int) $metadata['plan_id'] : 0;
        $userId = isset($metadata['user_id']) ? (int) $metadata['user_id'] : 0;
        $priceId = (string) ($subscription['items']['data'][0]['price']['id'] ?? '');
        $customerId = (string) ($subscription['customer'] ?? '');
        $status = (string) ($subscription['status'] ?? 'incomplete');
        $currentPeriodEnd = $subscription['current_period_end'] ?? null;

        if ($planId === 0 && $priceId !== '') {
            $planId = $this->resolvePlanIdByPriceId($priceId);
        }

        $existing = $this->repository->findByStripeSubscriptionId($subscriptionId);
        $customerEmail = is_array($existing) ? (string) ($existing['customer_email'] ?? '') : '';

        $this->repository->upsertByStripeSubscriptionId([
            'user_id' => $userId > 0 ? $userId : (is_array($existing) ? (int) ($existing['user_id'] ?? 0) : null),
            'customer_email' => $customerEmail,
            'plan_id' => $planId,
            'stripe_customer_id' => $customerId,
            'stripe_subscription_id' => $subscriptionId,
            'stripe_price_id' => $priceId,
            'status' => $status,
            'next_billing_date' => $currentPeriodEnd,
            'last_checkout_session_id' => is_array($existing) ? (string) ($existing['last_checkout_session_id'] ?? '') : '',
        ]);

        $this->logger->info('Local customer subscription synced from Stripe subscription object.', [
            'stripe_subscription_id' => $subscriptionId,
            'status' => $status,
            'plan_id' => $planId,
        ]);

        return true;
    }

    public function markInvoicePaymentStatus(string $stripeSubscriptionId, string $status): void
    {
        $existing = $this->repository->findByStripeSubscriptionId($stripeSubscriptionId);
        if (! is_array($existing)) {
            return;
        }

        $this->repository->upsertByStripeSubscriptionId([
            'user_id' => (int) ($existing['user_id'] ?? 0),
            'customer_email' => (string) ($existing['customer_email'] ?? ''),
            'plan_id' => (int) ($existing['plan_id'] ?? 0),
            'stripe_customer_id' => (string) ($existing['stripe_customer_id'] ?? ''),
            'stripe_subscription_id' => (string) ($existing['stripe_subscription_id'] ?? ''),
            'stripe_price_id' => (string) ($existing['stripe_price_id'] ?? ''),
            'status' => $status,
            'next_billing_date' => $existing['next_billing_date'] ?? null,
            'last_checkout_session_id' => (string) ($existing['last_checkout_session_id'] ?? ''),
        ]);
    }

    private function resolvePlanIdByPriceId(string $priceId): int
    {
        if ($priceId === '') {
            return 0;
        }

        $plans = get_posts([
            'post_type' => PlanRepository::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => 1,
            'meta_key' => PlanRepository::META_STRIPE_PRICE_ID,
            'meta_value' => $priceId,
            'fields' => 'ids',
        ]);

        if (empty($plans)) {
            return 0;
        }

        return (int) $plans[0];
    }
}
