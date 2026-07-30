<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Role;

/**
 * Placeholder admin landing page. Widgets (today's sales, revenue,
 * low-stock alerts, latest orders, charts...) are built in the Admin
 * Dashboard module; this proves the admin auth + RBAC gate works.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $admin = Auth::user();
        $role = $admin !== null ? Role::find((int) $admin['role_id']) : null;

        $this->view('admin/dashboard/index', [
            'pageTitle' => 'Admin Dashboard | Kymera Collection',
            'roleName' => $role['name'] ?? 'Staff',
        ], 'admin/layouts/app');
    }
}
