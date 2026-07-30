<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Gates a route behind a specific permission slug from the
 * role_permissions matrix, e.g.
 *   $router->get('/admin/settings', [Controller::class, 'index'],
 *       [[PermissionMiddleware::class, 'settings.manage']]);
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $permission)
    {
    }

    public function handle(Request $request): bool
    {
        if (!Auth::check()) {
            Session::flash('errors', ['auth' => ['Please log in to the admin panel.']]);
            Response::redirect('/admin/login');
        }

        if (!Auth::can($this->permission)) {
            Response::abort(403, 'You do not have permission to access this page.');
        }

        return true;
    }
}
