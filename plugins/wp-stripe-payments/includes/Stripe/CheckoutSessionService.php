<?php

namespace WPStripePayments\Stripe;

use WC_Order;
use WP_Error;
use WPStripePayments\Utils\Logger;

class CheckoutSessionService
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
    public function createForOrder(WC_Order $order, string $successUrl, string $cancelUrl)
    {
        $lineItems = $this->buildLineItems($order);

        $params = [
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $order->get_id(),
            'metadata' => [
                'flow' => 'woocommerce_order',
                'order_id' => (string) $order->get_id(),
                'order_key' => (string) $order->get_order_key(),
                'order_number' => (string) $order->get_order_number(),
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'flow' => 'woocommerce_order',
                    'order_id' => (string) $order->get_id(),
                    'order_key' => (string) $order->get_order_key(),
                ],
            ],
        ];

        $customerEmail = (string) $order->get_billing_email();
        if ($customerEmail !== '') {
            $params['customer_email'] = $customerEmail;
        }

        if ((float) $order->get_total_tax() > 0) {
            $params['tax_id_collection'] = ['enabled' => true];
        }

        $this->logger->info('Creating Stripe Checkout Session for WooCommerce order.', [
            'order_id' => $order->get_id(),
            'currency' => $order->get_currency(),
            'total' => $order->get_total(),
        ]);

        return $this->client->post('/checkout/sessions', $params);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildLineItems(WC_Order $order): array
    {
        $currency = strtolower((string) $order->get_currency());
        $items = [];

        foreach ($order->get_items() as $orderItem) {
            if (! $orderItem instanceof \WC_Order_Item_Product) {
                continue;
            }

            $lineTotal = (float) $orderItem->get_total() + (float) $orderItem->get_total_tax();
            if ($lineTotal < 0) {
                continue;
            }

            $items[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $this->toStripeAmount($lineTotal),
                    'product_data' => [
                        'name' => sprintf(
                            __('%1$s (x%2$d)', 'wp-stripe-payments'),
                            $orderItem->get_name(),
                            max(1, (int) $orderItem->get_quantity())
                        ),
                    ],
                ],
            ];
        }

        foreach ($order->get_items('shipping') as $shippingItem) {
            $shippingTotal = (float) $shippingItem->get_total() + (float) $shippingItem->get_total_tax();
            if ($shippingTotal <= 0) {
                continue;
            }

            $items[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $this->toStripeAmount($shippingTotal),
                    'product_data' => [
                        'name' => __('Shipping', 'wp-stripe-payments'),
                    ],
                ],
            ];
        }

        foreach ($order->get_items('fee') as $feeItem) {
            $feeTotal = (float) $feeItem->get_total() + (float) $feeItem->get_total_tax();
            if ($feeTotal <= 0) {
                continue;
            }

            $items[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $this->toStripeAmount($feeTotal),
                    'product_data' => [
                        'name' => (string) $feeItem->get_name(),
                    ],
                ],
            ];
        }

        if (empty($items)) {
            $items[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $this->toStripeAmount((float) $order->get_total()),
                    'product_data' => [
                        'name' => sprintf(__('Order %s', 'wp-stripe-payments'), $order->get_order_number()),
                    ],
                ],
            ];
        }

        return $items;
    }

    private function toStripeAmount(float $amount): int
    {
        return max(0, (int) round($amount * 100));
    }
}
