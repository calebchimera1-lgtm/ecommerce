<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

/**
 * Keeps an already-authenticated staff member off the admin login
 * page, redirecting them straight to the dashboard.
 */
final class GuestAdminMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        if (Auth::check() && Auth::can('dashboard.view')) {
            Response::redirect('/admin/dashboard');
        }

        return true;
    }
}
