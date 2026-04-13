<?php

namespace WPStripePayments\Subscriptions;

use WPStripePayments\Stripe\Client;
use WPStripePayments\Utils\Logger;

class CustomerSubscriptionBillingHistoryService
{
    private const ALLOWED_STATUSES = [
        'paid',
        'open',
        'failed',
        'refunded',
        'void',
        'pending',
    ];

    private CustomerSubscriptionBillingHistoryRepository $billingHistoryRepository;

    private CustomerSubscriptionRepository $customerSubscriptionRepository;

    private Client $stripeClient;

    private Logger $logger;

    public function __construct(
        ?CustomerSubscriptionBillingHistoryRepository $billingHistoryRepository = null,
        ?CustomerSubscriptionRepository $customerSubscriptionRepository = null,
        ?Client $stripeClient = null,
        ?Logger $logger = null
    ) {
        $this->billingHistoryRepository = $billingHistoryRepository ?? new CustomerSubscriptionBillingHistoryRepository();
        $this->customerSubscriptionRepository = $customerSubscriptionRepository ?? new CustomerSubscriptionRepository();
        $this->stripeClient = $stripeClient ?? new Client();
        $this->logger = $logger ?? new Logger();
    }

    /**
     * @param array<string, mixed> $invoiceObject
     */
    public function syncFromInvoiceObject(array $invoiceObject, string $statusOverride = ''): bool
    {
        $invoiceId = sanitize_text_field((string) ($invoiceObject['id'] ?? ''));
        if ($invoiceId === '') {
            return false;
        }

        $subscriptionId = sanitize_text_field((string) ($invoiceObject['subscription'] ?? ''));
        $subscriptionRow = $subscriptionId !== ''
            ? $this->customerSubscriptionRepository->findOneByStripeSubscriptionId($subscriptionId)
            : null;

        $customerEmail = sanitize_email((string) ($invoiceObject['customer_email'] ?? (is_array($subscriptionRow) ? ($subscriptionRow['customer_email'] ?? '') : '')));
        $payload = [
            'user_id' => is_array($subscriptionRow) && (int) ($subscriptionRow['user_id'] ?? 0) > 0
                ? (int) $subscriptionRow['user_id']
                : null,
            'customer_email' => $customerEmail,
            'stripe_customer_id' => sanitize_text_field((string) ($invoiceObject['customer'] ?? (is_array($subscriptionRow) ? ($subscriptionRow['stripe_customer_id'] ?? '') : ''))),
            'stripe_subscription_id' => $subscriptionId,
            'stripe_invoice_id' => $invoiceId,
            'stripe_payment_intent_id' => $this->extractId($invoiceObject['payment_intent'] ?? ''),
            'stripe_charge_id' => $this->extractId($invoiceObject['charge'] ?? ''),
            'invoice_number' => sanitize_text_field((string) ($invoiceObject['number'] ?? '')),
            'status' => $this->normalizeStatus($statusOverride !== '' ? $statusOverride : $this->deriveStatus($invoiceObject)),
            'currency' => strtolower(sanitize_text_field((string) ($invoiceObject['currency'] ?? ''))),
            'amount_due' => (int) ($invoiceObject['amount_due'] ?? 0),
            'amount_paid' => (int) ($invoiceObject['amount_paid'] ?? 0),
            'amount_remaining' => (int) ($invoiceObject['amount_remaining'] ?? 0),
            'period_start' => $this->extractPeriodBoundary($invoiceObject, 'start'),
            'period_end' => $this->extractPeriodBoundary($invoiceObject, 'end'),
            'invoice_created_at' => $invoiceObject['created'] ?? null,
            'hosted_invoice_url' => (string) ($invoiceObject['hosted_invoice_url'] ?? ''),
            'invoice_pdf_url' => (string) ($invoiceObject['invoice_pdf'] ?? ''),
            'receipt_url' => $this->extractReceiptUrl($invoiceObject),
            'is_live_mode' => ! empty($invoiceObject['livemode']),
        ];

        $this->billingHistoryRepository->upsertByInvoiceId($payload);

        if ($customerEmail !== '' && isset($payload['user_id']) && (int) $payload['user_id'] > 0) {
            $this->billingHistoryRepository->attachUserIdByEmail($customerEmail, (int) $payload['user_id']);
        }

        return true;
    }

    /**
     * @param array<string, mixed> $chargeObject
     */
    public function syncRefundFromChargeObject(array $chargeObject): void
    {
        $invoiceId = sanitize_text_field((string) ($chargeObject['invoice'] ?? ''));
        if ($invoiceId === '') {
            return;
        }

        $existing = $this->billingHistoryRepository->findByStripeInvoiceId($invoiceId);
        if (! is_array($existing)) {
            return;
        }

        $payload = [
            'stripe_invoice_id' => $invoiceId,
            'status' => 'refunded',
            'stripe_charge_id' => sanitize_text_field((string) ($chargeObject['id'] ?? '')),
            'receipt_url' => (string) ($chargeObject['receipt_url'] ?? ''),
        ];

        $amountRefunded = (int) ($chargeObject['amount_refunded'] ?? 0);
        if ($amountRefunded > 0) {
            $originalPaid = (int) ($existing['amount_paid'] ?? 0);
            $remainingPaid = max(0, $originalPaid - $amountRefunded);
            $payload['amount_paid'] = $remainingPaid;
        }

        $this->billingHistoryRepository->upsertByInvoiceId($payload);
    }

    public function syncRecentInvoicesForSubscription(string $stripeSubscriptionId, int $limit = 12): void
    {
        $subscriptionId = sanitize_text_field($stripeSubscriptionId);
        if ($subscriptionId === '') {
            return;
        }

        $response = $this->stripeClient->get('/invoices', [
            'subscription' => $subscriptionId,
            'limit' => max(1, min(100, $limit)),
        ]);

        if (is_wp_error($response)) {
            $this->logger->warning('Failed to sync recent Stripe invoices for subscription.', [
                'stripe_subscription_id' => $subscriptionId,
                'error' => $response->get_error_message(),
            ]);
            return;
        }

        $invoices = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
        foreach ($invoices as $invoice) {
            if (! is_array($invoice)) {
                continue;
            }

            $this->syncFromInvoiceObject($invoice);
        }
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getAccountBillingHistoryForUser(int $userId, int $limit = 100): array
    {
        if ($userId <= 0) {
            return [];
        }

        $subscriptions = $this->customerSubscriptionRepository->findByUserId($userId, 500);
        $email = $this->resolveUserEmail($userId);

        if ($email !== '') {
            $this->customerSubscriptionRepository->attachUserIdByEmail($email, $userId);
            $this->billingHistoryRepository->attachUserIdByEmail($email, $userId);
            $subscriptions = $this->mergeRowsUniqueById($subscriptions, $this->customerSubscriptionRepository->findByEmail($email, 500));
        }

        $allowedSubscriptionIds = [];
        foreach ($subscriptions as $subscription) {
            $subscriptionId = sanitize_text_field((string) ($subscription['stripe_subscription_id'] ?? ''));
            if ($subscriptionId === '') {
                continue;
            }

            $allowedSubscriptionIds[$subscriptionId] = true;
        }

        if (empty($allowedSubscriptionIds)) {
            return [];
        }

        $rows = $this->billingHistoryRepository->findByStripeSubscriptionIds(array_keys($allowedSubscriptionIds), $limit);
        if (empty($rows)) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $subscriptionId = sanitize_text_field((string) ($row['stripe_subscription_id'] ?? ''));
            if ($subscriptionId === '' || ! isset($allowedSubscriptionIds[$subscriptionId])) {
                continue;
            }

            if (! $this->isBillingRowOwnedByUser($row, $userId, $email)) {
                continue;
            }

            if (! isset($grouped[$subscriptionId])) {
                $grouped[$subscriptionId] = [];
            }

            $grouped[$subscriptionId][] = $this->formatBillingHistoryRow($row);
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function listForAdmin(array $filters, int $paged, int $perPage): array
    {
        $result = $this->billingHistoryRepository->findForAdmin($filters, $paged, $perPage);
        $formattedRows = [];

        foreach ($result['rows'] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $formattedRows[] = $this->formatBillingHistoryRow($row);
        }

        return [
            'rows' => $formattedRows,
            'total' => (int) ($result['total'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, int>
     */
    public function summarizeForAdmin(array $filters): array
    {
        return $this->billingHistoryRepository->summarize($filters);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function formatBillingHistoryRow(array $row): array
    {
        $currency = strtoupper((string) ($row['currency'] ?? ''));
        if ($currency === '') {
            $currency = get_woocommerce_currency();
        }

        $amountPaid = (int) ($row['amount_paid'] ?? 0);
        $amountDue = (int) ($row['amount_due'] ?? 0);
        $displayAmount = $amountPaid > 0 ? $amountPaid : $amountDue;
        $status = $this->normalizeStatus((string) ($row['status'] ?? 'pending'));
        $invoiceDate = (string) ($row['invoice_created_at'] ?? '');

        return [
            'date' => $invoiceDate,
            'date_label' => $this->formatDateLabel($invoiceDate),
            'amount' => $displayAmount,
            'amount_label' => wc_price($displayAmount / 100, ['currency' => $currency]),
            'currency' => $currency,
            'status' => $status,
            'status_label' => $this->formatStatusLabel($status),
            'invoice_number' => (string) ($row['invoice_number'] ?? ''),
            'invoice_id' => (string) ($row['stripe_invoice_id'] ?? ''),
            'period_start' => (string) ($row['period_start'] ?? ''),
            'period_end' => (string) ($row['period_end'] ?? ''),
            'period_label' => $this->formatPeriodLabel((string) ($row['period_start'] ?? ''), (string) ($row['period_end'] ?? '')),
            'hosted_invoice_url' => (string) ($row['hosted_invoice_url'] ?? ''),
            'invoice_pdf_url' => (string) ($row['invoice_pdf_url'] ?? ''),
            'receipt_url' => (string) ($row['receipt_url'] ?? ''),
            'stripe_payment_intent_id' => (string) ($row['stripe_payment_intent_id'] ?? ''),
            'stripe_charge_id' => (string) ($row['stripe_charge_id'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $invoiceObject
     */
    private function deriveStatus(array $invoiceObject): string
    {
        if (! empty($invoiceObject['paid'])) {
            return 'paid';
        }

        $status = strtolower(sanitize_text_field((string) ($invoiceObject['status'] ?? 'pending')));
        if ($status === 'void') {
            return 'void';
        }

        if ($status === 'open') {
            return 'open';
        }

        if ($status === 'draft') {
            return 'pending';
        }

        if ($status === 'uncollectible') {
            return 'failed';
        }

        return 'pending';
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = sanitize_key($status);

        if (! in_array($normalized, self::ALLOWED_STATUSES, true)) {
            return 'pending';
        }

        return $normalized;
    }

    private function formatStatusLabel(string $status): string
    {
        $labels = [
            'paid' => __('Paid', 'wp-stripe-payments'),
            'open' => __('Open', 'wp-stripe-payments'),
            'failed' => __('Failed', 'wp-stripe-payments'),
            'refunded' => __('Refunded', 'wp-stripe-payments'),
            'void' => __('Void', 'wp-stripe-payments'),
            'pending' => __('Pending', 'wp-stripe-payments'),
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    private function formatDateLabel(string $date): string
    {
        if ($date === '') {
            return __('N/A', 'wp-stripe-payments');
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return __('N/A', 'wp-stripe-payments');
        }

        return function_exists('wp_date')
            ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)
            : gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function formatPeriodLabel(string $periodStart, string $periodEnd): string
    {
        if ($periodStart === '' && $periodEnd === '') {
            return __('N/A', 'wp-stripe-payments');
        }

        $startLabel = $this->formatDateOnlyLabel($periodStart);
        $endLabel = $this->formatDateOnlyLabel($periodEnd);

        if ($startLabel !== '' && $endLabel !== '') {
            return sprintf('%s - %s', $startLabel, $endLabel);
        }

        if ($startLabel !== '') {
            return $startLabel;
        }

        if ($endLabel !== '') {
            return $endLabel;
        }

        return __('N/A', 'wp-stripe-payments');
    }

    private function formatDateOnlyLabel(string $date): string
    {
        if ($date === '') {
            return '';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }

        return function_exists('wp_date')
            ? wp_date(get_option('date_format'), $timestamp)
            : gmdate('Y-m-d', $timestamp);
    }

    /**
     * @param array<string, mixed> $invoiceObject
     */
    private function extractPeriodBoundary(array $invoiceObject, string $boundary): ?int
    {
        $lines = $invoiceObject['lines']['data'] ?? [];
        if (! is_array($lines)) {
            return null;
        }

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $lineType = sanitize_key((string) ($line['type'] ?? ''));
            if ($lineType !== 'subscription' && $lineType !== 'invoiceitem') {
                continue;
            }

            $period = isset($line['period']) && is_array($line['period']) ? $line['period'] : [];
            if (isset($period[$boundary]) && is_numeric($period[$boundary])) {
                return (int) $period[$boundary];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $invoiceObject
     */
    private function extractReceiptUrl(array $invoiceObject): string
    {
        $charge = $invoiceObject['charge'] ?? null;

        if (is_array($charge)) {
            $receiptUrl = (string) ($charge['receipt_url'] ?? '');
            if ($receiptUrl !== '' && wp_http_validate_url($receiptUrl)) {
                return $receiptUrl;
            }
        }

        return '';
    }

    /**
     * @param mixed $value
     */
    private function extractId($value): string
    {
        if (is_string($value)) {
            return sanitize_text_field($value);
        }

        if (is_array($value) && isset($value['id'])) {
            return sanitize_text_field((string) $value['id']);
        }

        return '';
    }

    private function resolveUserEmail(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        $user = get_user_by('id', $userId);
        if (! $user instanceof \WP_User) {
            return '';
        }

        return sanitize_email((string) $user->user_email);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isBillingRowOwnedByUser(array $row, int $userId, string $email): bool
    {
        $rowUserId = (int) ($row['user_id'] ?? 0);
        if ($rowUserId > 0) {
            return $rowUserId === $userId;
        }

        $rowEmail = strtolower((string) ($row['customer_email'] ?? ''));
        if ($rowEmail !== '' && $email !== '') {
            return $rowEmail === strtolower($email);
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $left
     * @param array<int, array<string, mixed>> $right
     *
     * @return array<int, array<string, mixed>>
     */
    private function mergeRowsUniqueById(array $left, array $right): array
    {
        $indexed = [];

        foreach (array_merge($left, $right) as $row) {
            $rowId = (int) ($row['id'] ?? 0);
            if ($rowId <= 0) {
                continue;
            }

            $indexed[$rowId] = $row;
        }

        usort($indexed, static function (array $a, array $b): int {
            return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        });

        return array_values($indexed);
    }
}
