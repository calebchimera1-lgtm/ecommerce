<?php /** @var array $rows @var float $totalValue */ ?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <h4 class="mb-0" style="color:#f8f7f4;">Inventory Report</h4>
    <a href="/admin/reports" class="btn btn-outline-light btn-sm">&larr; Back to Reports</a>
</div>

<p class="text-white-50">
    <?= count($rows) ?> product<?= count($rows) === 1 ? '' : 's' ?> &middot;
    Total stock value: <strong class="text-white"><?= money($totalValue) ?></strong>
</p>

<div class="d-flex justify-content-end mb-3">
    <?php $exportBase = '/admin/reports/inventory'; ?>
    <?php require __DIR__ . '/_export-buttons.php'; ?>
</div>

<div class="table-responsive">
    <table class="table table-dark table-sm align-middle">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Product</th>
                <th>Category</th>
                <th>Supplier</th>
                <th class="text-end">Stock</th>
                <th class="text-end">Threshold</th>
                <th class="text-end">Cost Price</th>
                <th class="text-end">Stock Value</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="text-white-50">No products found.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="text-white-50"><?= e($row['sku']) ?></td>
                    <td><?= e($row['name']) ?></td>
                    <td class="text-white-50"><?= e($row['category_name'] ?? '') ?></td>
                    <td class="text-white-50"><?= e($row['supplier_name'] ?? '&mdash;') ?></td>
                    <td class="text-end <?= (int) $row['stock_quantity'] <= (int) $row['low_stock_threshold'] ? 'text-warning' : '' ?>"><?= (int) $row['stock_quantity'] ?></td>
                    <td class="text-end text-white-50"><?= (int) $row['low_stock_threshold'] ?></td>
                    <td class="text-end"><?= $row['cost_price'] !== null ? money($row['cost_price']) : '&mdash;' ?></td>
                    <td class="text-end"><?= money($row['stock_value']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
