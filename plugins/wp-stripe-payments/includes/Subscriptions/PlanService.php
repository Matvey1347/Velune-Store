<?php

namespace WPStripePayments\Subscriptions;

use WPStripePayments\Stripe\SubscriptionPlanSyncService;

class PlanService
{
    private PlanRepository $repository;

    private SubscriptionPlanSyncService $planSyncService;

    public function __construct(?PlanRepository $repository = null, ?SubscriptionPlanSyncService $planSyncService = null)
    {
        $this->repository = $repository ?? new PlanRepository();
        $this->planSyncService = $planSyncService ?? new SubscriptionPlanSyncService($this->repository);
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType'], 0);
        add_action('admin_init', [$this, 'normalizeLegacyPostTypeRequest'], 0);
        add_action('admin_enqueue_scripts', [$this, 'enqueueMediaAssets']);
        add_action('add_meta_boxes', [$this, 'registerMetaBoxes']);
        add_action('save_post_' . PlanRepository::POST_TYPE, [$this, 'saveMeta'], 10, 2);
        add_filter('manage_' . PlanRepository::POST_TYPE . '_posts_columns', [$this, 'addAdminColumns']);
        add_action('manage_' . PlanRepository::POST_TYPE . '_posts_custom_column', [$this, 'renderAdminColumns'], 10, 2);
        add_action('admin_post_wp_sp_sync_plan_now', [$this, 'handleManualSync']);
        add_action('admin_notices', [$this, 'renderPlanNotices']);

        if (did_action('init')) {
            $this->registerPostType();
        }
    }

    public function registerPostType(): void
    {
        if (post_type_exists(PlanRepository::POST_TYPE)) {
            return;
        }

        $labels = [
            'name' => __('Plans', 'wp-stripe-payments'),
            'singular_name' => __('Subscription Plan', 'wp-stripe-payments'),
            'menu_name' => __('Plans', 'wp-stripe-payments'),
            'add_new' => __('Add Plan', 'wp-stripe-payments'),
            'add_new_item' => __('Add New Subscription Plan', 'wp-stripe-payments'),
            'edit_item' => __('Edit Subscription Plan', 'wp-stripe-payments'),
            'new_item' => __('New Subscription Plan', 'wp-stripe-payments'),
            'view_item' => __('View Subscription Plan', 'wp-stripe-payments'),
            'search_items' => __('Search Subscription Plans', 'wp-stripe-payments'),
            'not_found' => __('No subscription plans found.', 'wp-stripe-payments'),
            'not_found_in_trash' => __('No subscription plans found in Trash.', 'wp-stripe-payments'),
        ];

        register_post_type(PlanRepository::POST_TYPE, [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_nav_menus' => false,
            'show_in_admin_bar' => false,
            'has_archive' => false,
            'rewrite' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'menu_icon' => 'dashicons-update',
        ]);
    }

    public function normalizeLegacyPostTypeRequest(): void
    {
        if (! is_admin()) {
            return;
        }

        $requestPostType = isset($_REQUEST['post_type']) ? sanitize_key((string) $_REQUEST['post_type']) : '';
        if ($requestPostType !== PlanRepository::LEGACY_POST_TYPE) {
            return;
        }

        $_GET['post_type'] = PlanRepository::POST_TYPE;
        $_REQUEST['post_type'] = PlanRepository::POST_TYPE;
    }

    public function enqueueMediaAssets(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (! $screen instanceof \WP_Screen || $screen->post_type !== PlanRepository::POST_TYPE) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'wp-sp-plan-image-uploader',
            WP_STRIPE_PAYMENTS_URL . 'assets/js/plan-image-uploader.js',
            ['jquery'],
            WP_STRIPE_PAYMENTS_VERSION,
            true
        );
    }

    public function registerMetaBoxes(): void
    {
        add_meta_box(
            'wp_sp_subscription_plan_details',
            __('Plan Details', 'wp-stripe-payments'),
            [$this, 'renderMetaBox'],
            PlanRepository::POST_TYPE,
            'normal',
            'default'
        );
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $meta = $this->repository->getPlanMeta((int) $post->ID);
        $status = $meta['status'] !== '' ? $meta['status'] : 'inactive';
        $billingInterval = $meta['billing_interval'] !== '' ? $meta['billing_interval'] : 'month';

        wp_nonce_field('wp_sp_save_plan_meta', 'wp_sp_plan_meta_nonce');
        ?>
        <div class="wp-sp-card" style="margin-bottom:12px;">
            <h3><?php esc_html_e('Plan Configuration', 'wp-stripe-payments'); ?></h3>
            <p class="description"><?php esc_html_e('Define your product, recurring billing cycle, and public plan details shown to customers.', 'wp-stripe-payments'); ?></p>
            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <th scope="row"><label for="wp_sp_description"><?php esc_html_e('Description', 'wp-stripe-payments'); ?></label></th>
                    <td>
                        <textarea class="large-text" rows="4" id="wp_sp_description" name="wp_sp_plan[description]"><?php echo esc_textarea($meta['description']); ?></textarea>
                        <p class="description"><?php esc_html_e('Displayed in plan previews. Keep this concise and benefit-driven.', 'wp-stripe-payments'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp_sp_image"><?php esc_html_e('Image', 'wp-stripe-payments'); ?></label></th>
                    <td>
                        <input class="regular-text" type="url" id="wp_sp_image" name="wp_sp_plan[image]" value="<?php echo esc_attr($meta['image']); ?>" readonly="readonly" />
                        <p>
                            <button type="button" class="button" id="wp_sp_select_image"><?php esc_html_e('Select image', 'wp-stripe-payments'); ?></button>
                            <button type="button" class="button" id="wp_sp_remove_image"><?php esc_html_e('Remove image', 'wp-stripe-payments'); ?></button>
                        </p>
                        <p id="wp_sp_image_preview" <?php echo $meta['image'] === '' ? 'style="display:none;"' : ''; ?>>
                            <img id="wp_sp_image_preview_img" src="<?php echo esc_url($meta['image']); ?>" alt="" style="max-width:180px;height:auto;border-radius:8px;" />
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp_sp_price"><?php esc_html_e('Price', 'wp-stripe-payments'); ?></label></th>
                    <td>
                        <input class="regular-text" type="text" id="wp_sp_price" name="wp_sp_plan[price]" value="<?php echo esc_attr($meta['price']); ?>" placeholder="9.99" />
                        <p class="description"><?php esc_html_e('Use major currency units (for example 29.00).', 'wp-stripe-payments'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp_sp_billing_interval"><?php esc_html_e('Billing Interval', 'wp-stripe-payments'); ?></label></th>
                    <td>
                        <select id="wp_sp_billing_interval" name="wp_sp_plan[billing_interval]">
                            <option value="day" <?php selected($billingInterval, 'day'); ?>><?php esc_html_e('Daily', 'wp-stripe-payments'); ?></option>
                            <option value="week" <?php selected($billingInterval, 'week'); ?>><?php esc_html_e('Weekly', 'wp-stripe-payments'); ?></option>
                            <option value="month" <?php selected($billingInterval, 'month'); ?>><?php esc_html_e('Monthly', 'wp-stripe-payments'); ?></option>
                            <option value="year" <?php selected($billingInterval, 'year'); ?>><?php esc_html_e('Yearly', 'wp-stripe-payments'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp_sp_status"><?php esc_html_e('Status', 'wp-stripe-payments'); ?></label></th>
                    <td>
                        <select id="wp_sp_status" name="wp_sp_plan[status]">
                            <option value="active" <?php selected($status, 'active'); ?>><?php esc_html_e('Active', 'wp-stripe-payments'); ?></option>
                            <option value="inactive" <?php selected($status, 'inactive'); ?>><?php esc_html_e('Inactive', 'wp-stripe-payments'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Only active plans should be shown for checkout.', 'wp-stripe-payments'); ?></p>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="wp-sp-card" style="margin-bottom:12px;">
            <h3><?php esc_html_e('Stripe Sync', 'wp-stripe-payments'); ?></h3>
            <p class="description"><?php esc_html_e('Connect this plan to a Stripe product and recurring price.', 'wp-stripe-payments'); ?></p>
            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <th scope="row"><label for="wp_sp_stripe_product_id"><?php esc_html_e('Stripe Product ID', 'wp-stripe-payments'); ?></label></th>
                    <td>
                        <input class="regular-text" type="text" id="wp_sp_stripe_product_id" name="wp_sp_plan[stripe_product_id]" value="<?php echo esc_attr($meta['stripe_product_id']); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp_sp_stripe_price_id"><?php esc_html_e('Stripe Price ID', 'wp-stripe-payments'); ?></label></th>
                    <td>
                        <input class="regular-text" type="text" id="wp_sp_stripe_price_id" name="wp_sp_plan[stripe_price_id]" value="<?php echo esc_attr($meta['stripe_price_id']); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Connection State', 'wp-stripe-payments'); ?></th>
                    <td>
                        <?php if ($meta['stripe_product_id'] !== '' && $meta['stripe_price_id'] !== '') : ?>
                            <span class="wp-sp-status-badge is-good"><?php esc_html_e('Connected', 'wp-stripe-payments'); ?></span>
                        <?php else : ?>
                            <span class="wp-sp-status-badge is-warning"><?php esc_html_e('Not connected', 'wp-stripe-payments'); ?></span>
                            <p class="description"><?php esc_html_e('Missing Stripe IDs. Checkout for this plan will stay disabled until sync completes.', 'wp-stripe-payments'); ?></p>
                        <?php endif; ?>

                        <?php if ($meta['last_sync_status'] !== '') : ?>
                            <p>
                                <strong><?php esc_html_e('Last sync status:', 'wp-stripe-payments'); ?></strong>
                                <?php echo esc_html(ucfirst($meta['last_sync_status'])); ?>
                                <?php if ($meta['last_sync_at'] !== '') : ?>
                                    (<?php echo esc_html((string) $meta['last_sync_at']); ?>)
                                <?php endif; ?>
                            </p>
                            <?php if ($meta['last_sync_message'] !== '') : ?>
                                <p class="description"><?php echo esc_html($meta['last_sync_message']); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Sync Options', 'wp-stripe-payments'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wp_sp_plan[sync_to_stripe]" value="1" checked="checked" />
                            <?php esc_html_e('Sync plan to Stripe when you save this post', 'wp-stripe-payments'); ?>
                        </label>
                        <p style="margin-top:8px;">
                            <?php
                            $syncUrl = wp_nonce_url(
                                add_query_arg([
                                    'action' => 'wp_sp_sync_plan_now',
                                    'plan_id' => (int) $post->ID,
                                ], admin_url('admin-post.php')),
                                'wp_sp_sync_plan_now_' . (int) $post->ID
                            );
                            ?>
                            <a href="<?php echo esc_url($syncUrl); ?>" class="button"><?php esc_html_e('Sync to Stripe now', 'wp-stripe-payments'); ?></a>
                        </p>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="wp-sp-card">
            <h3><?php esc_html_e('Preview', 'wp-stripe-payments'); ?></h3>
            <p><strong><?php echo wp_kses_post(wc_price((float) $meta['price'])); ?></strong> / <?php echo esc_html($billingInterval); ?></p>
            <p class="description"><?php esc_html_e('This is an admin preview of the plan amount formatting.', 'wp-stripe-payments'); ?></p>
        </div>
        <?php
    }

    public function saveMeta(int $postId, \WP_Post $post): void
    {
        if ($post->post_type !== PlanRepository::POST_TYPE) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! isset($_POST['wp_sp_plan_meta_nonce'])) {
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wp_sp_plan_meta_nonce'])), 'wp_sp_save_plan_meta')) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        $raw = isset($_POST['wp_sp_plan']) && is_array($_POST['wp_sp_plan'])
            ? wp_unslash($_POST['wp_sp_plan'])
            : [];

        $syncToStripe = isset($raw['sync_to_stripe']) && (string) $raw['sync_to_stripe'] === '1';
        unset($raw['sync_to_stripe']);

        $existing = $this->repository->getPlanMeta($postId);
        $sanitized = $this->repository->sanitizeMeta($raw);
        $merged = array_merge($existing, $sanitized);
        $this->repository->savePlanMeta($postId, $merged);

        if ($syncToStripe) {
            $syncResult = $this->planSyncService->syncPlan($postId);
            if (is_wp_error($syncResult)) {
                $this->storePlanNotice('error', sprintf(__('Plan sync failed: %s', 'wp-stripe-payments'), $syncResult->get_error_message()));
            } else {
                $this->storePlanNotice('success', __('Plan synced to Stripe successfully.', 'wp-stripe-payments'));
            }
        }
    }

    public function handleManualSync(): void
    {
        $planId = isset($_GET['plan_id']) ? (int) wp_unslash($_GET['plan_id']) : 0;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if ($planId <= 0 || ! wp_verify_nonce($nonce, 'wp_sp_sync_plan_now_' . $planId)) {
            wp_die(esc_html__('Security check failed.', 'wp-stripe-payments'));
        }

        if (! current_user_can('edit_post', $planId)) {
            wp_die(esc_html__('Insufficient permissions.', 'wp-stripe-payments'));
        }

        $result = $this->planSyncService->syncPlan($planId);
        if (is_wp_error($result)) {
            $this->storePlanNotice('error', sprintf(__('Plan sync failed: %s', 'wp-stripe-payments'), $result->get_error_message()));
        } else {
            $this->storePlanNotice('success', __('Plan synced to Stripe successfully.', 'wp-stripe-payments'));
        }

        wp_safe_redirect(add_query_arg([
            'post' => $planId,
            'action' => 'edit',
        ], admin_url('post.php')));
        exit;
    }

    public function renderPlanNotices(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen instanceof \WP_Screen || $screen->post_type !== PlanRepository::POST_TYPE) {
            return;
        }

        $notice = get_transient($this->noticeKey());
        if (! is_array($notice)) {
            return;
        }

        delete_transient($this->noticeKey());
        $type = sanitize_key((string) ($notice['type'] ?? 'success'));
        $message = sanitize_text_field((string) ($notice['message'] ?? ''));
        if ($message === '') {
            return;
        }

        echo '<div class="notice notice-' . esc_attr($type === 'error' ? 'error' : 'success') . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * @param array<string, string> $columns
     *
     * @return array<string, string>
     */
    public function addAdminColumns(array $columns): array
    {
        $columns['wp_sp_plan_price'] = __('Price', 'wp-stripe-payments');
        $columns['wp_sp_plan_interval'] = __('Interval', 'wp-stripe-payments');
        $columns['wp_sp_plan_status'] = __('Status', 'wp-stripe-payments');
        $columns['wp_sp_plan_sync'] = __('Stripe Sync', 'wp-stripe-payments');

        return $columns;
    }

    public function renderAdminColumns(string $column, int $postId): void
    {
        $meta = $this->repository->getPlanMeta($postId);

        switch ($column) {
            case 'wp_sp_plan_price':
                echo wp_kses_post(wc_price((float) $meta['price']));
                break;

            case 'wp_sp_plan_interval':
                echo esc_html($meta['billing_interval'] !== '' ? ucfirst($meta['billing_interval']) : '-');
                break;

            case 'wp_sp_plan_status':
                echo esc_html($meta['status'] !== '' ? ucfirst($meta['status']) : 'Inactive');
                break;

            case 'wp_sp_plan_sync':
                if ($meta['stripe_product_id'] !== '' && $meta['stripe_price_id'] !== '') {
                    echo '<span class="wp-sp-status-badge is-good">' . esc_html__('Connected', 'wp-stripe-payments') . '</span>';
                } else {
                    echo '<span class="wp-sp-status-badge is-warning">' . esc_html__('Not connected', 'wp-stripe-payments') . '</span>';
                }
                break;
        }
    }

    private function storePlanNotice(string $type, string $message): void
    {
        set_transient($this->noticeKey(), [
            'type' => $type,
            'message' => $message,
        ], 120);
    }

    private function noticeKey(): string
    {
        return 'wp_sp_plan_notice_' . get_current_user_id();
    }
}
