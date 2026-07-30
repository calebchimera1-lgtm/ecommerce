<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal file-based logger. Writes daily rotated log files under
 * storage/logs. Kept dependency-free on purpose.
 */
final class Logger
{
    private const LEVELS = ['debug', 'info', 'warning', 'error', 'critical'];

    public static function log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);

        if (!in_array($level, self::LEVELS, true)) {
            $level = 'info';
        }

        $dir = dirname(__DIR__, 2) . '/storage/logs';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $dir . '/' . date('Y-m-d') . '.log';
        $line = sprintf(
            '[%s] %s: %s %s%s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context !== [] ? json_encode($context, JSON_UNESCAPED_SLASHES) : '',
            PHP_EOL
        );

        error_log($line, 3, $file);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log('critical', $message, $context);
    }
}
