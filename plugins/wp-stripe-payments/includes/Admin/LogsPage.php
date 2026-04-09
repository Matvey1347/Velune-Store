<?php

namespace WPStripePayments\Admin;

use WPStripePayments\Utils\Logger;

class LogsPage
{
    public function render(): void
    {
        if (! current_user_can('manage_options') && ! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-stripe-payments'));
        }

        $logs = Logger::getStoredLogs();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Stripe Logs', 'wp-stripe-payments'); ?></h1>
            <p><?php esc_html_e('Recent plugin logs are shown below. Full structured logs are also sent to WooCommerce logger.', 'wp-stripe-payments'); ?></p>

            <?php if (empty($logs)) : ?>
                <p><?php esc_html_e('No logs recorded yet.', 'wp-stripe-payments'); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                    <tr>
                        <th><?php esc_html_e('Time', 'wp-stripe-payments'); ?></th>
                        <th><?php esc_html_e('Level', 'wp-stripe-payments'); ?></th>
                        <th><?php esc_html_e('Message', 'wp-stripe-payments'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_reverse($logs) as $entry) : ?>
                        <tr>
                            <td><?php echo esc_html((string) ($entry['time'] ?? '')); ?></td>
                            <td><?php echo esc_html(strtoupper((string) ($entry['level'] ?? 'info'))); ?></td>
                            <td>
                                <?php echo esc_html((string) ($entry['message'] ?? '')); ?>
                                <?php if (! empty($entry['context']) && is_array($entry['context'])) : ?>
                                    <br />
                                    <code><?php echo esc_html(wp_json_encode($entry['context'])); ?></code>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
