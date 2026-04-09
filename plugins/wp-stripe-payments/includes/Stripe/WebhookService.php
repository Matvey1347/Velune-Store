<?php

namespace WPStripePayments\Stripe;

use WP_REST_Request;
use WP_REST_Response;
use WPStripePayments\Admin\Settings;
use WPStripePayments\Core\Hooks;
use WPStripePayments\Utils\Logger;

class WebhookService
{
    private Logger $logger;

    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger ?? new Logger();
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

        $eventType = (string) ($event['type'] ?? '');
        $paymentIntent = $event['data']['object'] ?? [];
        $paymentIntentId = (string) ($paymentIntent['id'] ?? '');

        $this->logger->info('Stripe webhook event received.', [
            'event_type' => $eventType,
            'payment_intent_id' => $paymentIntentId,
        ]);

        if ($paymentIntentId === '') {
            return new WP_REST_Response(['received' => true], 200);
        }

        $order = $this->findOrderByPaymentIntent($paymentIntentId, $paymentIntent);
        if (! $order) {
            $this->logger->warning('No WooCommerce order found for Stripe webhook.', [
                'payment_intent_id' => $paymentIntentId,
            ]);
            return new WP_REST_Response(['received' => true], 200);
        }

        $alreadyProcessed = $order->get_meta('_wp_stripe_webhook_processed_' . $eventType, true);
        if ($alreadyProcessed === 'yes') {
            return new WP_REST_Response(['received' => true], 200);
        }

        switch ($eventType) {
            case 'payment_intent.succeeded':
                if (! $order->is_paid()) {
                    $order->payment_complete($paymentIntentId);
                    $order->add_order_note(__('Stripe payment confirmed by webhook.', 'wp-stripe-payments'));
                }
                break;

            case 'payment_intent.payment_failed':
                if (! in_array($order->get_status(), ['failed', 'cancelled', 'refunded'], true)) {
                    $order->update_status('failed', __('Stripe payment failed (webhook).', 'wp-stripe-payments'));
                }
                break;
        }

        $order->update_meta_data('_wp_stripe_webhook_processed_' . $eventType, 'yes');
        $order->save();

        return new WP_REST_Response(['received' => true], 200);
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

    /**
     * @param array<string, mixed> $paymentIntent
     */
    private function findOrderByPaymentIntent(string $paymentIntentId, array $paymentIntent): ?\WC_Order
    {
        $orders = wc_get_orders([
            'limit' => 1,
            'meta_key' => '_wp_stripe_payment_intent_id',
            'meta_value' => $paymentIntentId,
            'type' => 'shop_order',
        ]);

        if (! empty($orders) && $orders[0] instanceof \WC_Order) {
            return $orders[0];
        }

        $metadataOrderId = $paymentIntent['metadata']['order_id'] ?? null;
        if (! $metadataOrderId) {
            return null;
        }

        $order = wc_get_order((int) $metadataOrderId);
        return $order instanceof \WC_Order ? $order : null;
    }
}
