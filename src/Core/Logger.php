<?php

declare(strict_types=1);

namespace App\Core;

class Logger
{
    private static ?string $logFile = null;

    public static function init(): void
    {
        if (self::$logFile === null) {
            $dir = dirname(__DIR__, 2) . '/storage/logs';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            self::$logFile = $dir . '/app.log';
        }
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        self::init();
        $timestamp = date('Y-m-d H:i:s');
        $sanitizedContext = self::sanitize($context);
        $contextStr = !empty($sanitizedContext) ? ' ' . json_encode($sanitizedContext, JSON_UNESCAPED_SLASHES) : '';
        $line = sprintf("[%s] [%s] %s%s\n", $timestamp, strtoupper($level), $message, $contextStr);

        file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('DEBUG', $message, $context);
    }

    public static function audit(string $action, string $entityType, ?string $entityId = null, ?array $details = null, ?int $userId = null): void
    {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            if ($userId === null && isset($_SESSION['user_id'])) {
                $userId = (int) $_SESSION['user_id'];
            }

            $sanitizedDetails = $details ? json_encode(self::sanitize($details)) : null;

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'ip_address' => $ip,
                'details' => $sanitizedDetails,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            self::error('Audit log failed: ' . $e->getMessage());
        }
    }

    public static function sanitize(mixed $data): mixed
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $k => $v) {
                $lowerKey = strtolower((string) $k);
                if (str_contains($lowerKey, 'token') ||
                    str_contains($lowerKey, 'password') ||
                    str_contains($lowerKey, 'secret') ||
                    str_contains($lowerKey, 'auth') ||
                    str_contains($lowerKey, 'key')) {
                    $sanitized[$k] = '***REDACTED***';
                } else {
                    $sanitized[$k] = self::sanitize($v);
                }
            }
            return $sanitized;
        }

        return $data;
    }

    public static function getRecentLogs(int $lines = 100): array
    {
        self::init();
        if (!file_exists(self::$logFile)) {
            return [];
        }

        $all = file(self::$logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($all === false) {
            return [];
        }

        return array_slice(array_reverse($all), 0, $lines);
    }
}
