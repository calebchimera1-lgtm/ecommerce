<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;

/**
 * Permission matrix editor for existing roles. Creating brand-new
 * roles is deliberately out of scope here - the four roles seeded in
 * Module 1 (Super Admin, Manager, Support, Customer) cover the brief's
 * staff structure, and a "new role" flow would need its own slug/name
 * validation and a decision about what a blank role starts with,
 * which isn't needed to make RBAC itself fully usable.
 */
final class RoleController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('admin/roles/index', [
            'pageTitle' => 'Roles & Permissions | Kymera Collection Admin',
            'roles' => Role::all('name', 'ASC'),
        ], 'admin/layouts/app');
    }

    public function edit(Request $request): void
    {
        $role = $this->loadRole($request);

        $this->view('admin/roles/edit', [
            'pageTitle' => 'Edit ' . $role['name'] . ' Permissions | Kymera Collection Admin',
            'role' => $role,
            'permissionGroups' => Permission::allGroupedByModule(),
            'grantedSlugs' => Role::permissionSlugs((int) $role['id']),
        ], 'admin/layouts/app');
    }

    public function updatePermissions(Request $request): void
    {
        $role = $this->loadRole($request);

        if ($role['slug'] === 'customer') {
            Session::flash('errors', ['role' => ['The customer role does not use the admin permission matrix.']]);
            $this->back();
        }

        $before = Role::permissionSlugs((int) $role['id']);
        $permissionIds = array_map('intval', (array) $request->input('permissions', []));

        Role::syncPermissions((int) $role['id'], $permissionIds);

        $after = Role::permissionSlugs((int) $role['id']);

        AuditLog::record(Auth::id(), 'role.permissions_updated', 'role', (int) $role['id'], [
            'permissions' => $before,
        ], [
            'permissions' => $after,
        ]);

        Session::flash('success', 'Permissions updated for ' . $role['name'] . '.');
        $this->redirect('/admin/roles/' . (int) $role['id'] . '/edit');
    }

    private function loadRole(Request $request): array
    {
        $id = (int) $request->route('id');
        $role = Role::find($id);

        if ($role === null) {
            Response::abort(404, 'Role not found.');
        }

        return $role;
    }
}
