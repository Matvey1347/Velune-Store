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
            $this->markSyncResult($planId, 'error', __('Subscription plan does not exist.', 'wp-stripe-payments'));
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
                $this->markSyncResult($planId, 'error', $productResponse->get_error_message());
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
                $this->markSyncResult($planId, 'error', $updateResponse->get_error_message());
                return $updateResponse;
            }
        }

        if ($productId === '') {
            $this->markSyncResult($planId, 'error', __('Stripe product ID was not returned.', 'wp-stripe-payments'));
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
            $this->markSyncResult($planId, 'error', $priceResponse->get_error_message());
            $this->logger->error('Stripe price sync failed.', [
                'plan_id' => $planId,
                'product_id' => $productId,
                'error' => $priceResponse->get_error_message(),
            ]);
            return $priceResponse;
        }

        $priceId = (string) ($priceResponse['id'] ?? '');

        if ($priceId === '') {
            $this->markSyncResult($planId, 'error', __('Stripe price ID was not returned.', 'wp-stripe-payments'));
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
            'last_sync_status' => 'success',
            'last_sync_message' => __('Synced to Stripe successfully.', 'wp-stripe-payments'),
            'last_sync_at' => gmdate('Y-m-d H:i:s'),
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

    private function markSyncResult(int $planId, string $status, string $message): void
    {
        $plan = $this->planRepository->findById($planId);
        if ($plan === null) {
            return;
        }

        $this->planRepository->savePlanMeta($planId, [
            'description' => $plan['description'],
            'image' => $plan['image'],
            'price' => $plan['price'],
            'billing_interval' => $plan['billing_interval'],
            'status' => $plan['status'],
            'stripe_product_id' => $plan['stripe_product_id'],
            'stripe_price_id' => $plan['stripe_price_id'],
            'last_sync_status' => sanitize_key($status),
            'last_sync_message' => sanitize_text_field($message),
            'last_sync_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
