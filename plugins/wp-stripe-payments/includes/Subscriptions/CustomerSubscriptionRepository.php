<?php

namespace WPStripePayments\Subscriptions;

class CustomerSubscriptionRepository
{
    public const TABLE_SLUG = 'wp_sp_customer_subscriptions';
    private const SCHEMA_VERSION_OPTION = 'wp_sp_customer_subscriptions_schema_version';
    private const SCHEMA_VERSION = '1.2.0';

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
            customer_email VARCHAR(190) NOT NULL,
            plan_id BIGINT UNSIGNED NOT NULL,
            stripe_customer_id VARCHAR(100) NOT NULL DEFAULT '',
            stripe_subscription_id VARCHAR(100) NOT NULL,
            stripe_price_id VARCHAR(100) NOT NULL DEFAULT '',
            status VARCHAR(50) NOT NULL DEFAULT 'incomplete',
            cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
            canceled_at DATETIME NULL,
            current_period_start DATETIME NULL,
            current_period_end DATETIME NULL,
            next_billing_date DATETIME NULL,
            billing_interval VARCHAR(20) NOT NULL DEFAULT '',
            plan_snapshot_title VARCHAR(255) NOT NULL DEFAULT '',
            plan_snapshot_price DECIMAL(12,2) NULL,
            last_checkout_session_id VARCHAR(100) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY stripe_subscription_id (stripe_subscription_id),
            KEY user_id (user_id),
            KEY customer_email (customer_email),
            KEY plan_id (plan_id),
            KEY status (status),
            KEY cancel_at_period_end (cancel_at_period_end),
            KEY current_period_end (current_period_end)
        ) {$charset};";

        dbDelta($sql);
        update_option(self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, false);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsertByStripeSubscriptionId(array $data): void
    {
        global $wpdb;

        $table = $this->getTableName();
        $now = function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');

        $existing = $this->findByStripeSubscriptionId((string) ($data['stripe_subscription_id'] ?? ''));

        $payload = [
            'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'customer_email' => (string) ($data['customer_email'] ?? ''),
            'plan_id' => (int) ($data['plan_id'] ?? 0),
            'stripe_customer_id' => (string) ($data['stripe_customer_id'] ?? ''),
            'stripe_subscription_id' => (string) ($data['stripe_subscription_id'] ?? ''),
            'stripe_price_id' => (string) ($data['stripe_price_id'] ?? ''),
            'status' => (string) ($data['status'] ?? 'incomplete'),
            'cancel_at_period_end' => ! empty($data['cancel_at_period_end']) ? 1 : 0,
            'canceled_at' => $this->normalizeDate($data['canceled_at'] ?? null),
            'current_period_start' => $this->normalizeDate($data['current_period_start'] ?? null),
            'current_period_end' => $this->normalizeDate($data['current_period_end'] ?? null),
            'next_billing_date' => $this->normalizeDate($data['next_billing_date'] ?? null),
            'billing_interval' => sanitize_text_field((string) ($data['billing_interval'] ?? '')),
            'plan_snapshot_title' => sanitize_text_field((string) ($data['plan_snapshot_title'] ?? '')),
            'plan_snapshot_price' => $this->normalizePrice($data['plan_snapshot_price'] ?? null),
            'last_checkout_session_id' => (string) ($data['last_checkout_session_id'] ?? ''),
            'updated_at' => $now,
        ];

        if ($existing === null) {
            $payload['created_at'] = $now;
            $wpdb->insert($table, $payload);
            return;
        }

        $wpdb->update($table, $payload, ['id' => (int) $existing['id']]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOneByStripeSubscriptionId(string $stripeSubscriptionId): ?array
    {
        global $wpdb;

        if ($stripeSubscriptionId === '') {
            return null;
        }

        $table = $this->getTableName();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE stripe_subscription_id = %s LIMIT 1", $stripeSubscriptionId),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByStripeSubscriptionId(string $stripeSubscriptionId): ?array
    {
        return $this->findOneByStripeSubscriptionId($stripeSubscriptionId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = $this->getTableName();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByUserId(int $userId, int $limit = 100): array
    {
        global $wpdb;

        if ($userId <= 0) {
            return [];
        }

        $table = $this->getTableName();
        $safeLimit = max(1, min(1000, $limit));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC LIMIT %d",
                $userId,
                $safeLimit
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByEmail(string $email, int $limit = 100): array
    {
        global $wpdb;

        $normalizedEmail = sanitize_email($email);
        if ($normalizedEmail === '') {
            return [];
        }

        $table = $this->getTableName();
        $safeLimit = max(1, min(1000, $limit));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE customer_email = %s ORDER BY updated_at DESC LIMIT %d",
                $normalizedEmail,
                $safeLimit
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAll(int $limit = 200): array
    {
        global $wpdb;

        $table = $this->getTableName();
        $safeLimit = max(1, min(2000, $limit));

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT %d", $safeLimit),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function findForAdmin(array $filters, int $paged, int $perPage, string $orderby = 'updated_at', string $order = 'DESC'): array
    {
        global $wpdb;

        $table = $this->getTableName();
        $where = ['1=1'];
        $params = [];

        $search = sanitize_text_field((string) ($filters['s'] ?? ''));
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(customer_email LIKE %s OR stripe_subscription_id LIKE %s OR stripe_customer_id LIKE %s OR plan_snapshot_title LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $status = sanitize_key((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        $autoRenew = sanitize_key((string) ($filters['auto_renew'] ?? ''));
        if ($autoRenew === 'on') {
            $where[] = 'cancel_at_period_end = 0';
            $where[] = "status <> 'canceled'";
        } elseif ($autoRenew === 'off') {
            $where[] = '(cancel_at_period_end = 1 OR status = %s)';
            $params[] = 'canceled';
        }

        $planId = (int) ($filters['plan_id'] ?? 0);
        if ($planId > 0) {
            $where[] = 'plan_id = %d';
            $params[] = $planId;
        }

        $dateFrom = $this->normalizeDateBoundary((string) ($filters['date_from'] ?? ''), 'start');
        if ($dateFrom !== '') {
            $where[] = 'updated_at >= %s';
            $params[] = $dateFrom;
        }

        $dateTo = $this->normalizeDateBoundary((string) ($filters['date_to'] ?? ''), 'end');
        if ($dateTo !== '') {
            $where[] = 'updated_at <= %s';
            $params[] = $dateTo;
        }

        $allowedOrderBy = ['updated_at', 'created_at', 'status', 'plan_snapshot_price', 'customer_email', 'current_period_end'];
        $safeOrderBy = in_array($orderby, $allowedOrderBy, true) ? $orderby : 'updated_at';
        $safeOrder = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $safePaged = max(1, $paged);
        $safePerPage = max(5, min(200, $perPage));
        $offset = ($safePaged - 1) * $safePerPage;

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(1) FROM {$table} WHERE {$whereSql}";
        $preparedCount = $wpdb->prepare($countSql, ...$params);
        $total = (int) $wpdb->get_var($preparedCount ?: $countSql);

        $dataSql = "SELECT * FROM {$table} WHERE {$whereSql} ORDER BY {$safeOrderBy} {$safeOrder} LIMIT %d OFFSET %d";
        $dataParams = array_merge($params, [$safePerPage, $offset]);
        $preparedData = $wpdb->prepare($dataSql, ...$dataParams);
        $rows = $wpdb->get_results($preparedData ?: $dataSql, ARRAY_A);

        return [
            'rows' => is_array($rows) ? $rows : [],
            'total' => $total,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        global $wpdb;

        $table = $this->getTableName();
        $rows = $wpdb->get_results("SELECT status, COUNT(1) as total FROM {$table} GROUP BY status", ARRAY_A);
        $stats = [];

        if (! is_array($rows)) {
            return $stats;
        }

        foreach ($rows as $row) {
            $key = sanitize_key((string) ($row['status'] ?? ''));
            if ($key === '') {
                continue;
            }

            $stats[$key] = (int) ($row['total'] ?? 0);
        }

        return $stats;
    }

    /**
     * @return array{on: int, off: int}
     */
    public function countAutoRenewStates(): array
    {
        global $wpdb;

        $table = $this->getTableName();

        $off = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$table} WHERE cancel_at_period_end = 1 OR status = 'canceled'");
        $all = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$table}");

        return [
            'on' => max(0, $all - $off),
            'off' => $off,
        ];
    }

    public function countUpdatedSince(string $fromDate): int
    {
        global $wpdb;

        $table = $this->getTableName();
        $normalized = $this->normalizeDateBoundary($fromDate, 'start');
        if ($normalized === '') {
            return 0;
        }

        $sql = $wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE updated_at >= %s", $normalized);
        return (int) $wpdb->get_var($sql ?: '');
    }

    public function countCreatedBetween(string $fromDate, string $toDate): int
    {
        global $wpdb;

        $table = $this->getTableName();
        $start = $this->normalizeDateBoundary($fromDate, 'start');
        $end = $this->normalizeDateBoundary($toDate, 'end');

        if ($start === '' || $end === '') {
            return 0;
        }

        $sql = $wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE created_at >= %s AND created_at <= %s", $start, $end);
        return (int) $wpdb->get_var($sql ?: '');
    }

    public function countCanceledBetween(string $fromDate, string $toDate): int
    {
        global $wpdb;

        $table = $this->getTableName();
        $start = $this->normalizeDateBoundary($fromDate, 'start');
        $end = $this->normalizeDateBoundary($toDate, 'end');

        if ($start === '' || $end === '') {
            return 0;
        }

        $sql = $wpdb->prepare(
            "SELECT COUNT(1) FROM {$table} WHERE status = %s AND updated_at >= %s AND updated_at <= %s",
            'canceled',
            $start,
            $end
        );
        return (int) $wpdb->get_var($sql ?: '');
    }

    /**
     * @return array<int, array{bucket: string, total: int}>
     */
    public function getCreatedSeries(string $fromDate, string $toDate): array
    {
        global $wpdb;

        $table = $this->getTableName();
        $start = $this->normalizeDateBoundary($fromDate, 'start');
        $end = $this->normalizeDateBoundary($toDate, 'end');

        if ($start === '' || $end === '') {
            return [];
        }

        $sql = $wpdb->prepare(
            "SELECT DATE(created_at) AS bucket, COUNT(1) AS total FROM {$table} WHERE created_at >= %s AND created_at <= %s GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC",
            $start,
            $end
        );

        $rows = $wpdb->get_results($sql ?: '', ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'bucket' => (string) ($row['bucket'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ];
        }, $rows);
    }

    public function updateStatus(string $stripeSubscriptionId, string $status): bool
    {
        global $wpdb;

        if ($stripeSubscriptionId === '') {
            return false;
        }

        $table = $this->getTableName();
        $now = function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
        $updated = $wpdb->update(
            $table,
            [
                'status' => sanitize_text_field($status),
                'updated_at' => $now,
            ],
            ['stripe_subscription_id' => $stripeSubscriptionId]
        );

        return $updated !== false;
    }

    /**
     * @param string|int|null $canceledAt
     * @param string|int|null $currentPeriodEnd
     * @param string|int|null $currentPeriodStart
     */
    public function updateCancellationFlags(
        string $stripeSubscriptionId,
        bool $cancelAtPeriodEnd,
        $canceledAt = null,
        $currentPeriodEnd = null,
        $currentPeriodStart = null
    ): bool {
        global $wpdb;

        if ($stripeSubscriptionId === '') {
            return false;
        }

        $table = $this->getTableName();
        $now = function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
        $updated = $wpdb->update(
            $table,
            [
                'cancel_at_period_end' => $cancelAtPeriodEnd ? 1 : 0,
                'canceled_at' => $this->normalizeDate($canceledAt),
                'current_period_end' => $this->normalizeDate($currentPeriodEnd),
                'current_period_start' => $this->normalizeDate($currentPeriodStart),
                'next_billing_date' => $this->normalizeDate($currentPeriodEnd),
                'updated_at' => $now,
            ],
            ['stripe_subscription_id' => $stripeSubscriptionId]
        );

        return $updated !== false;
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
     * @param mixed $value
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

    /**
     * @param mixed $value
     */
    private function normalizePrice($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return wc_format_decimal((string) $value, 2);
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
}
