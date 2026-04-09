<?php

namespace WPStripePayments\Utils;

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

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getStoredLogs(): array
    {
        $logs = get_option(self::LOG_OPTION_KEY, []);
        return is_array($logs) ? $logs : [];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
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
