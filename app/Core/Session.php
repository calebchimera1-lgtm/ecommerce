<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Secure session bootstrap and typed accessors. Session cookies are
 * HttpOnly, SameSite=Lax, and Secure whenever the request is HTTPS.
 * The session ID is regenerated on privilege changes (login/logout)
 * to prevent session fixation.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $isHttps = (($_SERVER['HTTPS'] ?? '') !== '') || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        session_set_cookie_params([
            'lifetime' => $config['session']['lifetime'] * 60,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name($config['session']['name']);
        session_start();

        self::$started = true;

        // Basic session integrity check: bind to user agent to make
        // stolen session cookies harder to reuse from a different client.
        $uaHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        if (!isset($_SESSION['_ua'])) {
            $_SESSION['_ua'] = $uaHash;
        } elseif (!hash_equals($_SESSION['_ua'], $uaHash)) {
            self::destroy();
            session_start();
            $_SESSION['_ua'] = $uaHash;
        }
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }
}
