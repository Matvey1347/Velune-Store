<?php

namespace WPStripePayments\Gateway;

use WC_Payment_Gateway;
use WP_Error;
use WPStripePayments\Admin\Settings;
use WPStripePayments\Core\Hooks;
use WPStripePayments\Orders\OrderPaymentService;
use WPStripePayments\Utils\Logger;

class StripeGateway extends WC_Payment_Gateway
{
    private OrderPaymentService $orderPaymentService;

    private Logger $logger;

    public function __construct()
    {
        $this->id = Hooks::GATEWAY_ID;
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = __('Stripe Checkout', 'wp-stripe-payments');
        $this->method_description = __('Accept payments using Stripe Hosted Checkout.', 'wp-stripe-payments');
        $this->supports = ['products'];

        $this->title = Settings::get('title', __('Stripe (Custom)', 'wp-stripe-payments'));
        $this->description = Settings::get('description', __('Pay securely via Stripe.', 'wp-stripe-payments'));
        $this->enabled = Settings::isGatewayEnabled() ? 'yes' : 'no';

        $this->orderPaymentService = new OrderPaymentService();
        $this->logger = new Logger();
    }

    public function admin_options(): void
    {
        echo '<h2>' . esc_html($this->get_method_title()) . '</h2>';
        echo '<p>' . esc_html__('This gateway is configured from Stripe Payments > Settings.', 'wp-stripe-payments') . '</p>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=wp-stripe-payments-settings')) . '" class="button">' . esc_html__('Open Plugin Settings', 'wp-stripe-payments') . '</a></p>';
    }

    public function is_available(): bool
    {
        if (! parent::is_available()) {
            return false;
        }

        return Settings::isGatewayEnabled() && Settings::isConfiguredForCurrentMode();
    }

    /**
     * @return array<string, string>|void
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if (! $order instanceof \WC_Order) {
            wc_add_notice(__('Invalid order.', 'wp-stripe-payments'), 'error');
            return;
        }

        $this->logger->info('Starting hosted Stripe Checkout for order.', [
            'order_id' => $order->get_id(),
        ]);

        $result = $this->orderPaymentService->startCheckout($order);
        if (is_wp_error($result)) {
            $this->handleCheckoutError($result, $order->get_id());
            return;
        }

        return [
            'result' => 'success',
            'redirect' => $result['checkout_url'],
        ];
    }

    private function handleCheckoutError(WP_Error $error, int $orderId): void
    {
        $message = $error->get_error_message();
        wc_add_notice($message !== '' ? $message : __('Unable to start Stripe Checkout.', 'wp-stripe-payments'), 'error');

        $this->logger->error('Stripe Checkout session creation failed for order.', [
            'order_id' => $orderId,
            'code' => $error->get_error_code(),
            'message' => $message,
            'data' => $error->get_error_data(),
        ]);
    }
}
