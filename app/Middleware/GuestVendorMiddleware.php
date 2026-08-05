<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Models\Role;
use App\Models\Vendor;

/**
 * Keeps an already-authenticated, approved vendor off the vendor
 * login page, redirecting them straight to their dashboard.
 */
final class GuestVendorMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        $user = Auth::check() ? Auth::user() : null;

        if ($user !== null) {
            $role = Role::find((int) $user['role_id']);
            $vendor = $role !== null && $role['slug'] === 'vendor' ? Vendor::findByUserId((int) $user['id']) : null;

            if ($vendor !== null && $vendor['status'] === 'approved') {
                Response::redirect('/vendor/dashboard');
            }
        }

        return true;
    }
}
