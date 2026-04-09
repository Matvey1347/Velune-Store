<?php

namespace WPStripePayments\Subscriptions;

class CustomerSubscriptionRepository
{
    public const TABLE_SLUG = 'wp_sp_customer_subscriptions';

    public function getTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_SLUG;
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
            next_billing_date DATETIME NULL,
            last_checkout_session_id VARCHAR(100) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY stripe_subscription_id (stripe_subscription_id),
            KEY user_id (user_id),
            KEY customer_email (customer_email),
            KEY plan_id (plan_id),
            KEY status (status)
        ) {$charset};";

        dbDelta($sql);
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
            'next_billing_date' => $this->normalizeDate($data['next_billing_date'] ?? null),
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
    public function findByStripeSubscriptionId(string $stripeSubscriptionId): ?array
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
     * @return array<int, array<string, mixed>>
     */
    public function findAll(int $limit = 200): array
    {
        global $wpdb;

        $table = $this->getTableName();
        $safeLimit = max(1, min(1000, $limit));

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT %d", $safeLimit),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
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
}
