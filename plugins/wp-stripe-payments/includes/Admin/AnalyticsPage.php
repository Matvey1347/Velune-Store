<?php

namespace WPStripePayments\Admin;

use WPStripePayments\Subscriptions\CustomerSubscriptionBillingHistoryRepository;
use WPStripePayments\Subscriptions\CustomerSubscriptionRepository;

class AnalyticsPage
{
    public function render(): void
    {
        if (! AdminContext::canManage()) {
            AdminContext::denyAccess();
        }

        $period = isset($_GET['period']) ? sanitize_key((string) wp_unslash($_GET['period'])) : '30d';
        $customFrom = isset($_GET['date_from']) ? sanitize_text_field((string) wp_unslash($_GET['date_from'])) : '';
        $customTo = isset($_GET['date_to']) ? sanitize_text_field((string) wp_unslash($_GET['date_to'])) : '';
        $planId = isset($_GET['plan_id']) ? (int) wp_unslash($_GET['plan_id']) : 0;
        $statusFilter = isset($_GET['status']) ? sanitize_key((string) wp_unslash($_GET['status'])) : '';
        $autoRenewFilter = isset($_GET['auto_renew']) ? sanitize_key((string) wp_unslash($_GET['auto_renew'])) : '';

        [$fromDate, $toDate] = $this->resolvePeriod($period, $customFrom, $customTo);

        $subscriptionRepo = new CustomerSubscriptionRepository();
        $billingRepo = new CustomerSubscriptionBillingHistoryRepository();

        $billingFilters = [
            'date_from' => $fromDate,
            'date_to' => $toDate,
            'plan_id' => $planId,
        ];

        $subscriptionFilters = [
            'plan_id' => $planId,
            'status' => $statusFilter,
            'auto_renew' => $autoRenewFilter,
        ];
        $filteredSubscriptions = $subscriptionRepo->findForAdmin($subscriptionFilters, 1, 2000)['rows'];

        $activeCount = 0;
        $newSubscriptions = 0;
        $canceledSubscriptions = 0;
        $fromTs = strtotime($fromDate . ' 00:00:00') ?: 0;
        $toTs = strtotime($toDate . ' 23:59:59') ?: time();
        foreach ($filteredSubscriptions as $row) {
            if (! is_array($row)) {
                continue;
            }
            $status = sanitize_key((string) ($row['status'] ?? ''));
            if ($status === 'active') {
                $activeCount++;
            }
            $createdTs = strtotime((string) ($row['created_at'] ?? ''));
            if ($createdTs !== false && $createdTs >= $fromTs && $createdTs <= $toTs) {
                $newSubscriptions++;
            }
            $updatedTs = strtotime((string) ($row['updated_at'] ?? ''));
            if ($status === 'canceled' && $updatedTs !== false && $updatedTs >= $fromTs && $updatedTs <= $toTs) {
                $canceledSubscriptions++;
            }
        }

        $billingSummary = $billingRepo->summarize($billingFilters);
        $renewals = (int) ($billingSummary['successful'] ?? 0);
        $failedPayments = (int) ($billingSummary['failed'] ?? 0);
        $estimatedRevenue = ((int) ($billingSummary['total_paid'] ?? 0)) / 100;

        $subscriptionSeries = $planId > 0 || $statusFilter !== '' || $autoRenewFilter !== ''
            ? []
            : $subscriptionRepo->getCreatedSeries($fromDate, $toDate);
        $revenueSeries = $billingRepo->getRevenueSeries($fromDate, $toDate, $planId);
        $plans = get_posts([
            'post_type' => 'wp_sp_sub_plan',
            'post_status' => 'publish',
            'numberposts' => 200,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(AdminContext::BRAND_NAME . ' ' . __('Analytics', 'wp-stripe-payments')); ?></h1>
            <p class="wp-sp-page-intro"><?php esc_html_e('Track subscription growth, churn, renewals, failed payments, and billed revenue from local synced data.', 'wp-stripe-payments'); ?></p>

            <div class="wp-sp-card" style="margin-bottom:12px;">
                <form method="get">
                    <input type="hidden" name="page" value="wp-stripe-payments-analytics" />
                    <div class="wp-sp-form-grid">
                        <div>
                            <label for="wp-sp-period"><strong><?php esc_html_e('Period', 'wp-stripe-payments'); ?></strong></label>
                            <select id="wp-sp-period" name="period">
                                <option value="7d" <?php selected($period, '7d'); ?>><?php esc_html_e('Last 7 days', 'wp-stripe-payments'); ?></option>
                                <option value="30d" <?php selected($period, '30d'); ?>><?php esc_html_e('Last 30 days', 'wp-stripe-payments'); ?></option>
                                <option value="90d" <?php selected($period, '90d'); ?>><?php esc_html_e('Last 90 days', 'wp-stripe-payments'); ?></option>
                                <option value="12m" <?php selected($period, '12m'); ?>><?php esc_html_e('Last 12 months', 'wp-stripe-payments'); ?></option>
                                <option value="custom" <?php selected($period, 'custom'); ?>><?php esc_html_e('Custom range', 'wp-stripe-payments'); ?></option>
                            </select>
                        </div>
                        <div>
                            <label for="wp-sp-plan"><strong><?php esc_html_e('Plan', 'wp-stripe-payments'); ?></strong></label>
                            <select id="wp-sp-plan" name="plan_id">
                                <option value="0"><?php esc_html_e('All plans', 'wp-stripe-payments'); ?></option>
                                <?php foreach ($plans as $planPost) : ?>
                                    <?php if (! $planPost instanceof \WP_Post) { continue; } ?>
                                    <option value="<?php echo esc_attr((string) $planPost->ID); ?>" <?php selected($planId, (int) $planPost->ID); ?>><?php echo esc_html($planPost->post_title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="wp-sp-status"><strong><?php esc_html_e('Status', 'wp-stripe-payments'); ?></strong></label>
                            <select id="wp-sp-status" name="status">
                                <option value=""><?php esc_html_e('All statuses', 'wp-stripe-payments'); ?></option>
                                <option value="active" <?php selected($statusFilter, 'active'); ?>><?php esc_html_e('Active', 'wp-stripe-payments'); ?></option>
                                <option value="trialing" <?php selected($statusFilter, 'trialing'); ?>><?php esc_html_e('Trialing', 'wp-stripe-payments'); ?></option>
                                <option value="past_due" <?php selected($statusFilter, 'past_due'); ?>><?php esc_html_e('Past due', 'wp-stripe-payments'); ?></option>
                                <option value="canceled" <?php selected($statusFilter, 'canceled'); ?>><?php esc_html_e('Canceled', 'wp-stripe-payments'); ?></option>
                            </select>
                        </div>
                        <div>
                            <label for="wp-sp-autorenew"><strong><?php esc_html_e('Auto-renew', 'wp-stripe-payments'); ?></strong></label>
                            <select id="wp-sp-autorenew" name="auto_renew">
                                <option value=""><?php esc_html_e('All', 'wp-stripe-payments'); ?></option>
                                <option value="on" <?php selected($autoRenewFilter, 'on'); ?>><?php esc_html_e('On', 'wp-stripe-payments'); ?></option>
                                <option value="off" <?php selected($autoRenewFilter, 'off'); ?>><?php esc_html_e('Off', 'wp-stripe-payments'); ?></option>
                            </select>
                        </div>
                        <div>
                            <label for="wp-sp-date-from"><strong><?php esc_html_e('Custom from', 'wp-stripe-payments'); ?></strong></label>
                            <input id="wp-sp-date-from" type="date" name="date_from" value="<?php echo esc_attr($customFrom); ?>" />
                        </div>
                        <div>
                            <label for="wp-sp-date-to"><strong><?php esc_html_e('Custom to', 'wp-stripe-payments'); ?></strong></label>
                            <input id="wp-sp-date-to" type="date" name="date_to" value="<?php echo esc_attr($customTo); ?>" />
                        </div>
                    </div>
                    <p><button type="submit" class="button button-primary"><?php esc_html_e('Apply', 'wp-stripe-payments'); ?></button></p>
                </form>
                <p class="description"><?php printf(esc_html__('Current range: %1$s to %2$s', 'wp-stripe-payments'), esc_html($fromDate), esc_html($toDate)); ?></p>
            </div>

            <div class="wp-sp-grid" style="margin-bottom:12px;">
                <div class="wp-sp-card"><div class="wp-sp-kpi-value"><?php echo esc_html((string) $activeCount); ?></div><div class="wp-sp-kpi-label"><?php esc_html_e('Active subscriptions', 'wp-stripe-payments'); ?></div></div>
                <div class="wp-sp-card"><div class="wp-sp-kpi-value"><?php echo esc_html((string) $newSubscriptions); ?></div><div class="wp-sp-kpi-label"><?php esc_html_e('New subscriptions in period', 'wp-stripe-payments'); ?></div></div>
                <div class="wp-sp-card"><div class="wp-sp-kpi-value"><?php echo esc_html((string) $canceledSubscriptions); ?></div><div class="wp-sp-kpi-label"><?php esc_html_e('Canceled subscriptions in period', 'wp-stripe-payments'); ?></div></div>
                <div class="wp-sp-card"><div class="wp-sp-kpi-value"><?php echo esc_html((string) $renewals); ?></div><div class="wp-sp-kpi-label"><?php esc_html_e('Renewals in period', 'wp-stripe-payments'); ?></div></div>
                <div class="wp-sp-card"><div class="wp-sp-kpi-value"><?php echo esc_html((string) $failedPayments); ?></div><div class="wp-sp-kpi-label"><?php esc_html_e('Failed payments in period', 'wp-stripe-payments'); ?></div></div>
                <div class="wp-sp-card"><div class="wp-sp-kpi-value"><?php echo wp_kses_post(wc_price($estimatedRevenue)); ?></div><div class="wp-sp-kpi-label"><?php esc_html_e('Estimated billed revenue', 'wp-stripe-payments'); ?></div></div>
            </div>

            <div class="wp-sp-grid-2">
                <div class="wp-sp-card">
                    <h2><?php esc_html_e('Subscriptions Over Time', 'wp-stripe-payments'); ?></h2>
                    <?php if (empty($subscriptionSeries)) : ?>
                        <p class="description"><?php esc_html_e('Trend table is shown when plan/status/auto-renew filters are not restricting the base timeline.', 'wp-stripe-payments'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead><tr><th><?php esc_html_e('Date', 'wp-stripe-payments'); ?></th><th><?php esc_html_e('New subscriptions', 'wp-stripe-payments'); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($subscriptionSeries as $point) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) $point['bucket']); ?></td>
                                    <td><?php echo esc_html((string) $point['total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="wp-sp-card">
                    <h2><?php esc_html_e('Revenue / Failures Over Time', 'wp-stripe-payments'); ?></h2>
                    <?php if (empty($revenueSeries)) : ?>
                        <p class="description"><?php esc_html_e('No billing events for selected period.', 'wp-stripe-payments'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead><tr><th><?php esc_html_e('Date', 'wp-stripe-payments'); ?></th><th><?php esc_html_e('Revenue', 'wp-stripe-payments'); ?></th><th><?php esc_html_e('Failures', 'wp-stripe-payments'); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($revenueSeries as $point) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) $point['bucket']); ?></td>
                                    <td><?php echo wp_kses_post(wc_price(((int) $point['revenue']) / 100)); ?></td>
                                    <td><?php echo esc_html((string) $point['failed']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePeriod(string $period, string $customFrom, string $customTo): array
    {
        $today = gmdate('Y-m-d');

        switch ($period) {
            case '7d':
                return [gmdate('Y-m-d', strtotime('-7 days')), $today];
            case '90d':
                return [gmdate('Y-m-d', strtotime('-90 days')), $today];
            case '12m':
                return [gmdate('Y-m-d', strtotime('-12 months')), $today];
            case 'custom':
                if ($customFrom !== '' && $customTo !== '') {
                    return [$customFrom, $customTo];
                }
                return [gmdate('Y-m-d', strtotime('-30 days')), $today];
            case '30d':
            default:
                return [gmdate('Y-m-d', strtotime('-30 days')), $today];
        }
    }
}
