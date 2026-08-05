<?php /** @var array $products @var array $filters @var int $page @var int $perPage @var int $total */
$statusBadge = static function (string $status): string {
    return match ($status) {
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        default => 'bg-secondary',
    };
};
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0" style="color:#f8f7f4;">My Products</h4>
    <a href="/vendor/products/create" class="btn btn-gold btn-sm">+ New Product</a>
</div>

<form method="GET" action="/vendor/products" class="row g-2 mb-4">
    <div class="col-md-6">
        <input type="text" class="form-control" name="search" placeholder="Search by name or SKU"
               value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-md-4">
        <select class="form-select" name="approval_status">
            <option value="">All statuses</option>
            <option value="pending" <?= $filters['approval_status'] === 'pending' ? 'selected' : '' ?>>Pending review</option>
            <option value="approved" <?= $filters['approval_status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="rejected" <?= $filters['approval_status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-light w-100">Filter</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Approval</th>
                <th>Live?</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="8" class="text-white-50">No products yet. Create your first listing to get started.</td></tr>
            <?php endif; ?>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= e($product['name']) ?></td>
                    <td class="text-white-50"><?= e($product['sku']) ?></td>
                    <td><?= e($product['category_name'] ?? '&mdash;') ?></td>
                    <td>
                        <?php if ($product['sale_price'] !== null): ?>
                            <span class="text-decoration-line-through text-white-50"><?= money($product['price']) ?></span>
                            <span class="text-warning"><?= money($product['sale_price']) ?></span>
                        <?php else: ?>
                            <?= money($product['price']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" action="/vendor/products/<?= (int) $product['id'] ?>/restock" class="d-flex gap-1">
                            <?= csrf_field() ?>
                            <input type="number" min="0" name="stock_quantity" value="<?= (int) $product['stock_quantity'] ?>"
                                   class="form-control form-control-sm" style="width:80px;">
                            <button type="submit" class="btn btn-sm btn-outline-light">Save</button>
                        </form>
                    </td>
                    <td>
                        <span class="badge <?= $statusBadge($product['approval_status']) ?>"><?= e(ucfirst($product['approval_status'])) ?></span>
                        <?php if ($product['approval_status'] === 'rejected' && !empty($product['rejection_reason'])): ?>
                            <div class="small text-white-50 mt-1" style="max-width:220px;"><?= e($product['rejection_reason']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int) $product['is_active'] === 1): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/vendor/products/<?= (int) $product['id'] ?>/edit" class="btn btn-sm btn-outline-light">Edit</a>
                        <form method="POST" action="/vendor/products/<?= (int) $product['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this product?');">
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
