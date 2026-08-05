<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\User;

final class CustomerController extends Controller
{
    private const PER_PAGE = 20;
    private const STATUSES = ['active', 'inactive', 'banned'];

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => (string) $request->query('status', ''),
        ];

        $this->view('admin/customers/index', [
            'pageTitle' => 'Customers | Kymera Collection Admin',
            'customers' => User::paginateCustomers($page, self::PER_PAGE, $filters),
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => User::countCustomersFiltered($filters),
        ], 'admin/layouts/app');
    }

    public function show(Request $request): void
    {
        $customer = $this->loadCustomer($request);

        $this->view('admin/customers/show', [
            'pageTitle' => e($customer['first_name'] . ' ' . $customer['last_name']) . ' | Kymera Collection Admin',
            'customer' => $customer,
            'orders' => Order::forUser((int) $customer['id']),
            'reviews' => ProductReview::forUser((int) $customer['id']),
            'statuses' => self::STATUSES,
        ], 'admin/layouts/app');
    }

    public function updateStatus(Request $request): void
    {
        $customer = $this->loadCustomer($request);
        $status = (string) $request->input('status');

        if (!in_array($status, self::STATUSES, true)) {
            Session::flash('errors', ['status' => ['Invalid status.']]);
            $this->back();
        }

        $oldStatus = $customer['status'];
        User::updateStatus((int) $customer['id'], $status);

        AuditLog::record(Auth::id(), 'customer.status_changed', 'user', (int) $customer['id'], [
            'status' => $oldStatus,
        ], ['status' => $status]);

        Session::flash('success', 'Customer status updated.');
        $this->redirect('/admin/customers/' . (int) $customer['id']);
    }

    private function loadCustomer(Request $request): array
    {
        $id = (int) $request->route('id');
        $customer = User::find($id);

        if ($customer === null || (int) $customer['role_id'] !== self::customerRoleId()) {
            Response::abort(404, 'Customer not found.');
        }

        return $customer;
    }

    private static function customerRoleId(): int
    {
        static $roleId = null;

        if ($roleId === null) {
            $role = \App\Models\Role::findBy('slug', 'customer');
            $roleId = $role !== null ? (int) $role['id'] : 0;
        }

        return $roleId;
    }
}
