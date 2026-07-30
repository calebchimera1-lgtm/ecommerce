<?php /** @var array $products @var array $categories @var array $brands @var array $filters @var int $page @var int $perPage @var int $total */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0" style="color:#f8f7f4;">Products</h4>
    <a href="/admin/products/create" class="btn btn-gold btn-sm">+ New Product</a>
</div>

<form method="GET" action="/admin/products" class="row g-2 mb-4">
    <div class="col-md-4">
        <input type="text" class="form-control" name="search" placeholder="Search by name or SKU"
               value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-md-3">
        <select class="form-select" name="category_id">
            <option value="">All categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>" <?= $filters['category_id'] === (string) $category['id'] ? 'selected' : '' ?>>
                    <?= str_repeat('&mdash; ', $category['depth']) ?><?= e($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-select" name="brand_id">
            <option value="">All brands</option>
            <?php foreach ($brands as $brand): ?>
                <option value="<?= (int) $brand['id'] ?>" <?= $filters['brand_id'] === (string) $brand['id'] ? 'selected' : '' ?>>
                    <?= e($brand['name']) ?>
                </option>
            <?php endforeach; ?>
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
                <th>Brand</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="8" class="text-white-50">No products found.</td></tr>
            <?php endif; ?>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= e($product['name']) ?></td>
                    <td class="text-white-50"><?= e($product['sku']) ?></td>
                    <td><?= e($product['category_name'] ?? '&mdash;') ?></td>
                    <td><?= e($product['brand_name'] ?? '&mdash;') ?></td>
                    <td>
                        <?php if ($product['sale_price'] !== null): ?>
                            <span class="text-decoration-line-through text-white-50"><?= money($product['price']) ?></span>
                            <span class="text-warning"><?= money($product['sale_price']) ?></span>
                        <?php else: ?>
                            <?= money($product['price']) ?>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $product['stock_quantity'] ?></td>
                    <td>
                        <?php if ((int) $product['is_active'] === 1): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/admin/products/<?= (int) $product['id'] ?>/edit" class="btn btn-sm btn-outline-light">Edit</a>
                        <form method="POST" action="/admin/products/<?= (int) $product['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this product?');">
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
