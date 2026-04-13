<?php

namespace WPStripePayments\Utils;

use WPStripePayments\Admin\Settings;

class Logger
{
    private const LOG_OPTION_KEY = 'wp_stripe_payments_logs';
    private const MAX_STORED_LOGS = 200;

    private ?\WC_Logger $logger = null;

    private string $source = 'wp-stripe-payments';

    public function __construct()
    {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            if ($logger instanceof \WC_Logger) {
                $this->logger = $logger;
            }
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public static function maxStoredLogs(): int
    {
        return self::MAX_STORED_LOGS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getStoredLogs(string $level = ''): array
    {
        $logs = get_option(self::LOG_OPTION_KEY, []);
        $logs = is_array($logs) ? $logs : [];

        $normalizedLevel = sanitize_key($level);
        if ($normalizedLevel === '') {
            return $logs;
        }

        return array_values(array_filter($logs, static function ($row) use ($normalizedLevel): bool {
            return sanitize_key((string) ($row['level'] ?? 'info')) === $normalizedLevel;
        }));
    }

    public static function clearStoredLogs(): void
    {
        update_option(self::LOG_OPTION_KEY, [], false);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (! Settings::isDebugLoggingEnabled() && $level === 'info') {
            return;
        }

        $wcContext = array_merge(['source' => $this->source], $context);

        if ($this->logger !== null) {
            $this->logger->log($level, $message, $wcContext);
        } else {
            error_log(sprintf('[%s] %s', strtoupper($level), $message));
        }

        $this->storeLog($level, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function storeLog(string $level, string $message, array $context): void
    {
        $logs = self::getStoredLogs();

        $logs[] = [
            'time' => function_exists('wp_date') ? wp_date('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        if (count($logs) > self::MAX_STORED_LOGS) {
            $logs = array_slice($logs, -1 * self::MAX_STORED_LOGS);
        }

        update_option(self::LOG_OPTION_KEY, $logs, false);
    }
}
