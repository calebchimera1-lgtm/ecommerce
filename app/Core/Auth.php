<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Authentication state for the storefront. Session-based for normal
 * requests, with an optional long-lived "remember me" cookie that
 * re-establishes the session on a later visit.
 *
 * The remember cookie stores "{user_id}|{plain_token}"; only a SHA-256
 * hash of the token is persisted in `users.remember_token`, so a
 * database leak alone is not enough to forge a valid cookie.
 */
final class Auth
{
    private const SESSION_KEY = 'auth_user_id';
    private const REMEMBER_COOKIE = 'kymera_remember';
    private const REMEMBER_DAYS = 30;

    public static function attemptResumeFromCookie(): void
    {
        if (self::check() || !isset($_COOKIE[self::REMEMBER_COOKIE])) {
            return;
        }

        [$userId, $token] = array_pad(explode('|', (string) $_COOKIE[self::REMEMBER_COOKIE], 2), 2, null);

        if ($userId === null || $token === null || !ctype_digit($userId)) {
            return;
        }

        $user = User::find((int) $userId);

        if ($user === null || $user['remember_token'] === null) {
            return;
        }

        if (!hash_equals($user['remember_token'], hash('sha256', $token))) {
            return;
        }

        self::login($user);
    }

    public static function login(array $user, bool $remember = false): void
    {
        Session::regenerate();
        Session::set(self::SESSION_KEY, (int) $user['id']);
        Session::set('auth_role_id', (int) $user['role_id']);

        if ($remember) {
            self::rememberUser((int) $user['id']);
        }
    }

    private static function rememberUser(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        User::update($userId, ['remember_token' => hash('sha256', $token)]);

        setcookie(self::REMEMBER_COOKIE, $userId . '|' . $token, [
            'expires' => time() + self::REMEMBER_DAYS * 86400,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => self::isHttps(),
        ]);
    }

    public static function logout(): void
    {
        $userId = self::id();

        if ($userId !== null) {
            User::update($userId, ['remember_token' => null]);
        }

        if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
            setcookie(self::REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
        }

        Session::destroy();
        Session::start();
        Session::regenerate();
    }

    public static function check(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public static function id(): ?int
    {
        $id = Session::get(self::SESSION_KEY);

        return $id === null ? null : (int) $id;
    }

    public static function user(): ?array
    {
        $id = self::id();

        return $id === null ? null : User::find($id);
    }

    /**
     * Whether the currently logged-in user's role has the given
     * permission slug (e.g. 'products.manage'). False when logged out.
     */
    public static function can(string $permissionSlug): bool
    {
        $user = self::user();

        return $user !== null && self::roleCan((int) $user['role_id'], $permissionSlug);
    }

    /**
     * Standalone role -> permission check, independent of the current
     * session. Used during login (before a session exists) to decide
     * whether a role is allowed into the admin panel at all.
     */
    public static function roleCan(int $roleId, string $permissionSlug): bool
    {
        static $cache = [];

        if (!isset($cache[$roleId])) {
            $stmt = Database::connection()->prepare(
                'SELECT p.slug FROM role_permissions rp
                 JOIN permissions p ON p.id = rp.permission_id
                 WHERE rp.role_id = :role_id'
            );
            $stmt->execute(['role_id' => $roleId]);
            $cache[$roleId] = array_column($stmt->fetchAll(), 'slug');
        }

        return in_array($permissionSlug, $cache[$roleId], true);
    }

    private static function isHttps(): bool
    {
        return (($_SERVER['HTTPS'] ?? '') !== '') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    }
}
