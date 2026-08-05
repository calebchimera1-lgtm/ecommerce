<?php /** @var array $suppliers @var array $filters @var int $page @var int $perPage @var int $total */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0" style="color:#f8f7f4;">Suppliers</h4>
    <a href="/admin/suppliers/create" class="btn btn-gold btn-sm">+ New Supplier</a>
</div>

<form method="GET" action="/admin/suppliers" class="row g-2 mb-4">
    <div class="col-md-5">
        <input type="text" class="form-control" name="search" placeholder="Search by name"
               value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-light w-100">Filter</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>Contact</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($suppliers)): ?>
                <tr><td colspan="6" class="text-white-50">No suppliers yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($suppliers as $supplier): ?>
                <tr>
                    <td><?= e($supplier['name']) ?></td>
                    <td class="text-white-50"><?= e($supplier['contact_person'] ?? '') ?></td>
                    <td class="text-white-50"><?= e($supplier['email'] ?? '') ?></td>
                    <td class="text-white-50"><?= e($supplier['phone'] ?? '') ?></td>
                    <td>
                        <?php if ((int) $supplier['is_active'] === 1): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/admin/suppliers/<?= (int) $supplier['id'] ?>/edit" class="btn btn-sm btn-outline-light">Edit</a>
                        <form method="POST" action="/admin/suppliers/<?= (int) $supplier['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this supplier?');">
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
