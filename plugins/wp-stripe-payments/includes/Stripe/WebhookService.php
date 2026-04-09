<?php

namespace WPStripePayments\Stripe;

use WP_REST_Request;
use WP_REST_Response;
use WPStripePayments\Admin\Settings;
use WPStripePayments\Core\Hooks;
use WPStripePayments\Subscriptions\CustomerSubscriptionService;
use WPStripePayments\Utils\Logger;

class WebhookService
{
    private const EVENT_CACHE_OPTION = 'wp_sp_processed_webhook_events';

    private Logger $logger;

    private CustomerSubscriptionService $customerSubscriptionService;

    public function __construct(?Logger $logger = null, ?CustomerSubscriptionService $customerSubscriptionService = null)
    {
        $this->logger = $logger ?? new Logger();
        $this->customerSubscriptionService = $customerSubscriptionService ?? new CustomerSubscriptionService();
    }

    public function registerRoutes(): void
    {
        register_rest_route(Hooks::REST_NAMESPACE, Hooks::REST_WEBHOOK_ROUTE, [
            'methods' => 'POST',
            'callback' => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $payload = (string) $request->get_body();
        $signature = (string) $request->get_header('stripe-signature');
        $secret = Settings::get('webhook_secret');

        if (! $this->isValidSignature($payload, $signature, $secret)) {
            $this->logger->warning('Invalid Stripe webhook signature.');
            return new WP_REST_Response(['received' => false, 'error' => 'invalid_signature'], 400);
        }

        $event = json_decode($payload, true);
        if (! is_array($event)) {
            $this->logger->warning('Invalid Stripe webhook payload JSON.');
            return new WP_REST_Response(['received' => false, 'error' => 'invalid_payload'], 400);
        }

        $eventId = (string) ($event['id'] ?? '');
        $eventType = (string) ($event['type'] ?? '');

        if ($eventId !== '' && $this->isEventProcessed($eventId)) {
            return new WP_REST_Response(['received' => true], 200);
        }

        $object = $event['data']['object'] ?? [];

        $this->logger->info('Stripe webhook event received.', [
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);

        switch ($eventType) {
            case 'checkout.session.completed':
                if (is_array($object)) {
                    $this->handleCheckoutSessionCompleted($object);
                }
                break;

            case 'payment_intent.succeeded':
                if (is_array($object)) {
                    $this->handlePaymentIntentSucceeded($object);
                }
                break;

            case 'payment_intent.payment_failed':
                if (is_array($object)) {
                    $this->handlePaymentIntentFailed($object);
                }
                break;

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                if (is_array($object)) {
                    $synced = $this->customerSubscriptionService->syncFromSubscriptionObject($object);
                    if (! $synced) {
                        $this->logger->warning('Failed to sync local subscription record from webhook event.', [
                            'event_type' => $eventType,
                            'stripe_subscription_id' => (string) ($object['id'] ?? ''),
                        ]);
                    }
                }
                break;

            case 'invoice.paid':
                $this->handleInvoicePaid($object);
                break;

            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($object);
                break;
        }

        if ($eventId !== '') {
            $this->markEventProcessed($eventId);
        }

        return new WP_REST_Response(['received' => true], 200);
    }

    /**
     * @param array<string, mixed> $session
     */
    private function handleCheckoutSessionCompleted(array $session): void
    {
        $flow = (string) ($session['metadata']['flow'] ?? '');

        if ($flow === 'subscription_checkout') {
            $synced = $this->customerSubscriptionService->syncFromCheckoutSession($session);
            if (! $synced) {
                $this->logger->warning('Failed to sync local subscription from checkout.session.completed webhook.', [
                    'stripe_session_id' => (string) ($session['id'] ?? ''),
                ]);
            }
            return;
        }

        $order = $this->findOrderFromCheckoutSession($session);
        if (! $order instanceof \WC_Order) {
            return;
        }

        $paymentIntentId = (string) ($session['payment_intent'] ?? '');
        if ($paymentIntentId !== '') {
            $order->update_meta_data('_wp_sp_payment_intent_id', $paymentIntentId);
        }

        $order->update_meta_data('_wp_sp_checkout_session_status', (string) ($session['status'] ?? 'complete'));
        $order->save();

        if (! $order->is_paid()) {
            $order->payment_complete($paymentIntentId);
            $order->add_order_note(__('Stripe Checkout session completed (webhook).', 'wp-stripe-payments'));
        }
    }

    /**
     * @param array<string, mixed> $paymentIntent
     */
    private function handlePaymentIntentSucceeded(array $paymentIntent): void
    {
        $order = $this->findOrderByPaymentIntent($paymentIntent);
        if (! $order instanceof \WC_Order) {
            return;
        }

        $paymentIntentId = (string) ($paymentIntent['id'] ?? '');

        if (! $order->is_paid()) {
            $order->payment_complete($paymentIntentId);
            $order->add_order_note(__('Stripe payment confirmed by webhook.', 'wp-stripe-payments'));
        }
    }

    /**
     * @param array<string, mixed> $paymentIntent
     */
    private function handlePaymentIntentFailed(array $paymentIntent): void
    {
        $order = $this->findOrderByPaymentIntent($paymentIntent);
        if (! $order instanceof \WC_Order) {
            return;
        }

        $errorMessage = (string) ($paymentIntent['last_payment_error']['message'] ?? __('Stripe payment failed.', 'wp-stripe-payments'));

        if (! $order->is_paid()) {
            $order->update_status('failed', sprintf(__('Stripe payment failed via webhook: %s', 'wp-stripe-payments'), $errorMessage));
        }
    }

    /**
     * @param mixed $invoiceObject
     */
    private function handleInvoicePaid($invoiceObject): void
    {
        if (! is_array($invoiceObject)) {
            return;
        }

        $subscriptionId = (string) ($invoiceObject['subscription'] ?? '');
        if ($subscriptionId === '') {
            return;
        }

        $this->customerSubscriptionService->markInvoicePaymentStatus($subscriptionId, 'active', $invoiceObject);
        $this->logger->info('Processed invoice.paid for subscription.', [
            'stripe_subscription_id' => $subscriptionId,
        ]);
    }

    /**
     * @param mixed $invoiceObject
     */
    private function handleInvoicePaymentFailed($invoiceObject): void
    {
        if (! is_array($invoiceObject)) {
            return;
        }

        $subscriptionId = (string) ($invoiceObject['subscription'] ?? '');
        if ($subscriptionId === '') {
            return;
        }

        $this->customerSubscriptionService->markInvoicePaymentStatus($subscriptionId, 'past_due', $invoiceObject);
        $this->logger->info('Processed invoice.payment_failed for subscription.', [
            'stripe_subscription_id' => $subscriptionId,
        ]);
    }

    /**
     * @param array<string, mixed> $session
     */
    private function findOrderFromCheckoutSession(array $session): ?\WC_Order
    {
        $sessionId = (string) ($session['id'] ?? '');

        if ($sessionId !== '') {
            $orders = wc_get_orders([
                'limit' => 1,
                'meta_key' => '_wp_sp_checkout_session_id',
                'meta_value' => $sessionId,
                'type' => 'shop_order',
            ]);

            if (! empty($orders) && $orders[0] instanceof \WC_Order) {
                return $orders[0];
            }
        }

        $orderId = isset($session['metadata']['order_id']) ? (int) $session['metadata']['order_id'] : 0;
        if ($orderId <= 0) {
            return null;
        }

        $order = wc_get_order($orderId);

        return $order instanceof \WC_Order ? $order : null;
    }

    /**
     * @param array<string, mixed> $paymentIntent
     */
    private function findOrderByPaymentIntent(array $paymentIntent): ?\WC_Order
    {
        $paymentIntentId = (string) ($paymentIntent['id'] ?? '');

        if ($paymentIntentId !== '') {
            $orders = wc_get_orders([
                'limit' => 1,
                'meta_key' => '_wp_sp_payment_intent_id',
                'meta_value' => $paymentIntentId,
                'type' => 'shop_order',
            ]);

            if (! empty($orders) && $orders[0] instanceof \WC_Order) {
                return $orders[0];
            }
        }

        $metadataOrderId = isset($paymentIntent['metadata']['order_id']) ? (int) $paymentIntent['metadata']['order_id'] : 0;
        if ($metadataOrderId <= 0) {
            return null;
        }

        $order = wc_get_order($metadataOrderId);
        return $order instanceof \WC_Order ? $order : null;
    }

    private function isValidSignature(string $payload, string $signatureHeader, string $secret): bool
    {
        if ($payload === '' || $signatureHeader === '' || $secret === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $segment) {
            $pair = explode('=', trim($segment), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';

        if ($timestamp === '' || $signature === '') {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        return hash_equals($expected, $signature);
    }

    private function isEventProcessed(string $eventId): bool
    {
        $events = get_option(self::EVENT_CACHE_OPTION, []);
        if (! is_array($events)) {
            return false;
        }

        return isset($events[$eventId]);
    }

    private function markEventProcessed(string $eventId): void
    {
        $events = get_option(self::EVENT_CACHE_OPTION, []);
        if (! is_array($events)) {
            $events = [];
        }

        $events[$eventId] = time();

        if (count($events) > 500) {
            asort($events);
            $events = array_slice($events, -500, null, true);
        }

        update_option(self::EVENT_CACHE_OPTION, $events, false);
    }
}
