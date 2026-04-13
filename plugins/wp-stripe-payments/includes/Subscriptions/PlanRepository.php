<?php

namespace WPStripePayments\Subscriptions;

class PlanRepository
{
    /**
     * WordPress stores post_type in wp_posts.post_type (varchar(20)),
     * so the internal key must stay within 20 characters.
     */
    public const POST_TYPE = 'wp_sp_sub_plan';
    public const LEGACY_POST_TYPE = 'wp_sp_subscription_plan';
    public const META_DESCRIPTION = '_wp_sp_plan_description';
    public const META_IMAGE = '_wp_sp_plan_image';
    public const META_PRICE = '_wp_sp_plan_price';
    public const META_BILLING_INTERVAL = '_wp_sp_plan_billing_interval';
    public const META_STATUS = '_wp_sp_plan_status';
    public const META_STRIPE_PRODUCT_ID = '_wp_sp_plan_stripe_product_id';
    public const META_STRIPE_PRICE_ID = '_wp_sp_plan_stripe_price_id';
    public const META_LAST_SYNC_STATUS = '_wp_sp_plan_last_sync_status';
    public const META_LAST_SYNC_MESSAGE = '_wp_sp_plan_last_sync_message';
    public const META_LAST_SYNC_AT = '_wp_sp_plan_last_sync_at';

    /**
     * @return array<string, string>
     */
    public function getPlanMeta(int $postId): array
    {
        return [
            'description' => (string) get_post_meta($postId, self::META_DESCRIPTION, true),
            'image' => (string) get_post_meta($postId, self::META_IMAGE, true),
            'price' => (string) get_post_meta($postId, self::META_PRICE, true),
            'billing_interval' => (string) get_post_meta($postId, self::META_BILLING_INTERVAL, true),
            'status' => (string) get_post_meta($postId, self::META_STATUS, true),
            'stripe_product_id' => (string) get_post_meta($postId, self::META_STRIPE_PRODUCT_ID, true),
            'stripe_price_id' => (string) get_post_meta($postId, self::META_STRIPE_PRICE_ID, true),
            'last_sync_status' => (string) get_post_meta($postId, self::META_LAST_SYNC_STATUS, true),
            'last_sync_message' => (string) get_post_meta($postId, self::META_LAST_SYNC_MESSAGE, true),
            'last_sync_at' => (string) get_post_meta($postId, self::META_LAST_SYNC_AT, true),
        ];
    }

    /**
     * @param array<string, string> $data
     */
    public function savePlanMeta(int $postId, array $data): void
    {
        update_post_meta($postId, self::META_DESCRIPTION, $data['description'] ?? '');
        update_post_meta($postId, self::META_IMAGE, $data['image'] ?? '');
        update_post_meta($postId, self::META_PRICE, $data['price'] ?? '');
        update_post_meta($postId, self::META_BILLING_INTERVAL, $data['billing_interval'] ?? 'month');
        update_post_meta($postId, self::META_STATUS, $data['status'] ?? 'inactive');
        update_post_meta($postId, self::META_STRIPE_PRODUCT_ID, $data['stripe_product_id'] ?? '');
        update_post_meta($postId, self::META_STRIPE_PRICE_ID, $data['stripe_price_id'] ?? '');
        update_post_meta($postId, self::META_LAST_SYNC_STATUS, $data['last_sync_status'] ?? '');
        update_post_meta($postId, self::META_LAST_SYNC_MESSAGE, $data['last_sync_message'] ?? '');
        update_post_meta($postId, self::META_LAST_SYNC_AT, $data['last_sync_at'] ?? '');
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, string>
     */
    public function sanitizeMeta(array $raw): array
    {
        $interval = sanitize_text_field((string) ($raw['billing_interval'] ?? 'month'));
        $status = sanitize_text_field((string) ($raw['status'] ?? 'inactive'));

        $allowedIntervals = ['day', 'week', 'month', 'year'];
        if (! in_array($interval, $allowedIntervals, true)) {
            $interval = 'month';
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            $status = 'inactive';
        }

        return [
            'description' => wp_kses_post((string) ($raw['description'] ?? '')),
            'image' => esc_url_raw((string) ($raw['image'] ?? '')),
            'price' => wc_format_decimal((string) ($raw['price'] ?? '0'), 2),
            'billing_interval' => $interval,
            'status' => $status,
            'stripe_product_id' => sanitize_text_field((string) ($raw['stripe_product_id'] ?? '')),
            'stripe_price_id' => sanitize_text_field((string) ($raw['stripe_price_id'] ?? '')),
            'last_sync_status' => sanitize_key((string) ($raw['last_sync_status'] ?? '')),
            'last_sync_message' => sanitize_text_field((string) ($raw['last_sync_message'] ?? '')),
            'last_sync_at' => sanitize_text_field((string) ($raw['last_sync_at'] ?? '')),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    public function findById(int $planId): ?array
    {
        $post = get_post($planId);
        if (! $post instanceof \WP_Post || $post->post_type !== self::POST_TYPE) {
            return null;
        }

        $meta = $this->getPlanMeta($planId);

        return [
            'id' => (string) $planId,
            'title' => (string) $post->post_title,
            'description' => $meta['description'],
            'image' => $meta['image'],
            'price' => $meta['price'],
            'billing_interval' => $meta['billing_interval'] !== '' ? $meta['billing_interval'] : 'month',
            'status' => $meta['status'] !== '' ? $meta['status'] : 'inactive',
            'stripe_product_id' => $meta['stripe_product_id'],
            'stripe_price_id' => $meta['stripe_price_id'],
            'last_sync_status' => $meta['last_sync_status'],
            'last_sync_message' => $meta['last_sync_message'],
            'last_sync_at' => $meta['last_sync_at'],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getActivePlans(): array
    {
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'menu_order title',
            'order' => 'ASC',
            'meta_key' => self::META_STATUS,
            'meta_value' => 'active',
        ]);

        $plans = [];

        foreach ($posts as $post) {
            if (! $post instanceof \WP_Post) {
                continue;
            }

            $plan = $this->findById((int) $post->ID);
            if ($plan !== null) {
                $plans[] = $plan;
            }
        }

        return $plans;
    }
}
