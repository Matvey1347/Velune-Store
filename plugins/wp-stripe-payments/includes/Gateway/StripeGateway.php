<?php

namespace WPStripePayments\Gateway;

use WC_Payment_Gateway;
use WP_Error;
use WPStripePayments\Admin\Settings;
use WPStripePayments\Core\Hooks;
use WPStripePayments\Stripe\PaymentService;
use WPStripePayments\Utils\Logger;

class StripeGateway extends WC_Payment_Gateway
{
    private PaymentService $paymentService;

    private Logger $logger;

    public function __construct()
    {
        $this->id = Hooks::GATEWAY_ID;
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = __('Stripe (Custom)', 'wp-stripe-payments');
        $this->method_description = __('Accept Stripe payments using plugin-owned Stripe settings.', 'wp-stripe-payments');
        $this->supports = ['products', 'payments'];

        $this->title = Settings::get('title', __('Stripe (Custom)', 'wp-stripe-payments'));
        $this->description = Settings::get('description', __('Pay securely via Stripe.', 'wp-stripe-payments'));
        $this->enabled = Settings::isGatewayEnabled() ? 'yes' : 'no';

        $this->paymentService = new PaymentService();
        $this->logger = new Logger();
    }

    public function admin_options(): void
    {
        echo '<h2>' . esc_html($this->get_method_title()) . '</h2>';
        echo '<p>' . esc_html__('This gateway is configured from Stripe Payments > Settings.', 'wp-stripe-payments') . '</p>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=wp-stripe-payments-settings')) . '" class="button">' . esc_html__('Open Plugin Settings', 'wp-stripe-payments') . '</a></p>';
    }

    public function payment_fields(): void
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }

        echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form">';
        echo '<p>' . esc_html__('Enter a Stripe Payment Method ID (for example: pm_card_visa in test mode).', 'wp-stripe-payments') . '</p>';
        echo '<p class="form-row form-row-wide">';
        echo '<label for="wp_stripe_payment_method">' . esc_html__('Payment Method ID', 'wp-stripe-payments') . ' <span class="required">*</span></label>';
        echo '<input id="wp_stripe_payment_method" name="wp_stripe_payment_method" type="text" autocomplete="off" />';
        echo '</p>';
        echo '</fieldset>';
    }

    /**
     * @return array<string, string>|void
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if (! $order) {
            wc_add_notice(__('Invalid order.', 'wp-stripe-payments'), 'error');
            return;
        }

        $paymentMethodId = isset($_POST['wp_stripe_payment_method']) ? wc_clean(wp_unslash($_POST['wp_stripe_payment_method'])) : '';
        if ($paymentMethodId === '') {
            wc_add_notice(__('Please provide a Stripe payment method.', 'wp-stripe-payments'), 'error');
            return;
        }

        $this->logger->info('Processing Stripe payment.', [
            'order_id' => $order->get_id(),
        ]);

        $intent = $this->paymentService->createPaymentIntent($order, $paymentMethodId);

        if (is_wp_error($intent)) {
            $this->handlePaymentError($intent);
            return;
        }

        $paymentIntentId = (string) ($intent['id'] ?? '');
        $status = (string) ($intent['status'] ?? '');

        if ($paymentIntentId !== '') {
            $order->update_meta_data('_wp_stripe_payment_intent_id', $paymentIntentId);
            $order->save();
        }

        if ($status === 'succeeded') {
            $order->payment_complete($paymentIntentId);
            $order->add_order_note(__('Stripe payment completed successfully.', 'wp-stripe-payments'));

            WC()->cart?->empty_cart();

            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        }

        if ($status === 'requires_action') {
            wc_add_notice(__('Additional payment authentication is required. This minimal gateway currently supports direct confirmations only.', 'wp-stripe-payments'), 'error');
            return;
        }

        $errorMessage = __('Stripe payment could not be completed.', 'wp-stripe-payments');

        if (isset($intent['last_payment_error']['message']) && is_string($intent['last_payment_error']['message'])) {
            $errorMessage = $intent['last_payment_error']['message'];
        }

        wc_add_notice($errorMessage, 'error');
        $this->logger->warning('Stripe payment failed.', [
            'order_id' => $order->get_id(),
            'status' => $status,
            'payment_intent_id' => $paymentIntentId,
        ]);
    }

    private function handlePaymentError(WP_Error $error): void
    {
        $message = $error->get_error_message();
        wc_add_notice($message !== '' ? $message : __('Stripe payment error.', 'wp-stripe-payments'), 'error');

        $this->logger->error('Stripe payment request error.', [
            'code' => $error->get_error_code(),
            'message' => $message,
            'data' => $error->get_error_data(),
        ]);
    }
}
