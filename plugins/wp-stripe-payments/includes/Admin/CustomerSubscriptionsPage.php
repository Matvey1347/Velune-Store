<?php

namespace WPStripePayments\Admin;

use WPStripePayments\Subscriptions\CustomerSubscriptionBillingHistoryService;
use WPStripePayments\Subscriptions\CustomerSubscriptionService;

class CustomerSubscriptionsPage
{
    private const MANUAL_REVIEW_OPTION = 'wp_sp_manual_review_subscriptions';

    private CustomerSubscriptionService $customerSubscriptionService;

    private CustomerSubscriptionBillingHistoryService $billingHistoryService;

    public function __construct(
        ?CustomerSubscriptionService $customerSubscriptionService = null,
        ?CustomerSubscriptionBillingHistoryService $billingHistoryService = null
    ) {
        $this->customerSubscriptionService = $customerSubscriptionService ?? new CustomerSubscriptionService();
        $this->billingHistoryService = $billingHistoryService ?? new CustomerSubscriptionBillingHistoryService();
    }

    public function maybeHandleActions(): void
    {
        if (! is_admin()) {
            return;
        }

        if (! isset($_GET['page']) || $_GET['page'] !== 'wp-stripe-payments-customer-subscriptions') {
            return;
        }

        if (! isset($_POST['wp_sp_admin_action'])) {
            return;
        }

        if (! AdminContext::canManage()) {
            wp_die(esc_html__('Insufficient permissions.', 'wp-stripe-payments'));
        }

        $action = sanitize_key((string) wp_unslash($_POST['wp_sp_admin_action']));
        $subscriptionId = isset($_POST['subscription_id']) ? (int) wp_unslash($_POST['subscription_id']) : 0;
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field((string) wp_unslash($_POST['_wpnonce'])) : '';

        if (! wp_verify_nonce($nonce, 'wp_sp_admin_subscription_action_' . $action . '_' . $subscriptionId)) {
            wp_die(esc_html__('Security check failed.', 'wp-stripe-payments'));
        }

        $redirect = add_query_arg([
            'page' => 'wp-stripe-payments-customer-subscriptions',
            'tab' => isset($_POST['tab']) ? sanitize_key((string) wp_unslash($_POST['tab'])) : 'subscriptions',
        ], admin_url('admin.php'));

        if ($subscriptionId <= 0) {
            wp_safe_redirect(add_query_arg('error', rawurlencode(__('Invalid subscription record.', 'wp-stripe-payments')), $redirect));
            exit;
        }

        if ($action === 'cancel') {
            $result = $this->customerSubscriptionService->cancelAutoRenewForAdmin($subscriptionId);
            $this->redirectFromActionResult($result, __('Auto-renew will stop at period end.', 'wp-stripe-payments'), $redirect, $subscriptionId);
        }

        if ($action === 'resume') {
            $result = $this->customerSubscriptionService->resumeAutoRenewForAdmin($subscriptionId);
            $this->redirectFromActionResult($result, __('Auto-renew resumed for this subscription.', 'wp-stripe-payments'), $redirect, $subscriptionId);
        }

        if ($action === 'sync') {
            $result = $this->customerSubscriptionService->syncFromStripeByAdmin($subscriptionId);
            $this->redirectFromActionResult($result, __('Subscription synced from Stripe.', 'wp-stripe-payments'), $redirect, $subscriptionId);
        }

        if ($action === 'manual_review') {
            $this->markManualReview($subscriptionId);
            wp_safe_redirect(add_query_arg([
                'updated' => rawurlencode(__('Subscription marked for manual review.', 'wp-stripe-payments')),
                'subscription_id' => $subscriptionId,
            ], $redirect));
            exit;
        }

        if ($action === 'portal') {
            if (! Settings::isBillingPortalEnabled()) {
                wp_safe_redirect(add_query_arg('error', rawurlencode(__('Billing portal is disabled in settings.', 'wp-stripe-payments')), $redirect));
                exit;
            }

            $session = $this->customerSubscriptionService->createBillingPortalSessionForAdmin($subscriptionId, Settings::billingPortalReturnUrl());
            if (is_wp_error($session)) {
                wp_safe_redirect(add_query_arg('error', rawurlencode($session->get_error_message()), $redirect));
                exit;
            }

            $portalUrl = (string) ($session['url'] ?? '');
            if ($portalUrl === '' || ! $this->isStripePortalUrl($portalUrl)) {
                wp_safe_redirect(add_query_arg('error', rawurlencode(__('Invalid Stripe portal URL.', 'wp-stripe-payments')), $redirect));
                exit;
            }

            wp_redirect($portalUrl, 303, 'CommerceKit Stripe Billing');
            exit;
        }
    }

    public function render(): void
    {
        if (! AdminContext::canManage()) {
            AdminContext::denyAccess();
        }

        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'subscriptions';
        if (! in_array($tab, ['subscriptions', 'billing'], true)) {
            $tab = 'subscriptions';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html(AdminContext::BRAND_NAME . ' ' . __('Customer Subscriptions', 'wp-stripe-payments')) . '</h1>';
        echo '<p class="wp-sp-page-intro">' . esc_html__('Manage subscription lifecycle safely: search/filter, inspect details, sync from Stripe, control auto-renew, and review billing events.', 'wp-stripe-payments') . '</p>';

        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html((string) wp_unslash($_GET['updated'])) . '</p></div>';
        }

        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html((string) wp_unslash($_GET['error'])) . '</p></div>';
        }

        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-stripe-payments-customer-subscriptions&tab=subscriptions')) . '" class="nav-tab ' . ($tab === 'subscriptions' ? 'nav-tab-active' : '') . '">' . esc_html__('Subscriptions', 'wp-stripe-payments') . '</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wp-stripe-payments-customer-subscriptions&tab=billing')) . '" class="nav-tab ' . ($tab === 'billing' ? 'nav-tab-active' : '') . '">' . esc_html__('Billing History', 'wp-stripe-payments') . '</a>';
        echo '</h2>';

        if ($tab === 'billing') {
            $this->renderBillingHistoryTab();
        } else {
            $this->renderSubscriptionsTab();
        }

        echo '</div>';
    }

    private function renderSubscriptionsTab(): void
    {
        $view = isset($_GET['view']) ? sanitize_key((string) wp_unslash($_GET['view'])) : '';
        $selectedSubscriptionId = isset($_GET['subscription_id']) ? (int) wp_unslash($_GET['subscription_id']) : 0;

        if ($view === 'details' && $selectedSubscriptionId > 0) {
            $this->renderDetailsView($selectedSubscriptionId);
            return;
        }

        $filters = [
            's' => isset($_GET['s']) ? sanitize_text_field((string) wp_unslash($_GET['s'])) : '',
            'status' => isset($_GET['status']) ? sanitize_key((string) wp_unslash($_GET['status'])) : '',
            'auto_renew' => isset($_GET['auto_renew']) ? sanitize_key((string) wp_unslash($_GET['auto_renew'])) : '',
            'plan_id' => isset($_GET['plan_id']) ? (int) wp_unslash($_GET['plan_id']) : 0,
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field((string) wp_unslash($_GET['date_from'])) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field((string) wp_unslash($_GET['date_to'])) : '',
        ];

        $paged = isset($_GET['paged']) ? max(1, (int) wp_unslash($_GET['paged'])) : 1;
        $orderby = isset($_GET['orderby']) ? sanitize_key((string) wp_unslash($_GET['orderby'])) : 'updated_at';
        $order = isset($_GET['order']) ? strtoupper(sanitize_key((string) wp_unslash($_GET['order']))) : 'DESC';

        $perPage = 20;
        $result = $this->customerSubscriptionService->listForAdminPaged($filters, $paged, $perPage, $orderby, $order);
        $rows = $result['rows'];
        $total = $result['total'];

        $manualReviewMap = $this->getManualReviewMap();

        $plans = get_posts([
            'post_type' => 'wp_sp_sub_plan',
            'post_status' => 'publish',
            'numberposts' => 200,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        echo '<div class="wp-sp-card" style="margin-top:12px;margin-bottom:12px;">';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="wp-stripe-payments-customer-subscriptions" />';
        echo '<input type="hidden" name="tab" value="subscriptions" />';
        echo '<div class="wp-sp-form-grid">';
        echo '<div><label for="wp-sp-sub-s"><strong>' . esc_html__('Search', 'wp-stripe-payments') . '</strong></label><input id="wp-sp-sub-s" type="search" name="s" class="regular-text" value="' . esc_attr((string) $filters['s']) . '" placeholder="email, stripe id, plan" /></div>';
        echo '<div><label for="wp-sp-sub-status"><strong>' . esc_html__('Status', 'wp-stripe-payments') . '</strong></label><select id="wp-sp-sub-status" name="status">' . $this->statusOptions((string) $filters['status']) . '</select></div>';
        echo '<div><label for="wp-sp-sub-autorenew"><strong>' . esc_html__('Auto-renew', 'wp-stripe-payments') . '</strong></label><select id="wp-sp-sub-autorenew" name="auto_renew">' . $this->autoRenewOptions((string) $filters['auto_renew']) . '</select></div>';
        echo '<div><label for="wp-sp-sub-plan"><strong>' . esc_html__('Plan', 'wp-stripe-payments') . '</strong></label><select id="wp-sp-sub-plan" name="plan_id"><option value="0">' . esc_html__('All plans', 'wp-stripe-payments') . '</option>';
        foreach ($plans as $planPost) {
            if (! $planPost instanceof \WP_Post) {
                continue;
            }
            echo '<option value="' . esc_attr((string) $planPost->ID) . '" ' . selected((int) $filters['plan_id'], (int) $planPost->ID, false) . '>' . esc_html($planPost->post_title) . '</option>';
        }
        echo '</select></div>';
        echo '<div><label for="wp-sp-sub-from"><strong>' . esc_html__('Updated from', 'wp-stripe-payments') . '</strong></label><input id="wp-sp-sub-from" type="date" name="date_from" value="' . esc_attr((string) $filters['date_from']) . '" /></div>';
        echo '<div><label for="wp-sp-sub-to"><strong>' . esc_html__('Updated to', 'wp-stripe-payments') . '</strong></label><input id="wp-sp-sub-to" type="date" name="date_to" value="' . esc_attr((string) $filters['date_to']) . '" /></div>';
        echo '</div>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Apply Filters', 'wp-stripe-payments') . '</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=wp-stripe-payments-customer-subscriptions&tab=subscriptions')) . '">' . esc_html__('Reset', 'wp-stripe-payments') . '</a></p>';
        echo '</form>';
        echo '</div>';

        if (empty($rows)) {
            echo '<div class="wp-sp-empty"><p>' . esc_html__('No subscription records found for current filters.', 'wp-stripe-payments') . '</p></div>';
            return;
        }

        $subscriptionIds = [];
        foreach ($rows as $row) {
            $subscriptionId = sanitize_text_field((string) ($row['stripe_subscription_id'] ?? ''));
            if ($subscriptionId === '') {
                continue;
            }

            $subscriptionIds[] = $subscriptionId;
        }
        $amountSpentBySubscriptionId = $this->billingHistoryService->getTotalPaidBySubscriptionIds($subscriptionIds);

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Email', 'wp-stripe-payments') . '</th>';
        echo '<th>' . esc_html__('Plan', 'wp-stripe-payments') . '</th>';
        echo '<th>' . esc_html__('Amount spent', 'wp-stripe-payments') . '</th>';
        echo '<th>' . esc_html__('Status', 'wp-stripe-payments') . '</th>';
        echo '<th>' . esc_html__('Auto-renew', 'wp-stripe-payments') . '</th>';
        echo '<th>' . esc_html__('Next Billing', 'wp-stripe-payments') . '</th>';
        echo '<th>' . esc_html__('Stripe IDs', 'wp-stripe-payments') . '</th>';
        echo '<th>' . esc_html__('Updated', 'wp-stripe-payments') . '</th>';
        echo '<th>' . esc_html__('Actions', 'wp-stripe-payments') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $rowId = (int) ($row['id'] ?? 0);
            $status = sanitize_key((string) ($row['status'] ?? ''));
            $planName = (string) ($row['plan_snapshot_title'] ?? '');
            if ($planName === '' && (int) ($row['plan_id'] ?? 0) > 0) {
                $planName = (string) get_the_title((int) ($row['plan_id'] ?? 0));
            }
            if ($planName === '') {
                $planName = __('Unknown', 'wp-stripe-payments');
            }

            $stripeSubId = (string) ($row['stripe_subscription_id'] ?? '');
            $amountSpent = 0;
            if ($stripeSubId !== '' && isset($amountSpentBySubscriptionId[$stripeSubId])) {
                $amountSpent = (int) $amountSpentBySubscriptionId[$stripeSubId];
            }

            $autoRenew = ! empty($row['cancel_at_period_end']) || $status === 'canceled' ? __('Off', 'wp-stripe-payments') : __('On', 'wp-stripe-payments');
            $nextBilling = (string) ($row['current_period_end'] ?? $row['next_billing_date'] ?? '');
            $updated = (string) ($row['updated_at'] ?? '');

            $stripeCustomerId = (string) ($row['stripe_customer_id'] ?? '');
            $stripeDashboardBase = Settings::isTestMode() ? 'https://dashboard.stripe.com/test' : 'https://dashboard.stripe.com';
            $detailsUrl = add_query_arg([
                'page' => 'wp-stripe-payments-customer-subscriptions',
                'tab' => 'subscriptions',
                'view' => 'details',
                'subscription_id' => $rowId,
            ], admin_url('admin.php'));
            $billingHistoryUrl = add_query_arg([
                'page' => 'wp-stripe-payments-customer-subscriptions',
                'tab' => 'billing',
                'subscription_id' => $stripeSubId,
            ], admin_url('admin.php'));

            echo '<tr class="wp-sp-clickable-row" role="link" tabindex="0" data-row-href="' . esc_url($detailsUrl) . '">';
            echo '<td>' . esc_html((string) ($row['customer_email'] ?? ''));
            if (isset($manualReviewMap[$rowId])) {
                echo '<br /><span class="wp-sp-status-badge is-warning" style="margin-top:4px;">' . esc_html__('Manual review', 'wp-stripe-payments') . '</span>';
            }
            echo '</td>';
            echo '<td>' . esc_html($planName) . '</td>';
            echo '<td>' . wp_kses_post(wc_price($amountSpent / 100)) . '</td>';
            echo '<td><span class="wp-sp-status-badge status-' . esc_attr($status) . '">' . esc_html($this->statusLabel($status)) . '</span></td>';
            echo '<td>' . esc_html($autoRenew) . '</td>';
            echo '<td>' . esc_html($nextBilling !== '' ? $nextBilling : __('N/A', 'wp-stripe-payments')) . '</td>';
            echo '<td>';
            if ($stripeSubId !== '') {
                echo '<div><code>' . esc_html($stripeSubId) . '</code> <a href="' . esc_url($stripeDashboardBase . '/subscriptions/' . rawurlencode($stripeSubId)) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Stripe', 'wp-stripe-payments') . '</a></div>';
            }
            if ($stripeCustomerId !== '') {
                echo '<div><code>' . esc_html($stripeCustomerId) . '</code> <a href="' . esc_url($stripeDashboardBase . '/customers/' . rawurlencode($stripeCustomerId)) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Stripe', 'wp-stripe-payments') . '</a></div>';
            }
            echo '</td>';
            echo '<td>' . esc_html($updated) . '</td>';
            echo '<td>';
            echo '<a class="button button-small" href="' . esc_url($billingHistoryUrl) . '">' . esc_html__('Billing History', 'wp-stripe-payments') . '</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        $this->renderPagination($total, $paged, $perPage, [
            'page' => 'wp-stripe-payments-customer-subscriptions',
            'tab' => 'subscriptions',
            's' => (string) $filters['s'],
            'status' => (string) $filters['status'],
            'auto_renew' => (string) $filters['auto_renew'],
            'plan_id' => (string) ((int) $filters['plan_id']),
            'date_from' => (string) $filters['date_from'],
            'date_to' => (string) $filters['date_to'],
            'orderby' => $orderby,
            'order' => $order,
        ]);
    }

    private function renderDetailsView(int $subscriptionId): void
    {
        $row = $this->customerSubscriptionService->findByIdForAdmin($subscriptionId);

        if (! is_array($row)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Subscription not found.', 'wp-stripe-payments') . '</p></div>';
            return;
        }

        $status = sanitize_key((string) ($row['status'] ?? ''));
        $amount = isset($row['plan_snapshot_price']) ? (float) $row['plan_snapshot_price'] : 0.0;
        $stripeSubId = (string) ($row['stripe_subscription_id'] ?? '');
        $stripeCustomerId = (string) ($row['stripe_customer_id'] ?? '');
        $stripeDashboardBase = Settings::isTestMode() ? 'https://dashboard.stripe.com/test' : 'https://dashboard.stripe.com';

        $historyResult = $this->billingHistoryService->listForAdmin([
            'subscription_id' => $stripeSubId,
        ], 1, 100);
        $historyRows = $historyResult['rows'];

        $summary = $this->billingHistoryService->summarizeForAdmin([
            'subscription_id' => $stripeSubId,
        ]);

        $manualReviewMap = $this->getManualReviewMap();
        $manualReviewAt = isset($manualReviewMap[$subscriptionId]) ? (string) $manualReviewMap[$subscriptionId] : '';

        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=wp-stripe-payments-customer-subscriptions&tab=subscriptions')) . '">' . esc_html__('Back to Subscriptions', 'wp-stripe-payments') . '</a></p>';

        echo '<div class="wp-sp-grid-2">';

        echo '<div class="wp-sp-card">';
        echo '<h2>' . esc_html__('Subscription Details', 'wp-stripe-payments') . '</h2>';
        echo '<table class="widefat striped"><tbody>';
        $this->detailRow(__('Customer Email', 'wp-stripe-payments'), (string) ($row['customer_email'] ?? ''));
        $this->detailRow(__('WP User ID', 'wp-stripe-payments'), (string) ((int) ($row['user_id'] ?? 0)));
        $this->detailRow(__('Plan', 'wp-stripe-payments'), (string) ($row['plan_snapshot_title'] ?? get_the_title((int) ($row['plan_id'] ?? 0))));
        $this->detailRow(__('Amount', 'wp-stripe-payments'), wp_kses_post(wc_price($amount)), true);
        $this->detailRow(__('Currency', 'wp-stripe-payments'), get_woocommerce_currency());
        $this->detailRow(__('Interval', 'wp-stripe-payments'), (string) ($row['billing_interval'] ?? ''));
        $this->detailRow(__('Status', 'wp-stripe-payments'), '<span class="wp-sp-status-badge status-' . esc_attr($status) . '">' . esc_html($this->statusLabel($status)) . '</span>', true);
        $this->detailRow(__('Auto-renew', 'wp-stripe-payments'), ! empty($row['cancel_at_period_end']) || $status === 'canceled' ? __('Off', 'wp-stripe-payments') : __('On', 'wp-stripe-payments'));
        $this->detailRow(__('Current period start', 'wp-stripe-payments'), (string) ($row['current_period_start'] ?? ''));
        $this->detailRow(__('Current period end', 'wp-stripe-payments'), (string) ($row['current_period_end'] ?? ''));
        $this->detailRow(__('Next billing date', 'wp-stripe-payments'), (string) ($row['next_billing_date'] ?? ''));
        $this->detailRow(__('Stripe customer ID', 'wp-stripe-payments'), '<code>' . esc_html($stripeCustomerId) . '</code>', true);
        $this->detailRow(__('Stripe subscription ID', 'wp-stripe-payments'), '<code>' . esc_html($stripeSubId) . '</code>', true);
        $this->detailRow(__('Checkout session ID', 'wp-stripe-payments'), '<code>' . esc_html((string) ($row['last_checkout_session_id'] ?? '')) . '</code>', true);
        $this->detailRow(__('Updated at', 'wp-stripe-payments'), (string) ($row['updated_at'] ?? ''));
        $this->detailRow(__('Internal note', 'wp-stripe-payments'), $manualReviewAt !== '' ? sprintf(esc_html__('Marked for manual review on %s', 'wp-stripe-payments'), esc_html($manualReviewAt)) : __('None', 'wp-stripe-payments'));
        echo '</tbody></table>';

        echo '<h3 style="margin-top:16px;">' . esc_html__('Billing Summary', 'wp-stripe-payments') . '</h3>';
        echo '<div class="wp-sp-grid">';
        echo '<div class="wp-sp-card"><div class="wp-sp-kpi-value">' . esc_html((string) (int) ($summary['successful'] ?? 0)) . '</div><div class="wp-sp-kpi-label">' . esc_html__('Successful payments', 'wp-stripe-payments') . '</div></div>';
        echo '<div class="wp-sp-card"><div class="wp-sp-kpi-value">' . esc_html((string) (int) ($summary['failed'] ?? 0)) . '</div><div class="wp-sp-kpi-label">' . esc_html__('Failed payments', 'wp-stripe-payments') . '</div></div>';
        echo '<div class="wp-sp-card"><div class="wp-sp-kpi-value">' . esc_html((string) (int) ($summary['refunded'] ?? 0)) . '</div><div class="wp-sp-kpi-label">' . esc_html__('Refunded', 'wp-stripe-payments') . '</div></div>';
        echo '<div class="wp-sp-card"><div class="wp-sp-kpi-value">' . wp_kses_post(wc_price(((int) ($summary['total_paid'] ?? 0)) / 100)) . '</div><div class="wp-sp-kpi-label">' . esc_html__('Total billed', 'wp-stripe-payments') . '</div></div>';
        echo '</div>';

        echo '<h3 style="margin-top:16px;">' . esc_html__('Billing History', 'wp-stripe-payments') . '</h3>';
        if (empty($historyRows)) {
            echo '<p class="description">' . esc_html__('No billing records yet for this subscription.', 'wp-stripe-payments') . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Date', 'wp-stripe-payments') . '</th><th>' . esc_html__('Amount', 'wp-stripe-payments') . '</th><th>' . esc_html__('Status', 'wp-stripe-payments') . '</th><th>' . esc_html__('Invoice', 'wp-stripe-payments') . '</th><th>' . esc_html__('Description', 'wp-stripe-payments') . '</th></tr></thead><tbody>';
            foreach ($historyRows as $item) {
                $invoiceRef = (string) ($item['invoice_number'] ?? '');
                if ($invoiceRef === '') {
                    $invoiceRef = (string) ($item['invoice_id'] ?? '');
                }
                echo '<tr>';
                echo '<td>' . esc_html((string) ($item['date_label'] ?? '')) . '</td>';
                echo '<td>' . wp_kses_post((string) ($item['amount_label'] ?? '')) . '</td>';
                echo '<td><span class="wp-sp-status-badge status-' . esc_attr(sanitize_key((string) ($item['status'] ?? 'pending'))) . '">' . esc_html((string) ($item['status_label'] ?? '')) . '</span></td>';
                echo '<td>' . esc_html($invoiceRef) . '</td>';
                echo '<td>' . esc_html((string) ($item['period_label'] ?? '')) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        echo '<div class="wp-sp-card">';
        echo '<h2>' . esc_html__('Admin Actions', 'wp-stripe-payments') . '</h2>';

        echo '<p>' . esc_html__('Supported safe actions:', 'wp-stripe-payments') . '</p>';
        echo '<ul class="wp-sp-inline-list">';
        echo '<li>' . esc_html__('Cancel at period end', 'wp-stripe-payments') . '</li>';
        echo '<li>' . esc_html__('Resume auto-renew', 'wp-stripe-payments') . '</li>';
        echo '<li>' . esc_html__('Sync latest subscription from Stripe', 'wp-stripe-payments') . '</li>';
        echo '<li>' . esc_html__('Open Stripe billing portal', 'wp-stripe-payments') . '</li>';
        echo '<li>' . esc_html__('Mark for manual review', 'wp-stripe-payments') . '</li>';
        echo '</ul>';

        echo '<p class="description">' . esc_html__('Manual extension/reactivation is intentionally not automated here to avoid unsafe Stripe-side mutations without explicit workflow controls.', 'wp-stripe-payments') . '</p>';

        echo '<div class="wp-sp-actions">';
        $this->renderActionForm('cancel', $subscriptionId, __('Cancel at period end', 'wp-stripe-payments'), true);
        $this->renderActionForm('resume', $subscriptionId, __('Resume auto-renew', 'wp-stripe-payments'));
        $this->renderActionForm('sync', $subscriptionId, __('Sync from Stripe', 'wp-stripe-payments'));
        if (Settings::isBillingPortalEnabled()) {
            $this->renderActionForm('portal', $subscriptionId, __('Open Billing Portal', 'wp-stripe-payments'));
        }
        $this->renderActionForm('manual_review', $subscriptionId, __('Mark for manual review', 'wp-stripe-payments'));
        echo '</div>';

        if ($stripeSubId !== '') {
            echo '<p><a class="button" href="' . esc_url($stripeDashboardBase . '/subscriptions/' . rawurlencode($stripeSubId)) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Open in Stripe (subscription)', 'wp-stripe-payments') . '</a></p>';
        }
        if ($stripeCustomerId !== '') {
            echo '<p><a class="button" href="' . esc_url($stripeDashboardBase . '/customers/' . rawurlencode($stripeCustomerId)) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Open in Stripe (customer)', 'wp-stripe-payments') . '</a></p>';
        }

        echo '</div>';

        echo '</div>';
    }

    private function renderBillingHistoryTab(): void
    {
        $filters = [
            'status' => isset($_GET['status']) ? sanitize_key((string) wp_unslash($_GET['status'])) : '',
            'customer' => isset($_GET['customer']) ? sanitize_text_field((string) wp_unslash($_GET['customer'])) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field((string) wp_unslash($_GET['date_from'])) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field((string) wp_unslash($_GET['date_to'])) : '',
            'subscription_id' => isset($_GET['subscription_id']) ? sanitize_text_field((string) wp_unslash($_GET['subscription_id'])) : '',
            'plan_id' => isset($_GET['plan_id']) ? (int) wp_unslash($_GET['plan_id']) : 0,
        ];

        $paged = isset($_GET['paged']) ? max(1, (int) wp_unslash($_GET['paged'])) : 1;
        $perPage = 25;
        $result = $this->billingHistoryService->listForAdmin($filters, $paged, $perPage);
        $summary = $this->billingHistoryService->summarizeForAdmin($filters);

        echo '<div class="wp-sp-grid" style="margin-top:12px; margin-bottom:12px;">';
        echo '<div class="wp-sp-card"><div class="wp-sp-kpi-value">' . esc_html((string) ((int) ($summary['successful'] ?? 0))) . '</div><div class="wp-sp-kpi-label">' . esc_html__('Total successful payments', 'wp-stripe-payments') . '</div></div>';
        echo '<div class="wp-sp-card"><div class="wp-sp-kpi-value">' . esc_html((string) ((int) ($summary['failed'] ?? 0))) . '</div><div class="wp-sp-kpi-label">' . esc_html__('Total failed payments', 'wp-stripe-payments') . '</div></div>';
        echo '<div class="wp-sp-card"><div class="wp-sp-kpi-value">' . esc_html((string) ((int) ($summary['refunded'] ?? 0))) . '</div><div class="wp-sp-kpi-label">' . esc_html__('Total refunded', 'wp-stripe-payments') . '</div></div>';
        echo '<div class="wp-sp-card"><div class="wp-sp-kpi-value">' . wp_kses_post(wc_price(((int) ($summary['total_paid'] ?? 0)) / 100)) . '</div><div class="wp-sp-kpi-label">' . esc_html__('Total billed amount', 'wp-stripe-payments') . '</div></div>';
        echo '</div>';

        $plans = get_posts([
            'post_type' => 'wp_sp_sub_plan',
            'post_status' => 'publish',
            'numberposts' => 200,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        echo '<div class="wp-sp-card" style="margin-bottom:12px;">';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="wp-stripe-payments-customer-subscriptions" />';
        echo '<input type="hidden" name="tab" value="billing" />';
        echo '<div class="wp-sp-form-grid">';
        echo '<div><label for="wp-sp-billing-status"><strong>' . esc_html__('Status', 'wp-stripe-payments') . '</strong></label><select id="wp-sp-billing-status" name="status">' . $this->billingStatusOptions((string) $filters['status']) . '</select></div>';
        echo '<div><label for="wp-sp-billing-customer"><strong>' . esc_html__('Customer', 'wp-stripe-payments') . '</strong></label><input id="wp-sp-billing-customer" type="text" name="customer" value="' . esc_attr((string) $filters['customer']) . '" placeholder="email or customer id" /></div>';
        echo '<div><label for="wp-sp-billing-sub"><strong>' . esc_html__('Subscription ID', 'wp-stripe-payments') . '</strong></label><input id="wp-sp-billing-sub" type="text" name="subscription_id" value="' . esc_attr((string) $filters['subscription_id']) . '" /></div>';
        echo '<div><label for="wp-sp-billing-plan"><strong>' . esc_html__('Plan', 'wp-stripe-payments') . '</strong></label><select id="wp-sp-billing-plan" name="plan_id"><option value="0">' . esc_html__('All plans', 'wp-stripe-payments') . '</option>';
        foreach ($plans as $planPost) {
            if (! $planPost instanceof \WP_Post) {
                continue;
            }
            echo '<option value="' . esc_attr((string) $planPost->ID) . '" ' . selected((int) $filters['plan_id'], (int) $planPost->ID, false) . '>' . esc_html($planPost->post_title) . '</option>';
        }
        echo '</select></div>';
        echo '<div><label for="wp-sp-billing-from"><strong>' . esc_html__('Date from', 'wp-stripe-payments') . '</strong></label><input id="wp-sp-billing-from" type="date" name="date_from" value="' . esc_attr((string) $filters['date_from']) . '" /></div>';
        echo '<div><label for="wp-sp-billing-to"><strong>' . esc_html__('Date to', 'wp-stripe-payments') . '</strong></label><input id="wp-sp-billing-to" type="date" name="date_to" value="' . esc_attr((string) $filters['date_to']) . '" /></div>';
        echo '</div>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Apply Filters', 'wp-stripe-payments') . '</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=wp-stripe-payments-customer-subscriptions&tab=billing')) . '">' . esc_html__('Reset', 'wp-stripe-payments') . '</a></p>';
        echo '</form>';
        echo '</div>';

        if (empty($result['rows'])) {
            echo '<div class="wp-sp-empty"><p>' . esc_html__('No billing history records found for current filters.', 'wp-stripe-payments') . '</p></div>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr><th>' . esc_html__('Date', 'wp-stripe-payments') . '</th><th>' . esc_html__('Amount', 'wp-stripe-payments') . '</th><th>' . esc_html__('Currency', 'wp-stripe-payments') . '</th><th>' . esc_html__('Status', 'wp-stripe-payments') . '</th><th>' . esc_html__('Invoice / Payment', 'wp-stripe-payments') . '</th><th>' . esc_html__('Description', 'wp-stripe-payments') . '</th></tr></thead><tbody>';
        foreach ($result['rows'] as $row) {
            $invoiceRef = (string) ($row['invoice_number'] ?? '');
            if ($invoiceRef === '') {
                $invoiceRef = (string) ($row['invoice_id'] ?? '');
            }

            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['date_label'] ?? '')) . '</td>';
            echo '<td>' . wp_kses_post((string) ($row['amount_label'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['currency'] ?? '')) . '</td>';
            echo '<td><span class="wp-sp-status-badge status-' . esc_attr(sanitize_key((string) ($row['status'] ?? 'pending'))) . '">' . esc_html((string) ($row['status_label'] ?? '')) . '</span></td>';
            echo '<td><code>' . esc_html($invoiceRef) . '</code></td>';
            echo '<td>' . esc_html((string) ($row['period_label'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        $this->renderPagination((int) $result['total'], $paged, $perPage, [
            'page' => 'wp-stripe-payments-customer-subscriptions',
            'tab' => 'billing',
            'status' => (string) $filters['status'],
            'customer' => (string) $filters['customer'],
            'subscription_id' => (string) $filters['subscription_id'],
            'plan_id' => (string) ((int) $filters['plan_id']),
            'date_from' => (string) $filters['date_from'],
            'date_to' => (string) $filters['date_to'],
        ]);
    }

    private function renderActionForm(string $action, int $subscriptionId, string $label, bool $confirm = false): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=wp-stripe-payments-customer-subscriptions')) . '" style="display:inline-block;margin-right:8px;">';
        echo '<input type="hidden" name="page" value="wp-stripe-payments-customer-subscriptions" />';
        echo '<input type="hidden" name="tab" value="subscriptions" />';
        echo '<input type="hidden" name="wp_sp_admin_action" value="' . esc_attr($action) . '" />';
        echo '<input type="hidden" name="subscription_id" value="' . esc_attr((string) $subscriptionId) . '" />';
        wp_nonce_field('wp_sp_admin_subscription_action_' . $action . '_' . $subscriptionId);
        $onclick = $confirm ? ' onclick="return confirm(\'' . esc_js(__('Are you sure you want to proceed?', 'wp-stripe-payments')) . '\');"' : '';
        echo '<button type="submit" class="button"' . $onclick . '>' . esc_html($label) . '</button>';
        echo '</form>';
    }

    /**
     * @param bool $allowHtml
     */
    private function detailRow(string $label, string $value, bool $allowHtml = false): void
    {
        echo '<tr><td><strong>' . esc_html($label) . '</strong></td><td>' . ($allowHtml ? $value : esc_html($value)) . '</td></tr>';
    }

    /**
     * @param bool|\WP_Error $result
     */
    private function redirectFromActionResult($result, string $successMessage, string $redirect, int $subscriptionId): void
    {
        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg([
                'error' => rawurlencode($result->get_error_message()),
                'subscription_id' => $subscriptionId,
            ], $redirect));
            exit;
        }

        wp_safe_redirect(add_query_arg([
            'updated' => rawurlencode($successMessage),
            'subscription_id' => $subscriptionId,
        ], $redirect));
        exit;
    }

    private function markManualReview(int $subscriptionId): void
    {
        $map = $this->getManualReviewMap();
        $map[$subscriptionId] = gmdate('Y-m-d H:i:s');
        update_option(self::MANUAL_REVIEW_OPTION, $map, false);
    }

    /**
     * @return array<int, string>
     */
    private function getManualReviewMap(): array
    {
        $raw = get_option(self::MANUAL_REVIEW_OPTION, []);
        if (! is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $key => $value) {
            $id = (int) $key;
            if ($id <= 0) {
                continue;
            }

            $result[$id] = sanitize_text_field((string) $value);
        }

        return $result;
    }

    private function renderPagination(int $total, int $paged, int $perPage, array $baseQuery): void
    {
        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages <= 1) {
            return;
        }

        echo '<div class="tablenav"><div class="tablenav-pages">';

        for ($page = 1; $page <= $totalPages; $page++) {
            $url = add_query_arg(array_merge($baseQuery, ['paged' => $page]), admin_url('admin.php'));
            if ($page === $paged) {
                echo '<span class="button button-primary" style="margin-right:4px;">' . esc_html((string) $page) . '</span>';
            } else {
                echo '<a class="button" style="margin-right:4px;" href="' . esc_url($url) . '">' . esc_html((string) $page) . '</a>';
            }
        }

        echo '</div></div>';
    }

    private function statusOptions(string $selected): string
    {
        $statuses = [
            '' => __('All statuses', 'wp-stripe-payments'),
            'active' => __('Active', 'wp-stripe-payments'),
            'trialing' => __('Trialing', 'wp-stripe-payments'),
            'past_due' => __('Past due', 'wp-stripe-payments'),
            'canceled' => __('Canceled', 'wp-stripe-payments'),
            'incomplete' => __('Incomplete', 'wp-stripe-payments'),
            'unpaid' => __('Unpaid', 'wp-stripe-payments'),
        ];

        $output = '';
        foreach ($statuses as $value => $label) {
            $output .= '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }

        return $output;
    }

    private function autoRenewOptions(string $selected): string
    {
        $options = [
            '' => __('All', 'wp-stripe-payments'),
            'on' => __('On', 'wp-stripe-payments'),
            'off' => __('Off', 'wp-stripe-payments'),
        ];

        $output = '';
        foreach ($options as $value => $label) {
            $output .= '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }

        return $output;
    }

    private function billingStatusOptions(string $selected): string
    {
        $statuses = [
            '' => __('All statuses', 'wp-stripe-payments'),
            'paid' => __('Paid', 'wp-stripe-payments'),
            'failed' => __('Failed', 'wp-stripe-payments'),
            'open' => __('Open', 'wp-stripe-payments'),
            'refunded' => __('Refunded', 'wp-stripe-payments'),
            'void' => __('Void', 'wp-stripe-payments'),
            'pending' => __('Pending', 'wp-stripe-payments'),
        ];

        $output = '';
        foreach ($statuses as $value => $label) {
            $output .= '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }

        return $output;
    }

    private function statusLabel(string $status): string
    {
        $labels = [
            'active' => __('Active', 'wp-stripe-payments'),
            'trialing' => __('Trialing', 'wp-stripe-payments'),
            'incomplete' => __('Incomplete', 'wp-stripe-payments'),
            'incomplete_expired' => __('Incomplete expired', 'wp-stripe-payments'),
            'past_due' => __('Past due', 'wp-stripe-payments'),
            'canceled' => __('Canceled', 'wp-stripe-payments'),
            'unpaid' => __('Unpaid', 'wp-stripe-payments'),
        ];

        return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    private function isStripePortalUrl(string $url): bool
    {
        if (! wp_http_validate_url($url)) {
            return false;
        }

        $parts = wp_parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        return $scheme === 'https' && $host === 'billing.stripe.com';
    }
}
