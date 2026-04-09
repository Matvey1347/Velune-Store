<?php

namespace WPStripePayments\Stripe;

use WP_Error;
use WPStripePayments\Subscriptions\PlanRepository;
use WPStripePayments\Utils\Logger;

class SubscriptionPlanSyncService
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
     * @return array<string, string>|WP_Error
     */
    public function syncPlan(int $planId)
    {
        $plan = $this->planRepository->findById($planId);
        if ($plan === null) {
            return new WP_Error('wp_sp_plan_missing', __('Subscription plan does not exist.', 'wp-stripe-payments'));
        }

        $productId = $plan['stripe_product_id'];

        if ($productId === '') {
            $productResponse = $this->client->post('/products', [
                'name' => $plan['title'],
                'description' => $plan['description'],
                'metadata' => [
                    'plan_id' => (string) $planId,
                ],
            ]);

            if (is_wp_error($productResponse)) {
                $this->logger->error('Stripe product sync failed.', [
                    'plan_id' => $planId,
                    'error' => $productResponse->get_error_message(),
                ]);
                return $productResponse;
            }

            $productId = (string) ($productResponse['id'] ?? '');
        } else {
            $updateResponse = $this->client->post('/products/' . rawurlencode($productId), [
                'name' => $plan['title'],
                'description' => $plan['description'],
                'active' => $plan['status'] === 'active',
            ]);

            if (is_wp_error($updateResponse)) {
                return $updateResponse;
            }
        }

        if ($productId === '') {
            return new WP_Error('wp_sp_product_id_missing', __('Stripe product ID was not returned.', 'wp-stripe-payments'));
        }

        $priceResponse = $this->client->post('/prices', [
            'unit_amount' => max(0, (int) round(((float) $plan['price']) * 100)),
            'currency' => strtolower(get_woocommerce_currency()),
            'product' => $productId,
            'recurring' => [
                'interval' => $plan['billing_interval'],
                'interval_count' => 1,
            ],
            'metadata' => [
                'plan_id' => (string) $planId,
            ],
        ]);

        if (is_wp_error($priceResponse)) {
            $this->logger->error('Stripe price sync failed.', [
                'plan_id' => $planId,
                'product_id' => $productId,
                'error' => $priceResponse->get_error_message(),
            ]);
            return $priceResponse;
        }

        $priceId = (string) ($priceResponse['id'] ?? '');

        if ($priceId === '') {
            return new WP_Error('wp_sp_price_id_missing', __('Stripe price ID was not returned.', 'wp-stripe-payments'));
        }

        $this->planRepository->savePlanMeta($planId, [
            'description' => $plan['description'],
            'image' => $plan['image'],
            'price' => $plan['price'],
            'billing_interval' => $plan['billing_interval'],
            'status' => $plan['status'],
            'stripe_product_id' => $productId,
            'stripe_price_id' => $priceId,
        ]);

        $this->logger->info('Subscription plan synced to Stripe.', [
            'plan_id' => $planId,
            'stripe_product_id' => $productId,
            'stripe_price_id' => $priceId,
        ]);

        return [
            'stripe_product_id' => $productId,
            'stripe_price_id' => $priceId,
        ];
    }
}
