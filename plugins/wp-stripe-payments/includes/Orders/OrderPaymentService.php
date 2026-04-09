<?php

namespace WPStripePayments\Orders;

use WC_Order;
use WP_Error;
use WPStripePayments\Stripe\CheckoutSessionService;
use WPStripePayments\Utils\Logger;

class OrderPaymentService
{
    private CheckoutSessionService $checkoutSessionService;

    private Logger $logger;

    public function __construct(?CheckoutSessionService $checkoutSessionService = null, ?Logger $logger = null)
    {
        $this->checkoutSessionService = $checkoutSessionService ?? new CheckoutSessionService();
        $this->logger = $logger ?? new Logger();
    }

    /**
     * @return array{checkout_url:string,session_id:string}|WP_Error
     */
    public function startCheckout(WC_Order $order)
    {
        $successUrl = add_query_arg(
            [
                'wp_sp_order' => (string) $order->get_id(),
                'key' => $order->get_order_key(),
                'session_id' => '{CHECKOUT_SESSION_ID}',
            ],
            $order->get_checkout_order_received_url()
        );

        $cancelUrl = add_query_arg(
            [
                'wp_sp_order' => (string) $order->get_id(),
                'key' => $order->get_order_key(),
            ],
            $order->get_checkout_payment_url(false)
        );

        $session = $this->checkoutSessionService->createForOrder($order, $successUrl, $cancelUrl);
        if (is_wp_error($session)) {
            return $session;
        }

        $sessionId = (string) ($session['id'] ?? '');
        $checkoutUrl = (string) ($session['url'] ?? '');

        if ($sessionId === '' || $checkoutUrl === '') {
            return new WP_Error('stripe_session_missing_data', __('Stripe Checkout session was created without redirect URL.', 'wp-stripe-payments'));
        }

        $paymentIntentId = (string) ($session['payment_intent'] ?? '');
        $order->update_meta_data('_wp_sp_checkout_session_id', $sessionId);
        $order->update_meta_data('_wp_sp_checkout_session_status', (string) ($session['status'] ?? 'open'));

        if ($paymentIntentId !== '') {
            $order->update_meta_data('_wp_sp_payment_intent_id', $paymentIntentId);
        }

        $attempts = $order->get_meta('_wp_sp_checkout_session_attempts', true);
        if (! is_array($attempts)) {
            $attempts = [];
        }

        $attempts[] = [
            'session_id' => $sessionId,
            'created_at' => gmdate('c'),
            'status' => (string) ($session['status'] ?? 'open'),
        ];

        $order->update_meta_data('_wp_sp_checkout_session_attempts', $attempts);
        $order->save();

        $this->logger->info('Stripe Checkout session created for order payment.', [
            'order_id' => $order->get_id(),
            'checkout_session_id' => $sessionId,
            'payment_intent_id' => $paymentIntentId,
        ]);

        return [
            'checkout_url' => $checkoutUrl,
            'session_id' => $sessionId,
        ];
    }
}
