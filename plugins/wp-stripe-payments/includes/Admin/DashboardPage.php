<?php

namespace WPStripePayments\Admin;

use WPStripePayments\Subscriptions\CustomerSubscriptionBillingHistoryRepository;
use WPStripePayments\Subscriptions\CustomerSubscriptionRepository;
use WPStripePayments\Utils\Logger;

class DashboardPage
{
    public function render(): void
    {
        if (! AdminContext::canManage()) {
            AdminContext::denyAccess();
        }

        $settings = Settings::all();
        $subscriptionRepo = new CustomerSubscriptionRepository();
        $billingRepo = new CustomerSubscriptionBillingHistoryRepository();

        $statusCounts = $subscriptionRepo->countByStatus();
        $autoRenew = $subscriptionRepo->countAutoRenewStates();
        $billingSummary = $billingRepo->summarize([
            'date_from' => gmdate('Y-m-d', strtotime('-30 days')),
            'date_to' => gmdate('Y-m-d'),
        ]);

        $mode = $settings['testmode'] === 'yes' ? __('Test', 'wp-stripe-payments') : __('Live', 'wp-stripe-payments');

        $testKeysConfigured = $settings['test_publishable_key'] !== '' && $settings['test_secret_key'] !== '';
        $liveKeysConfigured = $settings['live_publishable_key'] !== '' && $settings['live_secret_key'] !== '';
        $webhookConfigured = $settings['webhook_secret'] !== '';
        $billingPortalConfigured = Settings::isBillingPortalEnabled();
        $gatewayEnabled = Settings::isGatewayEnabled();

        $recentLogsCount = count(Logger::getStoredLogs());

        $mrr = $this->calculateMrrEstimate($subscriptionRepo->findAll(1000));

        $healthWarnings = [];
        if (! $gatewayEnabled) {
            $healthWarnings[] = __('Gateway is disabled.', 'wp-stripe-payments');
        }
        if (! $testKeysConfigured) {
            $healthWarnings[] = __('Test keys are missing.', 'wp-stripe-payments');
        }
        if (! $liveKeysConfigured) {
            $healthWarnings[] = __('Live keys are missing.', 'wp-stripe-payments');
        }
        if (! $webhookConfigured) {
            $healthWarnings[] = __('Webhook secret is missing.', 'wp-stripe-payments');
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(AdminContext::BRAND_NAME . ' ' . __('Dashboard', 'wp-stripe-payments')); ?></h1>
            <p class="wp-sp-page-intro"><?php esc_html_e('Control center for Stripe subscription operations, configuration health, and billing performance.', 'wp-stripe-payments'); ?></p>

            <div class="wp-sp-actions" style="margin-bottom:16px;">
                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=wp-stripe-payments-settings')); ?>"><?php esc_html_e('Open Settings', 'wp-stripe-payments'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wp-stripe-payments-setup-guide')); ?>"><?php esc_html_e('Open Setup Guide', 'wp-stripe-payments'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wp-stripe-payments-customer-subscriptions')); ?>"><?php esc_html_e('View Customer Subscriptions', 'wp-stripe-payments'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=wp_sp_sub_plan')); ?>"><?php esc_html_e('View Plans', 'wp-stripe-payments'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wp-stripe-payments-logs')); ?>"><?php esc_html_e('View Logs', 'wp-stripe-payments'); ?></a>
            </div>

            <div class="wp-sp-grid" style="margin-bottom:16px;">
                <?php $this->renderMetricCard(__('Active subscriptions', 'wp-stripe-payments'), (int) ($statusCounts['active'] ?? 0)); ?>
                <?php $this->renderMetricCard(__('Trialing subscriptions', 'wp-stripe-payments'), (int) ($statusCounts['trialing'] ?? 0)); ?>
                <?php $this->renderMetricCard(__('Past due subscriptions', 'wp-stripe-payments'), (int) ($statusCounts['past_due'] ?? 0)); ?>
                <?php $this->renderMetricCard(__('Canceled subscriptions', 'wp-stripe-payments'), (int) ($statusCounts['canceled'] ?? 0)); ?>
                <?php $this->renderMetricCard(__('Auto-renew ON', 'wp-stripe-payments'), (int) $autoRenew['on']); ?>
                <?php $this->renderMetricCard(__('Auto-renew OFF', 'wp-stripe-payments'), (int) $autoRenew['off']); ?>
                <?php $this->renderMetricCard(__('MRR estimate', 'wp-stripe-payments'), wp_kses_post(wc_price($mrr))); ?>
                <?php $this->renderMetricCard(__('Recent billing events (30d)', 'wp-stripe-payments'), (int) $billingSummary['successful'] + (int) $billingSummary['failed']); ?>
                <?php $this->renderMetricCard(__('Recent log events', 'wp-stripe-payments'), $recentLogsCount); ?>
            </div>

            <div class="wp-sp-grid-2">
                <div class="wp-sp-card">
                    <h2><?php esc_html_e('Configuration Health', 'wp-stripe-payments'); ?></h2>
                    <table class="widefat striped">
                        <tbody>
                        <tr>
                            <td><strong><?php esc_html_e('Current mode', 'wp-stripe-payments'); ?></strong></td>
                            <td><span class="wp-sp-status-badge is-neutral"><?php echo esc_html($mode); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Test keys present', 'wp-stripe-payments'); ?></strong></td>
                            <td><span class="wp-sp-status-badge <?php echo esc_attr($testKeysConfigured ? 'is-good' : 'is-warning'); ?>"><?php echo esc_html($testKeysConfigured ? __('Yes', 'wp-stripe-payments') : __('No', 'wp-stripe-payments')); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Live keys present', 'wp-stripe-payments'); ?></strong></td>
                            <td><span class="wp-sp-status-badge <?php echo esc_attr($liveKeysConfigured ? 'is-good' : 'is-warning'); ?>"><?php echo esc_html($liveKeysConfigured ? __('Yes', 'wp-stripe-payments') : __('No', 'wp-stripe-payments')); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Webhook configured', 'wp-stripe-payments'); ?></strong></td>
                            <td><span class="wp-sp-status-badge <?php echo esc_attr($webhookConfigured ? 'is-good' : 'is-warning'); ?>"><?php echo esc_html($webhookConfigured ? __('Yes', 'wp-stripe-payments') : __('No', 'wp-stripe-payments')); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Billing portal enabled', 'wp-stripe-payments'); ?></strong></td>
                            <td><span class="wp-sp-status-badge <?php echo esc_attr($billingPortalConfigured ? 'is-good' : 'is-warning'); ?>"><?php echo esc_html($billingPortalConfigured ? __('Yes', 'wp-stripe-payments') : __('No', 'wp-stripe-payments')); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Gateway enabled', 'wp-stripe-payments'); ?></strong></td>
                            <td><span class="wp-sp-status-badge <?php echo esc_attr($gatewayEnabled ? 'is-good' : 'is-warning'); ?>"><?php echo esc_html($gatewayEnabled ? __('Yes', 'wp-stripe-payments') : __('No', 'wp-stripe-payments')); ?></span></td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="wp-sp-card">
                    <h2><?php esc_html_e('System Health', 'wp-stripe-payments'); ?></h2>
                    <?php if (empty($healthWarnings)) : ?>
                        <p><span class="wp-sp-status-badge is-good"><?php esc_html_e('Healthy', 'wp-stripe-payments'); ?></span></p>
                        <p><?php esc_html_e('No urgent warnings detected in current plugin configuration.', 'wp-stripe-payments'); ?></p>
                    <?php else : ?>
                        <p><span class="wp-sp-status-badge is-warning"><?php esc_html_e('Needs attention', 'wp-stripe-payments'); ?></span></p>
                        <ul class="wp-sp-inline-list">
                            <?php foreach ($healthWarnings as $warning) : ?>
                                <li><?php echo esc_html($warning); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <p style="margin-top:12px;"><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wp-stripe-payments-setup-guide')); ?>"><?php esc_html_e('Resolve via Setup Guide', 'wp-stripe-payments'); ?></a></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param string|int $value
     */
    private function renderMetricCard(string $label, $value): void
    {
        echo '<div class="wp-sp-card">';
        echo '<div class="wp-sp-kpi-value">' . (is_string($value) ? wp_kses_post($value) : esc_html((string) $value)) . '</div>';
        echo '<div class="wp-sp-kpi-label">' . esc_html($label) . '</div>';
        echo '</div>';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function calculateMrrEstimate(array $rows): float
    {
        $mrr = 0.0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $status = sanitize_key((string) ($row['status'] ?? ''));
            if (! in_array($status, ['active', 'trialing', 'past_due', 'unpaid'], true)) {
                continue;
            }

            if (! empty($row['cancel_at_period_end']) && $status !== 'past_due') {
                continue;
            }

            $amount = (float) ($row['plan_snapshot_price'] ?? 0);
            $interval = sanitize_key((string) ($row['billing_interval'] ?? 'month'));

            if ($amount <= 0) {
                continue;
            }

            switch ($interval) {
                case 'year':
                    $mrr += $amount / 12;
                    break;
                case 'week':
                    $mrr += $amount * 4.345;
                    break;
                case 'day':
                    $mrr += $amount * 30;
                    break;
                default:
                    $mrr += $amount;
            }
        }

        return round($mrr, 2);
    }
}
