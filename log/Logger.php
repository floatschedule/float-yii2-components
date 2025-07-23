<?php

namespace float\log;

use Yii;

/**
 * Logger Utility class.
 */
class Logger
{
    /**
     * Debug.
     *
     * @param string $type    - Log type eg. RATE_LIMIT, EMAIL_VERIFICATION
     * @param string $message - Message string.
     * @param array  $details - Array with details.
     *
     * @return void
     */
    public static function debug(string $type, string $message, array $details = []): void
    {
        self::log('debug', $type, $message, $details);
    }

    /**
     * Info.
     *
     * @param string $type    - Log type eg. RATE_LIMIT, EMAIL_VERIFICATION
     * @param string $message - Message string.
     * @param array  $details - Array with details.
     *
     * @return void
     */
    public static function info(string $type, string $message, array $details = []): void
    {
        self::log('info', $type, $message, $details);
    }

    /**
     * Warn.
     *
     * @param string $type    - Log type eg. RATE_LIMIT, EMAIL_VERIFICATION
     * @param string $message - Message string.
     * @param array  $details - Array with details.
     *
     * @return void
     */
    public static function warn(string $type, string $message, array $details = []): void
    {
        self::log('warn', $type, $message, $details);
    }

    /**
     * Error.
     *
     * @param string $type    - Log type eg. RATE_LIMIT, EMAIL_VERIFICATION
     * @param string $message - Message string.
     * @param array  $details - Array with details.
     *
     * @return void
     */
    public static function error(string $type, string $message, array $details = []): void
    {
        self::log('error', $type, $message, $details);
    }

    /**
     * Fatal.
     *
     * @param string $type    - Log type eg. RATE_LIMIT, EMAIL_VERIFICATION
     * @param string $message - Message string.
     * @param array  $details - Array with details.
     *
     * @return void
     */
    public static function fatal(string $type, string $message, array $details = []): void
    {
        self::log('fatal', $type, $message, $details);
    }

    /**
     * Log function.
     *
     * @param string $status  - Log status type.
     * @param string $type    - Log type eg. RATE_LIMIT, EMAIL_VERIFICATION
     * @param string $message - Message string.
     * @param array  $details - Array with details.
     *
     * @return void
     */
    private static function log(string $status, string $type, string $message, array $details): void
    {
        $companyId = Yii::$app->user->identity->company_id ?? null;
        $accountId = Yii::$app->user->id ?? null;

        $log = [
            'log_status' => $status,
            'log_type'   => strtoupper($type),
            'message'    => $message,
            'details'    => $details,
            'service'    => getenv('DD_SERVICE') ?: '',
            'env'        => getenv('DD_ENV') ?: 'local',
            'version'    => getenv('DD_VERSION') ?: null,
            'pod'        => getenv('HOSTNAME') ?: php_uname('n'),
            'company_id' => $companyId,
            'account_id' => $accountId,
            'dd' => [
                'trace_id' => function_exists('\\DDTrace\\logs_correlation_trace_id')
                    ? \DDTrace\logs_correlation_trace_id()
                    : null,
            ],
            'cloud_trace' => $_SERVER['HTTP_X_CLOUD_TRACE_CONTEXT'] ?? null,
            'network' => [
                'client' => [
                    'ip' => self::getIpAddress(),
                ],
            ],
        ];

        error_log(json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Get IP Address.
     *
     * @return string
     */
    private static function getIpAddress(): string
    {
        foreach ([
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ] as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        return 'unknown';
    }
}
