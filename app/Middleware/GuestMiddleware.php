<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

/**
 * Blocks already-authenticated users from auth pages (login, register,
 * password reset) by redirecting them to their account dashboard.
 */
final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        if (Auth::check()) {
            Response::redirect('/account');
        }

        return true;
    }
}
