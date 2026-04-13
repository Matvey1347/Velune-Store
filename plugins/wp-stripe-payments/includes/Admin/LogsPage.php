<?php

namespace WPStripePayments\Admin;

use WPStripePayments\Utils\Logger;

class LogsPage
{
    public function maybeHandleActions(): void
    {
        if (! is_admin()) {
            return;
        }

        if (! isset($_GET['page']) || $_GET['page'] !== 'wp-stripe-payments-logs') {
            return;
        }

        if (! isset($_GET['wp_sp_action'])) {
            return;
        }

        if (! AdminContext::canManage()) {
            return;
        }

        $action = sanitize_key((string) wp_unslash($_GET['wp_sp_action']));
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field((string) wp_unslash($_GET['_wpnonce'])) : '';

        if (! wp_verify_nonce($nonce, 'wp_sp_logs_action_' . $action)) {
            wp_die(esc_html__('Security check failed.', 'wp-stripe-payments'));
        }

        if ($action === 'clear') {
            Logger::clearStoredLogs();
            wp_safe_redirect(add_query_arg([
                'page' => 'wp-stripe-payments-logs',
                'logs_cleared' => '1',
            ], admin_url('admin.php')));
            exit;
        }

        if ($action === 'refresh') {
            wp_safe_redirect(add_query_arg([
                'page' => 'wp-stripe-payments-logs',
                'refreshed' => '1',
            ], admin_url('admin.php')));
            exit;
        }
    }

    public function render(): void
    {
        if (! AdminContext::canManage()) {
            AdminContext::denyAccess();
        }

        $level = isset($_GET['level']) ? sanitize_key((string) wp_unslash($_GET['level'])) : '';
        $search = isset($_GET['s']) ? sanitize_text_field((string) wp_unslash($_GET['s'])) : '';

        $logs = Logger::getStoredLogs($level);
        $logs = array_reverse($logs);

        if ($search !== '') {
            $logs = array_values(array_filter($logs, static function ($entry) use ($search): bool {
                $haystack = (string) ($entry['message'] ?? '');
                $context = ! empty($entry['context']) && is_array($entry['context'])
                    ? (string) wp_json_encode($entry['context'])
                    : '';

                return stripos($haystack, $search) !== false || stripos($context, $search) !== false;
            }));
        }

        $refreshUrl = wp_nonce_url(
            add_query_arg([
                'page' => 'wp-stripe-payments-logs',
                'wp_sp_action' => 'refresh',
            ], admin_url('admin.php')),
            'wp_sp_logs_action_refresh'
        );

        $clearUrl = wp_nonce_url(
            add_query_arg([
                'page' => 'wp-stripe-payments-logs',
                'wp_sp_action' => 'clear',
            ], admin_url('admin.php')),
            'wp_sp_logs_action_clear'
        );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(AdminContext::BRAND_NAME . ' ' . __('Logs', 'wp-stripe-payments')); ?></h1>
            <p class="wp-sp-page-intro"><?php esc_html_e('Inspect the newest events first. Use filters to narrow down warnings/errors and clear logs only when required.', 'wp-stripe-payments'); ?></p>

            <?php if (isset($_GET['logs_cleared']) && $_GET['logs_cleared'] === '1') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Logs cleared successfully.', 'wp-stripe-payments'); ?></p></div>
            <?php endif; ?>

            <div class="notice notice-info"><p><?php printf(esc_html__('Local log storage keeps up to %d records. Full structured logs are also sent to WooCommerce logger.', 'wp-stripe-payments'), Logger::maxStoredLogs()); ?></p></div>

            <div class="wp-sp-card wp-sp-table-tools">
                <a href="<?php echo esc_url($refreshUrl); ?>" class="button"><?php esc_html_e('Refresh', 'wp-stripe-payments'); ?></a>
                <a href="<?php echo esc_url($clearUrl); ?>" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Clear all local plugin logs?', 'wp-stripe-payments')); ?>');"><?php esc_html_e('Clear Logs', 'wp-stripe-payments'); ?></a>
            </div>

            <div class="wp-sp-card" style="margin-bottom:12px;">
                <form method="get">
                    <input type="hidden" name="page" value="wp-stripe-payments-logs" />
                    <div class="wp-sp-form-grid">
                        <div>
                            <label for="wp-sp-log-level"><strong><?php esc_html_e('Level', 'wp-stripe-payments'); ?></strong></label>
                            <select id="wp-sp-log-level" name="level">
                                <option value=""><?php esc_html_e('All levels', 'wp-stripe-payments'); ?></option>
                                <option value="info" <?php selected($level, 'info'); ?>>INFO</option>
                                <option value="warning" <?php selected($level, 'warning'); ?>>WARNING</option>
                                <option value="error" <?php selected($level, 'error'); ?>>ERROR</option>
                            </select>
                        </div>
                        <div>
                            <label for="wp-sp-log-search"><strong><?php esc_html_e('Search', 'wp-stripe-payments'); ?></strong></label>
                            <input id="wp-sp-log-search" type="search" class="regular-text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('message, id, context', 'wp-stripe-payments'); ?>" />
                        </div>
                    </div>
                    <p>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Apply Filters', 'wp-stripe-payments'); ?></button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-stripe-payments-logs')); ?>" class="button"><?php esc_html_e('Reset', 'wp-stripe-payments'); ?></a>
                    </p>
                </form>
            </div>

            <?php if (empty($logs)) : ?>
                <div class="wp-sp-empty">
                    <p><?php esc_html_e('No logs match current filters.', 'wp-stripe-payments'); ?></p>
                </div>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                    <tr>
                        <th><?php esc_html_e('Time', 'wp-stripe-payments'); ?></th>
                        <th><?php esc_html_e('Level', 'wp-stripe-payments'); ?></th>
                        <th><?php esc_html_e('Message', 'wp-stripe-payments'); ?></th>
                        <th><?php esc_html_e('Context', 'wp-stripe-payments'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $entry) : ?>
                        <?php
                        $entryLevel = sanitize_key((string) ($entry['level'] ?? 'info'));
                        $context = ! empty($entry['context']) && is_array($entry['context']) ? $entry['context'] : [];
                        ?>
                        <tr>
                            <td><?php echo esc_html((string) ($entry['time'] ?? '')); ?></td>
                            <td><span class="wp-sp-status-badge status-<?php echo esc_attr($entryLevel); ?>"><?php echo esc_html(strtoupper($entryLevel)); ?></span></td>
                            <td><?php echo esc_html((string) ($entry['message'] ?? '')); ?></td>
                            <td>
                                <?php if (! empty($context)) : ?>
                                    <details class="wp-sp-collapsible">
                                        <summary><?php esc_html_e('View context', 'wp-stripe-payments'); ?></summary>
                                        <pre class="wp-sp-context-code"><?php echo esc_html((string) wp_json_encode($context, JSON_PRETTY_PRINT)); ?></pre>
                                    </details>
                                <?php else : ?>
                                    <span class="description"><?php esc_html_e('None', 'wp-stripe-payments'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
