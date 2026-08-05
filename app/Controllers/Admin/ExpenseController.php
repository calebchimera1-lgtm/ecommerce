<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Expense;

final class ExpenseController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = [
            'category' => (string) $request->query('category', ''),
            'start_date' => (string) $request->query('start_date', ''),
            'end_date' => (string) $request->query('end_date', ''),
        ];

        $this->view('admin/expenses/index', [
            'pageTitle' => 'Expenses | Kymera Collection Admin',
            'expenses' => Expense::paginateAll($page, self::PER_PAGE, $filters),
            'categories' => Expense::categories(),
            'filters' => $filters,
            'sumTotal' => Expense::sumFiltered($filters),
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => Expense::countAll($filters),
        ], 'admin/layouts/app');
    }

    public function create(Request $request): void
    {
        $this->view('admin/expenses/form', [
            'pageTitle' => 'New Expense | Kymera Collection Admin',
            'expense' => null,
            'categories' => Expense::categories(),
        ], 'admin/layouts/app');
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'category' => 'required|max:100',
            'amount' => 'required|numeric',
            'expense_date' => 'required',
        ]);

        $id = Expense::create([
            'category' => $data['category'],
            'description' => self::nullable($request->input('description')),
            'amount' => (string) $data['amount'],
            'expense_date' => $data['expense_date'],
            'created_by' => Auth::id(),
        ]);

        AuditLog::record(Auth::id(), 'expense.created', 'expense', $id, null, [
            'category' => $data['category'],
            'amount' => $data['amount'],
        ]);

        Session::flash('success', 'Expense recorded.');
        $this->redirect('/admin/expenses');
    }

    public function edit(Request $request): void
    {
        $expense = $this->loadExpense($request);

        $this->view('admin/expenses/form', [
            'pageTitle' => 'Edit Expense | Kymera Collection Admin',
            'expense' => $expense,
            'categories' => Expense::categories(),
        ], 'admin/layouts/app');
    }

    public function update(Request $request): void
    {
        $expense = $this->loadExpense($request);

        $data = $this->validate($request->all(), [
            'category' => 'required|max:100',
            'amount' => 'required|numeric',
            'expense_date' => 'required',
        ]);

        Expense::update((int) $expense['id'], [
            'category' => $data['category'],
            'description' => self::nullable($request->input('description')),
            'amount' => (string) $data['amount'],
            'expense_date' => $data['expense_date'],
        ]);

        AuditLog::record(Auth::id(), 'expense.updated', 'expense', (int) $expense['id'], [
            'category' => $expense['category'],
            'amount' => $expense['amount'],
        ], [
            'category' => $data['category'],
            'amount' => $data['amount'],
        ]);

        Session::flash('success', 'Expense updated.');
        $this->redirect('/admin/expenses');
    }

    public function destroy(Request $request): void
    {
        $expense = $this->loadExpense($request);
        Expense::delete((int) $expense['id']);

        AuditLog::record(Auth::id(), 'expense.deleted', 'expense', (int) $expense['id'], [
            'category' => $expense['category'],
            'amount' => $expense['amount'],
        ], null);

        Session::flash('success', 'Expense deleted.');
        $this->redirect('/admin/expenses');
    }

    private function loadExpense(Request $request): array
    {
        $id = (int) $request->route('id');
        $expense = Expense::find($id);

        if ($expense === null) {
            Response::abort(404, 'Expense not found.');
        }

        return $expense;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
