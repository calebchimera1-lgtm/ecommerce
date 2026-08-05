<?php /** @var array $products @var array $filters @var int $page @var int $perPage @var int $total */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0" style="color:#f8f7f4;">Inventory</h4>
    <a href="/admin/inventory/movements" class="btn btn-outline-light btn-sm">View Movement Log</a>
</div>

<form method="GET" action="/admin/inventory" class="row g-2 mb-4">
    <div class="col-md-4">
        <input type="text" class="form-control" name="search" placeholder="Search by name or SKU" value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-md-3">
        <select class="form-select" name="stock">
            <option value="">All stock levels</option>
            <option value="low" <?= $filters['stock'] === 'low' ? 'selected' : '' ?>>Low stock</option>
            <option value="out" <?= $filters['stock'] === 'out' ? 'selected' : '' ?>>Out of stock</option>
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
                <th>Supplier</th>
                <th class="text-end">Stock</th>
                <th class="text-end">Threshold</th>
                <th style="width:340px;">Adjust stock</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="5" class="text-white-50">No products found.</td></tr>
            <?php endif; ?>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td>
                        <?= e($product['name']) ?>
                        <div class="text-white-50 small"><?= e($product['sku']) ?></div>
                    </td>
                    <td class="text-white-50"><?= e($product['supplier_name'] ?? '&mdash;') ?></td>
                    <td class="text-end <?= (int) $product['stock_quantity'] <= 0 ? 'text-danger' : ((int) $product['stock_quantity'] <= (int) $product['low_stock_threshold'] ? 'text-warning' : '') ?>">
                        <?= (int) $product['stock_quantity'] ?>
                    </td>
                    <td class="text-end text-white-50"><?= (int) $product['low_stock_threshold'] ?></td>
                    <td>
                        <form method="POST" action="/admin/inventory/<?= (int) $product['id'] ?>/adjust" class="d-flex gap-1">
                            <?= csrf_field() ?>
                            <select class="form-select form-select-sm" name="type" style="max-width:110px;">
                                <option value="in">In</option>
                                <option value="out">Out</option>
                                <option value="adjustment">Adjust</option>
                                <option value="damaged">Damaged</option>
                                <option value="return">Return</option>
                            </select>
                            <input type="number" class="form-control form-control-sm" name="quantity" placeholder="Qty" style="max-width:80px;" required>
                            <input type="text" class="form-control form-control-sm" name="note" placeholder="Note (optional)">
                            <button type="submit" class="btn btn-sm btn-outline-light">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
