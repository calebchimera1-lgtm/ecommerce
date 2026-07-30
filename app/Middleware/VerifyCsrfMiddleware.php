<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

/**
 * Enforces the synchronizer-token CSRF pattern on every state-changing
 * request. Applied per-route (not globally) so JSON/API routes can use
 * a different protection strategy later without fighting this one.
 */
final class VerifyCsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->input((string) config('security.csrf_token_name'));

            if (!Csrf::verify(is_string($token) ? $token : null)) {
                Response::abort(419, 'Your session has expired. Please refresh the page and try again.');
            }
        }

        return true;
    }
}
