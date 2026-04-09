<?php

namespace WPStripePayments\Stripe;

use WP_Error;
use WPStripePayments\Utils\Logger;

class CustomerSubscriptionStripeService
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
    public function retrieveSubscription(string $stripeSubscriptionId)
    {
        if ($stripeSubscriptionId === '') {
            return new WP_Error('wp_sp_missing_subscription_id', __('Missing Stripe subscription ID.', 'wp-stripe-payments'));
        }

        return $this->client->get('/subscriptions/' . rawurlencode($stripeSubscriptionId), [
            'expand' => ['items.data.price'],
        ]);
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function cancelAtPeriodEnd(string $stripeSubscriptionId)
    {
        if ($stripeSubscriptionId === '') {
            return new WP_Error('wp_sp_missing_subscription_id', __('Missing Stripe subscription ID.', 'wp-stripe-payments'));
        }

        $response = $this->client->post('/subscriptions/' . rawurlencode($stripeSubscriptionId), [
            'cancel_at_period_end' => true,
            'proration_behavior' => 'none',
            'expand' => ['items.data.price'],
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('Stripe cancel-at-period-end request failed.', [
                'stripe_subscription_id' => $stripeSubscriptionId,
                'error' => $response->get_error_message(),
            ]);
        } else {
            $this->logger->info('Stripe cancel-at-period-end request succeeded.', [
                'stripe_subscription_id' => $stripeSubscriptionId,
            ]);
        }

        return $response;
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function resumeAutoRenew(string $stripeSubscriptionId)
    {
        if ($stripeSubscriptionId === '') {
            return new WP_Error('wp_sp_missing_subscription_id', __('Missing Stripe subscription ID.', 'wp-stripe-payments'));
        }

        $response = $this->client->post('/subscriptions/' . rawurlencode($stripeSubscriptionId), [
            'cancel_at_period_end' => false,
            'expand' => ['items.data.price'],
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('Stripe resume-auto-renew request failed.', [
                'stripe_subscription_id' => $stripeSubscriptionId,
                'error' => $response->get_error_message(),
            ]);
        } else {
            $this->logger->info('Stripe resume-auto-renew request succeeded.', [
                'stripe_subscription_id' => $stripeSubscriptionId,
            ]);
        }

        return $response;
    }
}
