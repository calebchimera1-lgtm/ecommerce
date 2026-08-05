<?php /** @var string $startDate @var string $endDate @var array $rows */ ?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <h4 class="mb-0" style="color:#f8f7f4;">Product Performance Report</h4>
    <a href="/admin/reports" class="btn btn-outline-light btn-sm">&larr; Back to Reports</a>
</div>

<form method="GET" action="/admin/reports/products" class="row g-2 mb-4">
    <div class="col-md-3">
        <input type="date" class="form-control" name="start_date" value="<?= e($startDate) ?>">
    </div>
    <div class="col-md-3">
        <input type="date" class="form-control" name="end_date" value="<?= e($endDate) ?>">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-light w-100">Apply</button>
    </div>
</form>

<p class="text-white-50"><?= count($rows) ?> product<?= count($rows) === 1 ? '' : 's' ?> sold in this range</p>

<div class="d-flex justify-content-end mb-3">
    <?php $exportBase = '/admin/reports/products?start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate); ?>
    <?php require __DIR__ . '/_export-buttons.php'; ?>
</div>

<div class="table-responsive">
    <table class="table table-dark table-sm align-middle">
        <thead><tr><th>Product</th><th>SKU</th><th class="text-end">Units Sold</th><th class="text-end">Revenue</th></tr></thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="4" class="text-white-50">No products sold in this range.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['name']) ?></td>
                    <td class="text-white-50"><?= e($row['sku']) ?></td>
                    <td class="text-end"><?= (int) $row['units_sold'] ?></td>
                    <td class="text-end"><?= money($row['revenue']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
