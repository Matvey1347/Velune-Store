<?php

namespace WPStripePayments\Subscriptions;

class CustomerSubscriptionBillingHistoryRepository
{
    public const TABLE_SLUG = 'wp_sp_subscription_invoices';
    private const SCHEMA_VERSION_OPTION = 'wp_sp_subscription_invoices_schema_version';
    private const SCHEMA_VERSION = '1.0.0';

    public function getTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_SLUG;
    }

    public function maybeMigrate(): void
    {
        $storedVersion = (string) get_option(self::SCHEMA_VERSION_OPTION, '');

        if ($storedVersion === self::SCHEMA_VERSION) {
            return;
        }

        $this->createTable();
    }

    public function createTable(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $this->getTableName();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            customer_email VARCHAR(190) NOT NULL DEFAULT '',
            stripe_customer_id VARCHAR(100) NOT NULL DEFAULT '',
            stripe_subscription_id VARCHAR(100) NOT NULL DEFAULT '',
            stripe_invoice_id VARCHAR(100) NOT NULL,
            stripe_payment_intent_id VARCHAR(100) NOT NULL DEFAULT '',
            stripe_charge_id VARCHAR(100) NOT NULL DEFAULT '',
            invoice_number VARCHAR(120) NOT NULL DEFAULT '',
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            currency VARCHAR(12) NOT NULL DEFAULT '',
            amount_due BIGINT NOT NULL DEFAULT 0,
            amount_paid BIGINT NOT NULL DEFAULT 0,
            amount_remaining BIGINT NOT NULL DEFAULT 0,
            period_start DATETIME NULL,
            period_end DATETIME NULL,
            invoice_created_at DATETIME NULL,
            hosted_invoice_url TEXT NULL,
            invoice_pdf_url TEXT NULL,
            receipt_url TEXT NULL,
            is_live_mode TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY stripe_invoice_id (stripe_invoice_id),
            KEY user_id (user_id),
            KEY customer_email (customer_email),
            KEY stripe_customer_id (stripe_customer_id),
            KEY stripe_subscription_id (stripe_subscription_id),
            KEY status (status),
            KEY invoice_created_at (invoice_created_at)
        ) {$charset};";

        dbDelta($sql);
        update_option(self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, false);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsertByInvoiceId(array $data): void
    {
        global $wpdb;

        $invoiceId = sanitize_text_field((string) ($data['stripe_invoice_id'] ?? ''));
        if ($invoiceId === '') {
            return;
        }

        $existing = $this->findByStripeInvoiceId($invoiceId);
        $table = $this->getTableName();
        $now = function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');

        $payload = [
            'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'customer_email' => sanitize_email((string) ($data['customer_email'] ?? '')),
            'stripe_customer_id' => sanitize_text_field((string) ($data['stripe_customer_id'] ?? '')),
            'stripe_subscription_id' => sanitize_text_field((string) ($data['stripe_subscription_id'] ?? '')),
            'stripe_invoice_id' => $invoiceId,
            'stripe_payment_intent_id' => sanitize_text_field((string) ($data['stripe_payment_intent_id'] ?? '')),
            'stripe_charge_id' => sanitize_text_field((string) ($data['stripe_charge_id'] ?? '')),
            'invoice_number' => sanitize_text_field((string) ($data['invoice_number'] ?? '')),
            'status' => sanitize_key((string) ($data['status'] ?? 'pending')),
            'currency' => strtolower(sanitize_text_field((string) ($data['currency'] ?? ''))),
            'amount_due' => $this->normalizeIntegerAmount($data['amount_due'] ?? 0),
            'amount_paid' => $this->normalizeIntegerAmount($data['amount_paid'] ?? 0),
            'amount_remaining' => $this->normalizeIntegerAmount($data['amount_remaining'] ?? 0),
            'period_start' => $this->normalizeDate($data['period_start'] ?? null),
            'period_end' => $this->normalizeDate($data['period_end'] ?? null),
            'invoice_created_at' => $this->normalizeDate($data['invoice_created_at'] ?? null),
            'hosted_invoice_url' => $this->normalizeUrl((string) ($data['hosted_invoice_url'] ?? '')),
            'invoice_pdf_url' => $this->normalizeUrl((string) ($data['invoice_pdf_url'] ?? '')),
            'receipt_url' => $this->normalizeUrl((string) ($data['receipt_url'] ?? '')),
            'is_live_mode' => ! empty($data['is_live_mode']) ? 1 : 0,
            'updated_at' => $now,
        ];

        if (is_array($existing)) {
            $payload = $this->keepExistingValuesWhenMissing($payload, $existing);
            $wpdb->update($table, $payload, ['id' => (int) $existing['id']]);
            return;
        }

        $payload['created_at'] = $now;
        $wpdb->insert($table, $payload);
    }

    public function attachUserIdByEmail(string $email, int $userId): void
    {
        global $wpdb;

        $normalizedEmail = sanitize_email($email);
        if ($normalizedEmail === '' || $userId <= 0) {
            return;
        }

        $table = $this->getTableName();
        $now = function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET user_id = %d, updated_at = %s WHERE customer_email = %s AND (user_id IS NULL OR user_id = 0)",
                $userId,
                $now,
                $normalizedEmail
            )
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByStripeSubscriptionIds(array $subscriptionIds, int $limit = 100): array
    {
        global $wpdb;

        $cleanIds = array_values(array_filter(array_map(static function ($value): string {
            return sanitize_text_field((string) $value);
        }, $subscriptionIds)));

        if (empty($cleanIds)) {
            return [];
        }

        $safeLimit = max(1, min(1000, $limit));
        $placeholders = implode(',', array_fill(0, count($cleanIds), '%s'));
        $sql = "SELECT * FROM {$this->getTableName()} WHERE stripe_subscription_id IN ({$placeholders}) ORDER BY invoice_created_at DESC, updated_at DESC LIMIT %d";

        $params = array_merge($cleanIds, [$safeLimit]);
        $preparedSql = $wpdb->prepare($sql, ...$params);
        if (! is_string($preparedSql)) {
            return [];
        }

        $rows = $wpdb->get_results($preparedSql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function findForAdmin(array $filters, int $paged, int $perPage): array
    {
        global $wpdb;

        $table = $this->getTableName();
        $subscriptionsTable = $wpdb->prefix . CustomerSubscriptionRepository::TABLE_SLUG;
        $where = ['1=1'];
        $params = [];

        $status = sanitize_key((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        $customer = sanitize_text_field((string) ($filters['customer'] ?? ''));
        if ($customer !== '') {
            $like = '%' . $wpdb->esc_like($customer) . '%';
            $where[] = '(customer_email LIKE %s OR stripe_customer_id LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $subscriptionId = sanitize_text_field((string) ($filters['subscription_id'] ?? ''));
        if ($subscriptionId !== '') {
            $where[] = 'stripe_subscription_id = %s';
            $params[] = $subscriptionId;
        }

        $planId = (int) ($filters['plan_id'] ?? 0);
        if ($planId > 0) {
            $where[] = "stripe_subscription_id IN (SELECT stripe_subscription_id FROM {$subscriptionsTable} WHERE plan_id = %d)";
            $params[] = $planId;
        }

        $dateFrom = $this->normalizeDateBoundary((string) ($filters['date_from'] ?? ''), 'start');
        if ($dateFrom !== '') {
            $where[] = 'invoice_created_at >= %s';
            $params[] = $dateFrom;
        }

        $dateTo = $this->normalizeDateBoundary((string) ($filters['date_to'] ?? ''), 'end');
        if ($dateTo !== '') {
            $where[] = 'invoice_created_at <= %s';
            $params[] = $dateTo;
        }

        $safePaged = max(1, $paged);
        $safePerPage = max(5, min(200, $perPage));
        $offset = ($safePaged - 1) * $safePerPage;
        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(1) FROM {$table} WHERE {$whereSql}";
        $preparedCount = $wpdb->prepare($countSql, ...$params);
        $total = (int) $wpdb->get_var($preparedCount ?: $countSql);

        $dataSql = "SELECT * FROM {$table} WHERE {$whereSql} ORDER BY invoice_created_at DESC, updated_at DESC LIMIT %d OFFSET %d";
        $dataParams = array_merge($params, [$safePerPage, $offset]);
        $preparedData = $wpdb->prepare($dataSql, ...$dataParams);
        $rows = $wpdb->get_results($preparedData ?: $dataSql, ARRAY_A);

        return [
            'rows' => is_array($rows) ? $rows : [],
            'total' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, int>
     */
    public function summarize(array $filters): array
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];
        $subscriptionsTable = $wpdb->prefix . CustomerSubscriptionRepository::TABLE_SLUG;

        $dateFrom = $this->normalizeDateBoundary((string) ($filters['date_from'] ?? ''), 'start');
        if ($dateFrom !== '') {
            $where[] = 'invoice_created_at >= %s';
            $params[] = $dateFrom;
        }

        $dateTo = $this->normalizeDateBoundary((string) ($filters['date_to'] ?? ''), 'end');
        if ($dateTo !== '') {
            $where[] = 'invoice_created_at <= %s';
            $params[] = $dateTo;
        }

        $subscriptionId = sanitize_text_field((string) ($filters['subscription_id'] ?? ''));
        if ($subscriptionId !== '') {
            $where[] = 'stripe_subscription_id = %s';
            $params[] = $subscriptionId;
        }

        $planId = (int) ($filters['plan_id'] ?? 0);
        if ($planId > 0) {
            $where[] = "stripe_subscription_id IN (SELECT stripe_subscription_id FROM {$subscriptionsTable} WHERE plan_id = %d)";
            $params[] = $planId;
        }

        $whereSql = implode(' AND ', $where);
        $table = $this->getTableName();

        $summarySql = "SELECT
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS successful,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
            SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) AS refunded,
            SUM(CASE WHEN status = 'paid' THEN amount_paid ELSE 0 END) AS total_paid
            FROM {$table}
            WHERE {$whereSql}";

        $prepared = $wpdb->prepare($summarySql, ...$params);
        $row = $wpdb->get_row($prepared ?: $summarySql, ARRAY_A);

        if (! is_array($row)) {
            return [
                'successful' => 0,
                'failed' => 0,
                'refunded' => 0,
                'total_paid' => 0,
            ];
        }

        return [
            'successful' => (int) ($row['successful'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'refunded' => (int) ($row['refunded'] ?? 0),
            'total_paid' => (int) ($row['total_paid'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{bucket: string, revenue: int, failed: int}>
     */
    public function getRevenueSeries(string $fromDate, string $toDate, int $planId = 0): array
    {
        global $wpdb;

        $start = $this->normalizeDateBoundary($fromDate, 'start');
        $end = $this->normalizeDateBoundary($toDate, 'end');
        if ($start === '' || $end === '') {
            return [];
        }

        $table = $this->getTableName();
        $subscriptionsTable = $wpdb->prefix . CustomerSubscriptionRepository::TABLE_SLUG;
        $where = "invoice_created_at >= %s AND invoice_created_at <= %s";
        $params = [$start, $end];
        if ($planId > 0) {
            $where .= " AND stripe_subscription_id IN (SELECT stripe_subscription_id FROM {$subscriptionsTable} WHERE plan_id = %d)";
            $params[] = $planId;
        }

        $sql = $wpdb->prepare(
            "SELECT DATE(invoice_created_at) AS bucket,
                SUM(CASE WHEN status = 'paid' THEN amount_paid ELSE 0 END) AS revenue,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed
            FROM {$table}
            WHERE {$where}
            GROUP BY DATE(invoice_created_at)
            ORDER BY DATE(invoice_created_at) ASC",
            ...$params
        );

        $rows = $wpdb->get_results($sql ?: '', ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'bucket' => (string) ($row['bucket'] ?? ''),
                'revenue' => (int) ($row['revenue'] ?? 0),
                'failed' => (int) ($row['failed'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByStripeInvoiceId(string $invoiceId): ?array
    {
        global $wpdb;

        $normalized = sanitize_text_field($invoiceId);
        if ($normalized === '') {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->getTableName()} WHERE stripe_invoice_id = %s LIMIT 1", $normalized),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByChargeId(string $chargeId): ?array
    {
        global $wpdb;

        $normalized = sanitize_text_field($chargeId);
        if ($normalized === '') {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->getTableName()} WHERE stripe_charge_id = %s LIMIT 1", $normalized),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param string|int|null $value
     */
    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return gmdate('Y-m-d H:i:s', (int) $value);
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function normalizeDateBoundary(string $date, string $position): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        $timestamp = strtotime($date . ($position === 'end' ? ' 23:59:59' : ' 00:00:00'));
        if ($timestamp === false) {
            return '';
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        return wp_http_validate_url($url) ? $url : '';
    }

    /**
     * @param mixed $value
     */
    private function normalizeIntegerAmount($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) round((float) $value);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $existing
     *
     * @return array<string, mixed>
     */
    private function keepExistingValuesWhenMissing(array $payload, array $existing): array
    {
        $preferExistingIfEmpty = [
            'customer_email',
            'stripe_customer_id',
            'stripe_subscription_id',
            'stripe_payment_intent_id',
            'stripe_charge_id',
            'invoice_number',
            'currency',
            'hosted_invoice_url',
            'invoice_pdf_url',
            'receipt_url',
        ];

        foreach ($preferExistingIfEmpty as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            if ((string) $payload[$key] === '' && ! empty($existing[$key])) {
                $payload[$key] = (string) $existing[$key];
            }
        }

        if ((int) ($payload['user_id'] ?? 0) <= 0 && (int) ($existing['user_id'] ?? 0) > 0) {
            $payload['user_id'] = (int) $existing['user_id'];
        }

        return $payload;
    }
}
