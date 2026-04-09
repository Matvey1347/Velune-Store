<?php

namespace WPStripePayments\Subscriptions;

use WP_Error;
use WPStripePayments\Stripe\Client;
use WPStripePayments\Stripe\CustomerSubscriptionStripeService;
use WPStripePayments\Utils\Logger;

class CustomerSubscriptionService
{
    private const ALLOWED_STATUSES = [
        'active',
        'trialing',
        'incomplete',
        'incomplete_expired',
        'past_due',
        'canceled',
        'unpaid',
    ];

    private CustomerSubscriptionRepository $repository;

    private PlanRepository $planRepository;

    private Client $stripeClient;

    private CustomerSubscriptionStripeService $customerSubscriptionStripeService;

    private Logger $logger;

    public function __construct(
        ?CustomerSubscriptionRepository $repository = null,
        ?PlanRepository $planRepository = null,
        ?Client $stripeClient = null,
        ?CustomerSubscriptionStripeService $customerSubscriptionStripeService = null,
        ?Logger $logger = null
    ) {
        $this->repository = $repository ?? new CustomerSubscriptionRepository();
        $this->planRepository = $planRepository ?? new PlanRepository();
        $this->stripeClient = $stripeClient ?? new Client();
        $this->customerSubscriptionStripeService = $customerSubscriptionStripeService ?? new CustomerSubscriptionStripeService(
            $this->stripeClient
        );
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
        if ($userId <= 0) {
            return [];
        }

        $rowsByUser = $this->repository->findByUserId($userId, 500);
        $email = $this->resolveUserEmail($userId);

        if ($email === '') {
            return $rowsByUser;
        }

        $this->repository->attachUserIdByEmail($email, $userId);
        $rowsByEmail = $this->repository->findByEmail($email, 500);

        return $this->mergeRowsUniqueById($rowsByUser, $rowsByEmail);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAccountSubscriptionsForUser(int $userId): array
    {
        $rows = $this->listForUser($userId);
        $result = [];

        foreach ($rows as $row) {
            $status = $this->normalizeStatus((string) ($row['status'] ?? 'incomplete'));
            $planId = (int) ($row['plan_id'] ?? 0);
            $plan = $planId > 0 ? $this->planRepository->findById($planId) : null;

            $amount = isset($row['plan_snapshot_price']) && $row['plan_snapshot_price'] !== null
                ? (float) $row['plan_snapshot_price']
                : (float) ($plan['price'] ?? 0);

            $interval = (string) ($row['billing_interval'] ?? '');
            if ($interval === '') {
                $interval = (string) ($plan['billing_interval'] ?? '');
            }

            $planTitle = (string) ($row['plan_snapshot_title'] ?? '');
            if ($planTitle === '' && $plan !== null) {
                $planTitle = (string) ($plan['title'] ?? '');
            }

            $cancelAtPeriodEnd = ! empty($row['cancel_at_period_end']);
            $nextBillingDate = (string) ($row['current_period_end'] ?? $row['next_billing_date'] ?? '');

            $result[] = [
                'id' => (int) ($row['id'] ?? 0),
                'plan_id' => $planId,
                'plan_name' => $planTitle !== '' ? $planTitle : __('Subscription plan', 'wp-stripe-payments'),
                'amount' => $amount,
                'amount_formatted' => wc_price($amount),
                'billing_interval' => $interval,
                'billing_interval_label' => $this->formatInterval($interval),
                'status' => $status,
                'status_label' => $this->formatStatusLabel($status),
                'status_description' => $this->formatStatusDescription($status, $cancelAtPeriodEnd),
                'next_billing_date' => $nextBillingDate,
                'next_billing_date_label' => $this->formatDateLabel($nextBillingDate),
                'auto_renew' => ! $cancelAtPeriodEnd && $status !== 'canceled',
                'cancel_at_period_end' => $cancelAtPeriodEnd,
                'can_cancel' => $this->canCancelAutoRenew($status, $cancelAtPeriodEnd),
                'can_resume' => $this->canResumeAutoRenew($status, $cancelAtPeriodEnd),
                'stripe_subscription_id' => (string) ($row['stripe_subscription_id'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @return bool|WP_Error
     */
    public function cancelAutoRenewForUser(int $userId, int $subscriptionRowId)
    {
        $owned = $this->getOwnedSubscription($userId, $subscriptionRowId);
        if (is_wp_error($owned)) {
            return $owned;
        }

        $status = $this->normalizeStatus((string) ($owned['status'] ?? 'incomplete'));
        $cancelAtPeriodEnd = ! empty($owned['cancel_at_period_end']);

        if (! $this->canCancelAutoRenew($status, $cancelAtPeriodEnd)) {
            return new WP_Error(
                'wp_sp_cannot_cancel_subscription',
                __('This subscription cannot be set to cancel at period end.', 'wp-stripe-payments')
            );
        }

        $stripeSubscriptionId = (string) ($owned['stripe_subscription_id'] ?? '');
        $response = $this->customerSubscriptionStripeService->cancelAtPeriodEnd($stripeSubscriptionId);
        if (is_wp_error($response)) {
            return $response;
        }

        $synced = $this->syncFromSubscriptionObject($response, [
            'customer_email' => (string) ($owned['customer_email'] ?? ''),
            'user_id' => $userId,
        ]);

        if (! $synced) {
            return new WP_Error(
                'wp_sp_subscription_sync_failed',
                __('Subscription was updated on Stripe, but local sync failed.', 'wp-stripe-payments')
            );
        }

        $this->logger->info('Customer requested cancel auto-renew.', [
            'user_id' => $userId,
            'subscription_row_id' => $subscriptionRowId,
            'stripe_subscription_id' => $stripeSubscriptionId,
        ]);

        return true;
    }

    /**
     * @return bool|WP_Error
     */
    public function resumeAutoRenewForUser(int $userId, int $subscriptionRowId)
    {
        $owned = $this->getOwnedSubscription($userId, $subscriptionRowId);
        if (is_wp_error($owned)) {
            return $owned;
        }

        $status = $this->normalizeStatus((string) ($owned['status'] ?? 'incomplete'));
        $cancelAtPeriodEnd = ! empty($owned['cancel_at_period_end']);

        if (! $this->canResumeAutoRenew($status, $cancelAtPeriodEnd)) {
            return new WP_Error(
                'wp_sp_cannot_resume_subscription',
                __('This subscription cannot be resumed for auto-renew.', 'wp-stripe-payments')
            );
        }

        $stripeSubscriptionId = (string) ($owned['stripe_subscription_id'] ?? '');
        $response = $this->customerSubscriptionStripeService->resumeAutoRenew($stripeSubscriptionId);
        if (is_wp_error($response)) {
            return $response;
        }

        $synced = $this->syncFromSubscriptionObject($response, [
            'customer_email' => (string) ($owned['customer_email'] ?? ''),
            'user_id' => $userId,
        ]);

        if (! $synced) {
            return new WP_Error(
                'wp_sp_subscription_sync_failed',
                __('Subscription was updated on Stripe, but local sync failed.', 'wp-stripe-payments')
            );
        }

        $this->logger->info('Customer requested resume auto-renew.', [
            'user_id' => $userId,
            'subscription_row_id' => $subscriptionRowId,
            'stripe_subscription_id' => $stripeSubscriptionId,
        ]);

        return true;
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
            'expand' => ['subscription', 'subscription.items.data.price'],
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
        $metadata = isset($session['metadata']) && is_array($session['metadata']) ? $session['metadata'] : [];
        $customerEmail = (string) ($session['customer_details']['email'] ?? $session['customer_email'] ?? $metadata['email'] ?? '');
        $sessionId = (string) ($session['id'] ?? '');
        $context = [
            'customer_email' => $customerEmail,
            'last_checkout_session_id' => $sessionId,
            'user_id' => isset($metadata['user_id']) ? (int) $metadata['user_id'] : 0,
            'plan_id' => isset($metadata['plan_id']) ? (int) $metadata['plan_id'] : 0,
        ];

        $subscription = isset($session['subscription']) && is_array($session['subscription']) ? $session['subscription'] : null;
        if ($subscription === null) {
            $subscriptionId = (string) ($session['subscription'] ?? '');
            if ($subscriptionId !== '') {
                $subscriptionResponse = $this->customerSubscriptionStripeService->retrieveSubscription($subscriptionId);
                if (is_wp_error($subscriptionResponse)) {
                    $this->logger->error('Unable to retrieve subscription for checkout sync.', [
                        'session_id' => $sessionId,
                        'stripe_subscription_id' => $subscriptionId,
                        'error' => $subscriptionResponse->get_error_message(),
                    ]);
                    return false;
                }

                $subscription = $subscriptionResponse;
            }
        }

        if (! is_array($subscription)) {
            return false;
        }

        return $this->syncFromSubscriptionObject($subscription, $context);
    }

    /**
     * @param array<string, mixed> $subscription
     * @param array<string, mixed> $context
     */
    public function syncFromSubscriptionObject(array $subscription, array $context = []): bool
    {
        $payload = $this->buildNormalizedPayloadFromStripeSubscription($subscription, $context);
        if ($payload === null) {
            return false;
        }

        $this->repository->upsertByStripeSubscriptionId($payload);

        if (! empty($payload['user_id']) && ! empty($payload['customer_email'])) {
            $this->repository->attachUserIdByEmail((string) $payload['customer_email'], (int) $payload['user_id']);
        }

        $this->logger->info('Local customer subscription synced from Stripe subscription object.', [
            'stripe_subscription_id' => (string) $payload['stripe_subscription_id'],
            'status' => (string) $payload['status'],
            'plan_id' => (int) ($payload['plan_id'] ?? 0),
            'cancel_at_period_end' => ! empty($payload['cancel_at_period_end']),
        ]);

        return true;
    }

    /**
     * @param array<string, mixed>|null $invoiceObject
     */
    public function markInvoicePaymentStatus(string $stripeSubscriptionId, string $status, ?array $invoiceObject = null): void
    {
        if ($stripeSubscriptionId === '') {
            return;
        }

        $normalizedStatus = $this->normalizeStatus($status);
        $existing = $this->repository->findOneByStripeSubscriptionId($stripeSubscriptionId);
        if (! is_array($existing)) {
            return;
        }

        $this->repository->updateStatus($stripeSubscriptionId, $normalizedStatus);

        if (is_array($invoiceObject)) {
            $periodEnd = $invoiceObject['lines']['data'][0]['period']['end'] ?? null;
            if ($periodEnd !== null) {
                $this->repository->updateCancellationFlags(
                    $stripeSubscriptionId,
                    ! empty($existing['cancel_at_period_end']),
                    $existing['canceled_at'] ?? null,
                    $periodEnd,
                    $invoiceObject['lines']['data'][0]['period']['start'] ?? ($existing['current_period_start'] ?? null)
                );
            }
        }
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function getOwnedSubscription(int $userId, int $subscriptionRowId)
    {
        if ($userId <= 0 || $subscriptionRowId <= 0) {
            return new WP_Error('wp_sp_invalid_owner', __('Invalid subscription ownership context.', 'wp-stripe-payments'));
        }

        $row = $this->repository->findById($subscriptionRowId);
        if (! is_array($row)) {
            return new WP_Error('wp_sp_subscription_not_found', __('Subscription record not found.', 'wp-stripe-payments'));
        }

        $rowUserId = (int) ($row['user_id'] ?? 0);
        if ($rowUserId === $userId) {
            return $row;
        }

        $currentUserEmail = $this->resolveUserEmail($userId);
        if ($rowUserId <= 0 && $currentUserEmail !== '' && strtolower((string) ($row['customer_email'] ?? '')) === strtolower($currentUserEmail)) {
            $this->repository->attachUserIdByEmail($currentUserEmail, $userId);
            $refreshed = $this->repository->findById($subscriptionRowId);
            if (is_array($refreshed)) {
                return $refreshed;
            }
        }

        return new WP_Error(
            'wp_sp_subscription_forbidden',
            __('You are not allowed to manage this subscription.', 'wp-stripe-payments')
        );
    }

    /**
     * @param array<string, mixed> $subscription
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>|null
     */
    private function buildNormalizedPayloadFromStripeSubscription(array $subscription, array $context): ?array
    {
        $subscriptionId = (string) ($subscription['id'] ?? '');
        if ($subscriptionId === '') {
            return null;
        }

        $metadata = isset($subscription['metadata']) && is_array($subscription['metadata']) ? $subscription['metadata'] : [];
        $existing = $this->repository->findOneByStripeSubscriptionId($subscriptionId);

        $price = $subscription['items']['data'][0]['price'] ?? [];
        $priceId = (string) ($price['id'] ?? $subscription['plan']['id'] ?? '');
        $billingInterval = (string) ($price['recurring']['interval'] ?? '');
        $priceAmount = isset($price['unit_amount']) ? ((float) $price['unit_amount'] / 100) : null;

        $planId = isset($metadata['plan_id']) ? (int) $metadata['plan_id'] : 0;
        if ($planId <= 0) {
            $planId = isset($context['plan_id']) ? (int) $context['plan_id'] : 0;
        }
        if ($planId <= 0 && $priceId !== '') {
            $planId = $this->resolvePlanIdByPriceId($priceId);
        }

        $userId = isset($metadata['user_id']) ? (int) $metadata['user_id'] : 0;
        if ($userId <= 0) {
            $userId = isset($context['user_id']) ? (int) $context['user_id'] : 0;
        }
        if ($userId <= 0 && is_array($existing)) {
            $userId = (int) ($existing['user_id'] ?? 0);
        }

        $customerEmail = isset($context['customer_email']) ? sanitize_email((string) $context['customer_email']) : '';
        if ($customerEmail === '' && is_array($existing)) {
            $customerEmail = sanitize_email((string) ($existing['customer_email'] ?? ''));
        }

        $planSnapshotTitle = '';
        $planSnapshotPrice = $priceAmount;

        if ($planId > 0) {
            $plan = $this->planRepository->findById($planId);
            if (is_array($plan)) {
                $planSnapshotTitle = (string) ($plan['title'] ?? '');
                if ($planSnapshotPrice === null) {
                    $planSnapshotPrice = (float) ($plan['price'] ?? 0);
                }
                if ($billingInterval === '') {
                    $billingInterval = (string) ($plan['billing_interval'] ?? '');
                }
            }
        }

        if ($planSnapshotTitle === '') {
            $planSnapshotTitle = (string) ($price['nickname'] ?? '');
        }

        if ($billingInterval === '' && is_array($existing)) {
            $billingInterval = (string) ($existing['billing_interval'] ?? '');
        }

        $currentPeriodEnd = $subscription['current_period_end'] ?? null;
        $currentPeriodStart = $subscription['current_period_start'] ?? null;
        $cancelAtPeriodEnd = ! empty($subscription['cancel_at_period_end']);

        return [
            'user_id' => $userId > 0 ? $userId : null,
            'customer_email' => $customerEmail,
            'plan_id' => $planId,
            'stripe_customer_id' => (string) ($subscription['customer'] ?? (is_array($existing) ? ($existing['stripe_customer_id'] ?? '') : '')),
            'stripe_subscription_id' => $subscriptionId,
            'stripe_price_id' => $priceId,
            'status' => $this->normalizeStatus((string) ($subscription['status'] ?? 'incomplete')),
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'canceled_at' => $subscription['canceled_at'] ?? null,
            'current_period_start' => $currentPeriodStart,
            'current_period_end' => $currentPeriodEnd,
            'next_billing_date' => $currentPeriodEnd,
            'billing_interval' => $billingInterval,
            'plan_snapshot_title' => $planSnapshotTitle,
            'plan_snapshot_price' => $planSnapshotPrice,
            'last_checkout_session_id' => isset($context['last_checkout_session_id'])
                ? (string) $context['last_checkout_session_id']
                : (is_array($existing) ? (string) ($existing['last_checkout_session_id'] ?? '') : ''),
        ];
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

    /**
     * @param array<int, array<string, mixed>> $left
     * @param array<int, array<string, mixed>> $right
     *
     * @return array<int, array<string, mixed>>
     */
    private function mergeRowsUniqueById(array $left, array $right): array
    {
        $indexed = [];

        foreach (array_merge($left, $right) as $row) {
            $rowId = (int) ($row['id'] ?? 0);
            if ($rowId <= 0) {
                continue;
            }

            $indexed[$rowId] = $row;
        }

        usort($indexed, static function (array $a, array $b): int {
            return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        });

        return array_values($indexed);
    }

    private function resolveUserEmail(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        $user = get_user_by('id', $userId);
        if (! $user instanceof \WP_User) {
            return '';
        }

        return sanitize_email((string) $user->user_email);
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = sanitize_text_field($status);

        if (! in_array($normalized, self::ALLOWED_STATUSES, true)) {
            return 'incomplete';
        }

        return $normalized;
    }

    private function canCancelAutoRenew(string $status, bool $cancelAtPeriodEnd): bool
    {
        if ($cancelAtPeriodEnd) {
            return false;
        }

        return in_array($status, ['active', 'trialing', 'past_due', 'unpaid', 'incomplete'], true);
    }

    private function canResumeAutoRenew(string $status, bool $cancelAtPeriodEnd): bool
    {
        if (! $cancelAtPeriodEnd) {
            return false;
        }

        return in_array($status, ['active', 'trialing', 'past_due', 'unpaid'], true);
    }

    private function formatStatusLabel(string $status): string
    {
        $labels = [
            'active' => __('Active', 'wp-stripe-payments'),
            'trialing' => __('Trialing', 'wp-stripe-payments'),
            'incomplete' => __('Incomplete', 'wp-stripe-payments'),
            'incomplete_expired' => __('Incomplete expired', 'wp-stripe-payments'),
            'past_due' => __('Past due', 'wp-stripe-payments'),
            'canceled' => __('Canceled', 'wp-stripe-payments'),
            'unpaid' => __('Unpaid', 'wp-stripe-payments'),
        ];

        return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    private function formatStatusDescription(string $status, bool $cancelAtPeriodEnd): string
    {
        if ($cancelAtPeriodEnd && in_array($status, ['active', 'trialing', 'past_due', 'unpaid'], true)) {
            return __('Auto-renew is off. Access remains active until the current billing period ends.', 'wp-stripe-payments');
        }

        $descriptions = [
            'active' => __('Subscription is active and renewing automatically.', 'wp-stripe-payments'),
            'trialing' => __('Subscription is currently in trial period.', 'wp-stripe-payments'),
            'incomplete' => __('The initial payment is incomplete.', 'wp-stripe-payments'),
            'incomplete_expired' => __('The initial payment expired and the subscription was not activated.', 'wp-stripe-payments'),
            'past_due' => __('Payment is overdue. Stripe may retry according to your billing settings.', 'wp-stripe-payments'),
            'canceled' => __('Subscription is canceled and no longer renews.', 'wp-stripe-payments'),
            'unpaid' => __('Subscription is unpaid and renewal attempts failed.', 'wp-stripe-payments'),
        ];

        return $descriptions[$status] ?? '';
    }

    private function formatDateLabel(string $date): string
    {
        if ($date === '') {
            return __('N/A', 'wp-stripe-payments');
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        return function_exists('wp_date')
            ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)
            : gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function formatInterval(string $interval): string
    {
        $normalized = sanitize_text_field($interval);
        if ($normalized === '') {
            return __('N/A', 'wp-stripe-payments');
        }

        $labels = [
            'day' => __('Day', 'wp-stripe-payments'),
            'week' => __('Week', 'wp-stripe-payments'),
            'month' => __('Month', 'wp-stripe-payments'),
            'year' => __('Year', 'wp-stripe-payments'),
        ];

        return $labels[$normalized] ?? ucfirst($normalized);
    }
}
