<?php

namespace WPStripePayments\Stripe;

use WC_Order;
use WP_Error;
use WPStripePayments\Utils\Logger;

class PaymentService
{
    private Client $client;

    private Logger $logger;

    public function __construct(?Client $client = null, ?Logger $logger = null)
    {
        $this->client = $client ?? new Client();
        $this->logger = $logger ?? new Logger();
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function createPaymentIntent(WC_Order $order, string $paymentMethodId)
    {
        $amount = (int) round(((float) $order->get_total()) * 100);

        $this->logger->info('Creating Stripe payment intent.', [
            'order_id' => $order->get_id(),
            'amount' => $amount,
        ]);

        $params = [
            'amount' => $amount,
            'currency' => strtolower($order->get_currency()),
            'confirm' => 'true',
            'payment_method' => $paymentMethodId,
            'description' => sprintf(__('Order %s', 'wp-stripe-payments'), $order->get_order_number()),
            'metadata[order_id]' => (string) $order->get_id(),
            'metadata[order_key]' => (string) $order->get_order_key(),
        ];

        return $this->client->post('/payment_intents', $params);
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function confirmPaymentIntent(string $paymentIntentId)
    {
        $this->logger->info('Confirming Stripe payment intent.', [
            'payment_intent_id' => $paymentIntentId,
        ]);

        return $this->client->post('/payment_intents/' . rawurlencode($paymentIntentId) . '/confirm', []);
    }

    public function isSuccessful(array $paymentIntent): bool
    {
        return ($paymentIntent['status'] ?? '') === 'succeeded';
    }
}
