<?php

namespace float\log;

use Yii;

/**
 * Logger Utility class.
 * 
 * @method static void debug(string $type, string $message, array $details = [])
 * @method static void info(string $type, string $message, array $details = [])
 * @method static void warn(string $type, string $message, array $details = [])
 * @method static void error(string $type, string $message, array $details = [])
 * @method static void fatal(string $type, string $message, array $details = [])
 */
class Logger
{
    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO  = 'info';
    public const LEVEL_WARN  = 'warn';
    public const LEVEL_ERROR = 'error';
    public const LEVEL_FATAL = 'fatal';

    protected static array $levels = [
        'debug' => self::LEVEL_DEBUG,
        'info'  => self::LEVEL_INFO,
        'warn'  => self::LEVEL_WARN,
        'error' => self::LEVEL_ERROR,
        'fatal' => self::LEVEL_FATAL,
    ];

    /**
     * Call static.
     *
     * @param string $name      - Functio name.
     * @param array  $arguments - Function arguments.
     *
     * @return void
     */
    public static function __callStatic(string $name, array $arguments): void
    {
        if (!array_key_exists($name, self::$levels)) {
            throw new \BadMethodCallException("Invalid log level: $name");
        }

        $type     = self::sanitizeString($arguments[0]) ?? 'GENERAL';          // Log type eg. RATE_LIMIT, EMAIL_VERIFICATION
        $category = self::sanitizeString($arguments[1]) ?? 'GENERAL_CATEGORY'; // Log category eg. VERIFICATION_MISMATCH
        $message  = self::sanitizeString($arguments[2]) ?? '';                 // Message string.
        $details  = self::sanitizeArray($arguments[3] ?? []);                  // Array with details.

        self::log(self::$levels[$name], $type, $category, $message, $details);
    }

    /**
     * Log function.
     *
     * @param string $status   - Log status type.
     * @param string $type     - Log type eg. RATE_LIMIT, EMAIL_VERIFICATION
     * @param string $category - Log category eg. VERIFICATION_MISMATCH, VERIFICATION_MISMATCH
     * @param string $message  - Message string.
     * @param array  $details  - Array with details.
     *
     * @return void
     */
    private static function log(string $status, string $type, string $category, string $message, array $details): void
    {
        try {
            $companyId = Yii::$app->user->identity->company_id ?? null;
            $accountId = Yii::$app->user->id ?? null;

            $log = [
                'log_status'   => $status,
                'log_type'     => strtoupper($type),
                'log_category' => strtoupper($category),
                'message'      => $message,
                'details'      => $details,
                'service'      => getenv('DD_SERVICE') ?: '',
                'env'          => getenv('DD_ENV') ?: 'lws',
                'version'      => getenv('DD_VERSION') ?: null,
                'pod'          => getenv('HOSTNAME') ?: php_uname('n'),
                'company_id'   => $companyId,
                'account_id'   => $accountId,
                'dd'           => [
                    'trace_id' => function_exists('\\DDTrace\\logs_correlation_trace_id')
                        ? \DDTrace\logs_correlation_trace_id()
                        : null,
                ],
                'cloud_trace'  => $_SERVER['HTTP_X_CLOUD_TRACE_CONTEXT'] ?? null,
                'network'      => [
                    'client' => [
                        'ip' => self::getIpAddress(),
                    ],
                ],
            ];

            error_log(json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            // Graceful fallback.
            error_log("Logger failed: " . $e->getMessage());
        }
    }

    /**
     * Recursively sanitize mixed data into a safe array suitable for logging.
     *
     * @param mixed $data - Mixed data.
     *
     * @return array|string
     */
    private static function sanitizeArray($data)
    {
        if (is_object($data)) {
            return [
                'object' => method_exists($data, '__toString')
                    ? (string) $data
                    : get_class($data),
            ];
        }

        if (is_scalar($data) || is_null($data)) {
            // Wrap scalar or null in 'value' key for clarity
            return ['value' => $data];
        }

        if (is_resource($data)) {
            return ['value' => 'resource'];
        }

        if (!is_array($data)) {
            return ['invalid_type' => gettype($data)];
        }

        // Recursively sanitize array elements
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sanitizeArray($value);
            } elseif (is_object($value)) {
                if (method_exists($value, '__toString')) {
                    $stringValue = (string) $value;
                    // Mask any emails found within the string
                    $data[$key] = self::maskEmailsInString($stringValue);
                } else {
                    $data[$key] = get_class($value);
                }
            } elseif (is_resource($value)) {
                $data[$key] = 'resource';
            } elseif (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                // Detect and mask email
                $data[$key] = self::maskEmail($value);
                if ($key === 'email') {
                    [$local, $domain] = explode('@', $value);
                    $data['email_domain'] = $domain;
                }
            } else {
                // scalar or null, safe to keep as is
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Mask emails in string.
     *
     * @param string $text - Text.
     *
     * @return string
     */
    private static function maskEmailsInString(string $text): string
    {
        return preg_replace_callback(
            '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i',
            fn($matches) => self::maskEmail($matches[0]),
            $text
        );
    }

    /**
     * Mask email.
     *
     * @param string $email - Email to be masked.
     *
     * @return string
     */
    private static function maskEmail(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        [$local, $domain] = explode('@', $email);

        if (mb_strlen($local) < 3) {
            // Too short to mask meaningfully
            return $email;
        }

        $first = mb_substr($local, 0, 1);
        $last  = mb_substr($local, -1);

        return $first . '***' . $last . '@' . $domain;
    }

    /**
     * Sanitize string.
     *
     * @param mixed $input - Input received.
     *
     * @return string
     */
    private static function sanitizeString(mixed $input): string
    {
        if (is_string($input)) {
            $str = $input;
        } elseif (is_object($input)) {
            // If object has __toString, use it, else class name
            $str = method_exists($input, '__toString') ? (string) $input : '[object ' . get_class($input) . ']';
        } elseif (is_array($input)) {
            // Convert array to JSON or just say it's an array
            $str = '[array] ' . json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (is_null($input)) {
            $str = 'null';
        } else {
            // For scalar (int, float, bool), convert to string
            $str = (string) $input;
        }

        // Mask all emails in Strings.
        $str = self::maskEmailsInString($str);
        // Remove URLs (http, https, www)
        $str = preg_replace('/\b(?:https?:\/\/|www\.)\S+/i', '', $str);
        
        // Now sanitize string as before
        $str = preg_replace('/[^\PC\s]/u', '', $str);
        $str = trim($str);

        $maxLength = 250;
        if (mb_strlen($str) > $maxLength) {
            $str = mb_substr($str, 0, $maxLength) . '... [truncated]';
        }

        return $str;
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
