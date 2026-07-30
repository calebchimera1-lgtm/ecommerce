<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Base gate for the admin panel: requires an authenticated user whose
 * role can view the dashboard at all. Feature-specific access (e.g.
 * "can manage products") is enforced per-route by PermissionMiddleware.
 */
final class AdminMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        if (!Auth::check()) {
            Session::flash('errors', ['auth' => ['Please log in to the admin panel.']]);
            Response::redirect('/admin/login');
        }

        if (!Auth::can('dashboard.view')) {
            Response::abort(403, 'You do not have access to the admin panel.');
        }

        return true;
    }
}
