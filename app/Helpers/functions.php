<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;

/**
 * Global helper functions, autoloaded via composer.json's "files"
 * directive. Kept intentionally small - most logic belongs in
 * App\Core classes; these are thin convenience wrappers used from
 * views and controllers.
 */

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $config = null;
        $config ??= require dirname(__DIR__, 2) . '/config/config.php';

        $segments = explode('.', $key);
        $value = $config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return rtrim((string) config('app.url'), '/') . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        static $values = null;
        // Session::getFlash() removes the key from the session on first
        // read; cache it locally so repeated old() calls within the same
        // request (one per form field) still see the values.
        $values ??= Session::getFlash('old', []);

        return $values[$key] ?? $default;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('money')) {
    function money(float|string $amount, string $currency = 'USD'): string
    {
        $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'KES' => 'KSh '];
        $symbol = $symbols[$currency] ?? $currency . ' ';

        return $symbol . number_format((float) $amount, 2);
    }
}
