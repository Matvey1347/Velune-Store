<?php

namespace WPStripePayments\Stripe;

use WP_Error;
use WPStripePayments\Admin\Settings;

class Client
{
    private const API_BASE = 'https://api.stripe.com/v1';

    public function isSecretKeyConfigured(): bool
    {
        return trim($this->getSecretKey()) !== '';
    }

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
        return $this->request('POST', $endpoint, $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>|WP_Error
     */
    public function get(string $endpoint, array $params = [])
    {
        return $this->request('GET', $endpoint, $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>|WP_Error
     */
    private function request(string $method, string $endpoint, array $params)
    {
        $secretKey = trim($this->getSecretKey());
        if ($secretKey === '') {
            return new WP_Error('stripe_missing_secret_key', __('Stripe secret key is not configured.', 'wp-stripe-payments'));
        }

        $url = self::API_BASE . $endpoint;
        $args = [
            'method' => strtoupper($method),
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey,
            ],
            'timeout' => 45,
        ];

        if (! empty($params)) {
            if (strtoupper($method) === 'GET') {
                $url = add_query_arg($this->flattenParams($params), $url);
            } else {
                $args['body'] = $this->flattenParams($params);
            }
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return new WP_Error('stripe_invalid_response', __('Invalid response from Stripe API.', 'wp-stripe-payments'), [
                'status' => $code,
                'response_body' => $body,
            ]);
        }

        if ($code < 200 || $code >= 300) {
            $message = $decoded['error']['message'] ?? __('Stripe API request failed.', 'wp-stripe-payments');
            return new WP_Error('stripe_api_error', (string) $message, [
                'status' => $code,
                'response' => $decoded,
                'response_body' => $body,
            ]);
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, string>
     */
    private function flattenParams(array $params): array
    {
        $flat = [];
        $this->flattenNode($params, '', $flat);

        return $flat;
    }

    /**
     * @param mixed $value
     * @param array<string, string> $flat
     */
    private function flattenNode($value, string $prefix, array &$flat): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $nestedValue) {
                $nestedKey = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
                $this->flattenNode($nestedValue, $nestedKey, $flat);
            }
            return;
        }

        if ($value === null) {
            return;
        }

        $flat[$prefix] = is_bool($value)
            ? ($value ? 'true' : 'false')
            : (string) $value;
    }
}
