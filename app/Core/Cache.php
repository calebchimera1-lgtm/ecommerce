<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal file-based cache (storage/cache/*.cache, one file per key,
 * a PHP-serialized [expires_at, value] pair). No daemon, no external
 * dependency - this app's server requirements (DEPLOYMENT.md section
 * 1) have deliberately stayed at "PHP + MySQL + a web server, nothing
 * else" through every module, and a cache layer shouldn't be the
 * thing that finally adds a Redis/Memcached requirement for what's
 * still a single-server-friendly app.
 *
 * Only ever wrap a read that's genuinely safe to serve slightly
 * stale. Never cache stock levels, cart contents, order status,
 * payment/payout figures, or anything else that must reflect the
 * database's actual current state on every request - caching those
 * would trade a real correctness guarantee for a performance win that
 * isn't worth it anywhere in this app.
 */
final class Cache
{
    private static ?string $directory = null;

    public static function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $cached = self::get($key);

        if ($cached !== null) {
            return $cached['value'];
        }

        $value = $callback();
        self::put($key, $value, $ttlSeconds);

        return $value;
    }

    public static function put(string $key, mixed $value, int $ttlSeconds): void
    {
        $entry = ['expires_at' => time() + $ttlSeconds, 'value' => $value];
        file_put_contents(self::path($key), serialize($entry), LOCK_EX);
    }

    public static function forget(string $key): void
    {
        $path = self::path($key);

        if (is_file($path)) {
            unlink($path);
        }
    }

    public static function flush(): void
    {
        foreach (glob(self::directory() . '/*.cache') ?: [] as $file) {
            unlink($file);
        }
    }

    /**
     * @return array{expires_at:int,value:mixed}|null
     */
    private static function get(string $key): ?array
    {
        $path = self::path($key);

        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        $entry = unserialize($raw, ['allowed_classes' => false]);

        if (!is_array($entry) || !isset($entry['expires_at'], $entry['value']) || $entry['expires_at'] <= time()) {
            return null;
        }

        return $entry;
    }

    private static function path(string $key): string
    {
        return self::directory() . '/' . sha1($key) . '.cache';
    }

    private static function directory(): string
    {
        if (self::$directory === null) {
            self::$directory = dirname(__DIR__, 2) . '/storage/cache';

            if (!is_dir(self::$directory)) {
                mkdir(self::$directory, 0755, true);
            }
        }

        return self::$directory;
    }
}
