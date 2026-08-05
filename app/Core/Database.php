<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Single shared PDO connection (lazy singleton). All data access goes
 * through PDO prepared statements - no raw string interpolation of
 * user input is permitted anywhere in the codebase.
 */
final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $config = require dirname(__DIR__, 2) . '/config/database.php';

            $dsn = sprintf(
                '%s:host=%s;port=%d;dbname=%s;charset=%s',
                $config['driver'],
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            try {
                self::$connection = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $config['options']
                );

                // Align MySQL's session clock with PHP's configured
                // app.timezone. Without this, MySQL's SYSTEM timezone
                // (whatever the DB host's OS is set to - often UTC, even
                // when APP_TIMEZONE is e.g. Africa/Nairobi) can silently
                // disagree with every PHP-computed timestamp written to
                // the DB. That mismatch doesn't just look wrong in
                // admin screens - it breaks any `... <= NOW()` /
                // `... >= NOW()` query comparing a PHP-set timestamp
                // against MySQL's own clock (password reset token
                // expiry, coupon validity windows, scheduled blog post
                // publishing), silently rejecting things that should
                // already be valid, or accepting things that shouldn't
                // be yet, for as long as the offset lasts.
                $offset = (new \DateTime('now', new \DateTimeZone($config['timezone'] ?? 'UTC')))->format('P');
                self::$connection->exec("SET time_zone = '{$offset}'");
            } catch (PDOException $e) {
                Logger::error('Database connection failed: ' . $e->getMessage());
                throw new RuntimeException('Unable to connect to the database.', previous: $e);
            }
        }

        return self::$connection;
    }

    private function __construct()
    {
    }
}
