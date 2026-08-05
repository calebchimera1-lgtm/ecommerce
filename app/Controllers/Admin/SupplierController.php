<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Supplier;
use PDOException;

final class SupplierController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = ['search' => trim((string) $request->query('search', ''))];

        $this->view('admin/suppliers/index', [
            'pageTitle' => 'Suppliers | Kymera Collection Admin',
            'suppliers' => Supplier::paginateAll($page, self::PER_PAGE, $filters),
            'filters' => $filters,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => Supplier::countAll($filters),
        ], 'admin/layouts/app');
    }

    public function create(Request $request): void
    {
        $this->view('admin/suppliers/form', [
            'pageTitle' => 'New Supplier | Kymera Collection Admin',
            'supplier' => null,
        ], 'admin/layouts/app');
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request->all(), ['name' => 'required|max:150']);

        $id = Supplier::create([
            'name' => $data['name'],
            'contact_person' => self::nullable($request->input('contact_person')),
            'email' => self::nullable($request->input('email')),
            'phone' => self::nullable($request->input('phone')),
            'address' => self::nullable($request->input('address')),
            'is_active' => $request->input('is_active') !== null ? 1 : 0,
        ]);

        AuditLog::record(Auth::id(), 'supplier.created', 'supplier', $id, null, ['name' => $data['name']]);

        Session::flash('success', 'Supplier created.');
        $this->redirect('/admin/suppliers');
    }

    public function edit(Request $request): void
    {
        $supplier = $this->loadSupplier($request);

        $this->view('admin/suppliers/form', [
            'pageTitle' => 'Edit Supplier | Kymera Collection Admin',
            'supplier' => $supplier,
        ], 'admin/layouts/app');
    }

    public function update(Request $request): void
    {
        $supplier = $this->loadSupplier($request);
        $data = $this->validate($request->all(), ['name' => 'required|max:150']);

        Supplier::update((int) $supplier['id'], [
            'name' => $data['name'],
            'contact_person' => self::nullable($request->input('contact_person')),
            'email' => self::nullable($request->input('email')),
            'phone' => self::nullable($request->input('phone')),
            'address' => self::nullable($request->input('address')),
            'is_active' => $request->input('is_active') !== null ? 1 : 0,
        ]);

        Session::flash('success', 'Supplier updated.');
        $this->redirect('/admin/suppliers');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->route('id');

        try {
            Supplier::delete($id);
            Session::flash('success', 'Supplier deleted.');
        } catch (PDOException) {
            // Restricted by purchase_orders.supplier_id ON DELETE RESTRICT -
            // a supplier with any purchase order history can't be removed.
            Session::flash('errors', ['supplier' => ['This supplier has purchase order history and cannot be deleted. Deactivate it instead.']]);
        }

        $this->redirect('/admin/suppliers');
    }

    private function loadSupplier(Request $request): array
    {
        $id = (int) $request->route('id');
        $supplier = Supplier::find($id);

        if ($supplier === null) {
            Response::abort(404, 'Supplier not found.');
        }

        return $supplier;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
