<?php

namespace WPStripePayments\Subscriptions;

class PlanService
{
    private PlanRepository $repository;

    public function __construct(?PlanRepository $repository = null)
    {
        $this->repository = $repository ?? new PlanRepository();
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('add_meta_boxes', [$this, 'registerMetaBoxes']);
        add_action('save_post_' . PlanRepository::POST_TYPE, [$this, 'saveMeta'], 10, 2);
    }

    public function registerPostType(): void
    {
        $labels = [
            'name' => __('Subscriptions', 'wp-stripe-payments'),
            'singular_name' => __('Subscription Plan', 'wp-stripe-payments'),
            'menu_name' => __('Subscriptions', 'wp-stripe-payments'),
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
            'show_in_menu' => 'wp-stripe-payments',
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
        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row"><label for="wp_sp_description"><?php esc_html_e('Description', 'wp-stripe-payments'); ?></label></th>
                <td><textarea class="large-text" rows="4" id="wp_sp_description" name="wp_sp_plan[description]"><?php echo esc_textarea($meta['description']); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row"><label for="wp_sp_image"><?php esc_html_e('Image URL', 'wp-stripe-payments'); ?></label></th>
                <td><input class="regular-text" type="url" id="wp_sp_image" name="wp_sp_plan[image]" value="<?php echo esc_attr($meta['image']); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="wp_sp_price"><?php esc_html_e('Price', 'wp-stripe-payments'); ?></label></th>
                <td><input class="regular-text" type="text" id="wp_sp_price" name="wp_sp_plan[price]" value="<?php echo esc_attr($meta['price']); ?>" placeholder="9.99" /></td>
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
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wp_sp_stripe_product_id"><?php esc_html_e('Stripe Product ID', 'wp-stripe-payments'); ?></label></th>
                <td><input class="regular-text" type="text" id="wp_sp_stripe_product_id" name="wp_sp_plan[stripe_product_id]" value="<?php echo esc_attr($meta['stripe_product_id']); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="wp_sp_stripe_price_id"><?php esc_html_e('Stripe Price ID', 'wp-stripe-payments'); ?></label></th>
                <td><input class="regular-text" type="text" id="wp_sp_stripe_price_id" name="wp_sp_plan[stripe_price_id]" value="<?php echo esc_attr($meta['stripe_price_id']); ?>" /></td>
            </tr>
            </tbody>
        </table>
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

        $sanitized = $this->repository->sanitizeMeta($raw);
        $this->repository->savePlanMeta($postId, $sanitized);
    }
}
