<?php

namespace WPStripePayments\Stripe;

use WP_Error;
use WPStripePayments\Admin\Settings;

class Client
{
    private const API_BASE = 'https://api.stripe.com/v1';

    public function getSecretKey(): string
    {
        return Settings::isTestMode()
            ? Settings::get('test_secret_key')
            : Settings::get('live_secret_key');
    }

    public function getPublishableKey(): string
    {
        return Settings::isTestMode()
            ? Settings::get('test_publishable_key')
            : Settings::get('live_publishable_key');
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>|WP_Error
     */
    public function post(string $endpoint, array $params)
    {
        $secretKey = $this->getSecretKey();
        if ($secretKey === '') {
            return new WP_Error('stripe_missing_secret_key', __('Stripe secret key is not configured.', 'wp-stripe-payments'));
        }

        $response = wp_remote_post(self::API_BASE . $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey,
            ],
            'body' => $params,
            'timeout' => 45,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return new WP_Error('stripe_invalid_response', __('Invalid response from Stripe API.', 'wp-stripe-payments'));
        }

        if ($code < 200 || $code >= 300) {
            $message = $decoded['error']['message'] ?? __('Stripe API request failed.', 'wp-stripe-payments');
            return new WP_Error('stripe_api_error', (string) $message, ['status' => $code, 'response' => $decoded]);
        }

        return $decoded;
    }
}
