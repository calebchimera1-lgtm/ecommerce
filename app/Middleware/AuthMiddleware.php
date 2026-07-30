<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Requires an authenticated customer session; redirects to /login
 * otherwise. Admin-specific access control is a separate middleware
 * added in the Admin authentication module.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        if (!Auth::check()) {
            Session::flash('errors', ['auth' => ['Please log in to continue.']]);
            Response::redirect('/login');
        }

        return true;
    }
}
