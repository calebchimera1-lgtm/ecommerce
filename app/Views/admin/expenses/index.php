<?php /** @var array $expenses @var array $categories @var array $filters @var float $sumTotal @var int $page @var int $perPage @var int $total */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0" style="color:#f8f7f4;">Expenses</h4>
    <a href="/admin/expenses/create" class="btn btn-gold btn-sm">+ New Expense</a>
</div>

<form method="GET" action="/admin/expenses" class="row g-2 mb-4">
    <div class="col-md-3">
        <select class="form-select" name="category">
            <option value="">All categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e($category) ?>" <?= $filters['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <input type="date" class="form-control" name="start_date" value="<?= e($filters['start_date']) ?>" placeholder="From">
    </div>
    <div class="col-md-3">
        <input type="date" class="form-control" name="end_date" value="<?= e($filters['end_date']) ?>" placeholder="To">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-light w-100">Filter</button>
    </div>
</form>

<p class="text-white-50">Total: <strong class="text-white"><?= money($sumTotal) ?></strong> across <?= $total ?> expense<?= $total === 1 ? '' : 's' ?></p>

<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Description</th>
                <th class="text-end">Amount</th>
                <th>Recorded by</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($expenses)): ?>
                <tr><td colspan="6" class="text-white-50">No expenses found.</td></tr>
            <?php endif; ?>
            <?php foreach ($expenses as $expense): ?>
                <tr>
                    <td class="text-white-50"><?= e(date('M j, Y', strtotime($expense['expense_date']))) ?></td>
                    <td><span class="badge bg-secondary"><?= e($expense['category']) ?></span></td>
                    <td class="text-white-50"><?= e($expense['description'] ?? '') ?></td>
                    <td class="text-end"><?= money($expense['amount']) ?></td>
                    <td class="text-white-50 small"><?= $expense['created_by'] !== null ? e(trim($expense['first_name'] . ' ' . $expense['last_name'])) : '&mdash;' ?></td>
                    <td class="text-end">
                        <a href="/admin/expenses/<?= (int) $expense['id'] ?>/edit" class="btn btn-sm btn-outline-light">Edit</a>
                        <form method="POST" action="/admin/expenses/<?= (int) $expense['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this expense?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
