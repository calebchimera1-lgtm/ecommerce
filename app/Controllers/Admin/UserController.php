<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Uuid;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;

/**
 * Staff/admin account management. Deliberately has no hard delete -
 * `users.id` is referenced by `orders.user_id` with ON DELETE RESTRICT
 * (among others), so removing a staff account outright would either
 * fail against a staff member who ever placed a test/demo order, or
 * require cascading changes far outside this module's scope. Instead,
 * staff accounts are deactivated (status = 'inactive'), which already
 * blocks admin login via the same status check used for customers.
 */
final class UserController extends Controller
{
    private const PER_PAGE = 20;
    private const STATUSES = ['active', 'inactive', 'banned'];

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));

        $this->view('admin/users/index', [
            'pageTitle' => 'Staff Users | Kymera Collection Admin',
            'users' => User::paginateStaff($page, self::PER_PAGE),
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => User::countStaff(),
        ], 'admin/layouts/app');
    }

    public function create(Request $request): void
    {
        $this->view('admin/users/form', [
            'pageTitle' => 'New Staff User | Kymera Collection Admin',
            'staffUser' => null,
            'roles' => Role::staffRoles(),
            'statuses' => self::STATUSES,
        ], 'admin/layouts/app');
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'email' => 'required|email|max:191|unique:users,email',
            'role_id' => 'required|integer',
            'password' => 'required|min:8|confirmed',
        ]);

        if (!self::isAssignableRole((int) $data['role_id'])) {
            Session::flash('errors', ['role_id' => ['Selected role is not valid for a staff account.']]);
            $this->back();
        }

        $status = (string) $request->input('status', 'active');

        if (!in_array($status, self::STATUSES, true)) {
            $status = 'active';
        }

        $userId = User::create([
            'uuid' => Uuid::v4(),
            'role_id' => (int) $data['role_id'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => mb_strtolower($data['email']),
            'password_hash' => password_hash($data['password'], config('security.hash_algo')),
            'status' => $status,
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        AuditLog::record(Auth::id(), 'staff_user.created', 'user', $userId, null, [
            'email' => mb_strtolower($data['email']),
            'role_id' => (int) $data['role_id'],
        ]);

        Session::flash('success', 'Staff account created.');
        $this->redirect('/admin/users');
    }

    public function edit(Request $request): void
    {
        $staffUser = $this->loadStaffUser($request);

        $this->view('admin/users/form', [
            'pageTitle' => 'Edit Staff User | Kymera Collection Admin',
            'staffUser' => $staffUser,
            'roles' => Role::staffRoles(),
            'statuses' => self::STATUSES,
        ], 'admin/layouts/app');
    }

    public function update(Request $request): void
    {
        $staffUser = $this->loadStaffUser($request);

        $data = $this->validate($request->all(), [
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'email' => 'required|email|max:191|unique:users,email,' . (int) $staffUser['id'],
            'role_id' => 'required|integer',
        ]);

        if (!self::isAssignableRole((int) $data['role_id'])) {
            Session::flash('errors', ['role_id' => ['Selected role is not valid for a staff account.']]);
            $this->back();
        }

        $status = (string) $request->input('status', $staffUser['status']);

        if (!in_array($status, self::STATUSES, true)) {
            $status = $staffUser['status'];
        }

        $update = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => mb_strtolower($data['email']),
            'role_id' => (int) $data['role_id'],
            'status' => $status,
        ];

        $newPassword = (string) $request->input('password', '');

        if ($newPassword !== '') {
            if (mb_strlen($newPassword) < 8) {
                Session::flash('errors', ['password' => ['Password must be at least 8 characters.']]);
                $this->back();
            }

            if ($newPassword !== (string) $request->input('password_confirmation', '')) {
                Session::flash('errors', ['password' => ['Password confirmation does not match.']]);
                $this->back();
            }

            $update['password_hash'] = password_hash($newPassword, config('security.hash_algo'));
        }

        User::update((int) $staffUser['id'], $update);

        AuditLog::record(Auth::id(), 'staff_user.updated', 'user', (int) $staffUser['id'], [
            'role_id' => (int) $staffUser['role_id'],
            'status' => $staffUser['status'],
        ], [
            'role_id' => (int) $data['role_id'],
            'status' => $status,
        ]);

        Session::flash('success', 'Staff account updated.');
        $this->redirect('/admin/users/' . (int) $staffUser['id'] . '/edit');
    }

    private function loadStaffUser(Request $request): array
    {
        $id = (int) $request->route('id');
        $user = User::find($id);
        $customerRole = Role::findBy('slug', 'customer');

        if ($user === null || ($customerRole !== null && (int) $user['role_id'] === (int) $customerRole['id'])) {
            Response::abort(404, 'Staff user not found.');
        }

        return $user;
    }

    private static function isAssignableRole(int $roleId): bool
    {
        foreach (Role::staffRoles() as $role) {
            if ((int) $role['id'] === $roleId) {
                return true;
            }
        }

        return false;
    }
}
