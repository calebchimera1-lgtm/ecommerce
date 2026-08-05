<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Role;
use App\Models\Vendor;

/**
 * Gate for the vendor dashboard. Vendors are a separate account type
 * from staff, not a limited admin role - there's no permission matrix
 * to check (PermissionMiddleware/Auth::can() are admin-panel concepts),
 * just: is this an authenticated 'vendor'-role user whose vendor
 * profile is currently 'approved'? A pending/rejected/suspended
 * vendor is blocked out just as firmly as a logged-out visitor, since
 * none of those states should have working dashboard access.
 */
final class VendorMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        if (!Auth::check()) {
            Session::flash('errors', ['auth' => ['Please log in to your vendor account.']]);
            Response::redirect('/vendor/login');
        }

        $user = Auth::user();
        $role = $user !== null ? Role::find((int) $user['role_id']) : null;

        if ($role === null || $role['slug'] !== 'vendor') {
            Response::abort(403, 'This area is for vendor accounts only.');
        }

        $vendor = Vendor::findByUserId((int) $user['id']);

        if ($vendor === null || $vendor['status'] !== 'approved') {
            Response::abort(403, 'Your vendor account is not currently approved.');
        }

        return true;
    }
}
