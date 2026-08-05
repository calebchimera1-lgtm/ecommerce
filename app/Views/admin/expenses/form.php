<?php
/** @var array|null $expense @var array $categories */
$isEdit = $expense !== null;
$action = $isEdit ? '/admin/expenses/' . (int) $expense['id'] : '/admin/expenses';

$field = static function (string $key, mixed $default = '') use ($isEdit, $expense): mixed {
    return $isEdit ? ($expense[$key] ?? $default) : old($key, $default);
};
?>
<h4 class="mb-4" style="color:#f8f7f4;"><?= $isEdit ? 'Edit Expense' : 'New Expense' ?></h4>
<form method="POST" action="<?= e($action) ?>" class="row g-3" style="max-width:640px;">
    <?= csrf_field() ?>
    <div class="col-md-6">
        <label class="form-label" for="category">Category</label>
        <input type="text" class="form-control" id="category" name="category" list="category-list" required value="<?= e((string) $field('category')) ?>">
        <datalist id="category-list">
            <?php foreach ($categories as $category): ?>
                <option value="<?= e($category) ?>"></option>
            <?php endforeach; ?>
        </datalist>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="amount">Amount</label>
        <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" required value="<?= e((string) $field('amount')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="expense_date">Date</label>
        <input type="date" class="form-control" id="expense_date" name="expense_date" required value="<?= e((string) $field('expense_date', date('Y-m-d'))) ?>">
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Description (optional)</label>
        <input type="text" class="form-control" id="description" name="description" value="<?= e((string) $field('description')) ?>">
    </div>
    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-gold"><?= $isEdit ? 'Save Changes' : 'Record Expense' ?></button>
        <a href="/admin/expenses" class="btn btn-outline-light">Cancel</a>
    </div>
</form>
