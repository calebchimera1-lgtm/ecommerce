<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CSRF token generation and verification (synchronizer token pattern).
 */
final class Csrf
{
    private static function tokenName(): string
    {
        static $name = null;
        $name ??= (require dirname(__DIR__, 2) . '/config/config.php')['security']['csrf_token_name'];

        return $name;
    }

    public static function token(): string
    {
        Session::start();
        $name = self::tokenName();

        if (!Session::has($name)) {
            Session::set($name, bin2hex(random_bytes(32)));
        }

        return (string) Session::get($name);
    }

    public static function field(): string
    {
        $name = self::tokenName();
        $token = self::token();

        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    public static function verify(?string $submitted): bool
    {
        Session::start();
        $expected = Session::get(self::tokenName());

        return is_string($submitted) && is_string($expected) && $expected !== '' && hash_equals($expected, $submitted);
    }
}
