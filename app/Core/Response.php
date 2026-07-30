<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Small helper for emitting HTML/JSON/redirect responses with
 * consistent security headers.
 */
final class Response
{
    public static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header(
            "Content-Security-Policy: default-src 'self'; " .
            "img-src 'self' data: https:; " .
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
            "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
            "script-src 'self' https://cdn.jsdelivr.net https://js.stripe.com; " .
            "frame-src https://js.stripe.com;"
        );

        if ((bool) (require dirname(__DIR__, 2) . '/config/config.php')['app']['debug'] === false) {
            header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
        }
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    public static function abort(int $status, string $message = ''): never
    {
        http_response_code($status);
        $viewsPath = dirname(__DIR__) . "/Views/errors/{$status}.php";

        if (is_file($viewsPath)) {
            require $viewsPath;
        } else {
            echo htmlspecialchars($message !== '' ? $message : "Error {$status}", ENT_QUOTES, 'UTF-8');
        }

        exit;
    }
}
