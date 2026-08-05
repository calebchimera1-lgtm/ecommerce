<?php /** @var string $startDate @var string $endDate @var array $rows */ ?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <h4 class="mb-0" style="color:#f8f7f4;">Customer Report</h4>
    <a href="/admin/reports" class="btn btn-outline-light btn-sm">&larr; Back to Reports</a>
</div>

<form method="GET" action="/admin/reports/customers" class="row g-2 mb-4">
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

<p class="text-white-50"><?= count($rows) ?> customer<?= count($rows) === 1 ? '' : 's' ?> with orders in this range</p>

<div class="d-flex justify-content-end mb-3">
    <?php $exportBase = '/admin/reports/customers?start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate); ?>
    <?php require __DIR__ . '/_export-buttons.php'; ?>
</div>

<div class="table-responsive">
    <table class="table table-dark table-sm align-middle">
        <thead><tr><th>Customer</th><th>Email</th><th class="text-end">Orders</th><th class="text-end">Total Spent</th><th>Last Order</th></tr></thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="5" class="text-white-50">No customers ordered in this range.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><a href="/admin/customers/<?= (int) $row['id'] ?>" class="text-white"><?= e(trim($row['first_name'] . ' ' . $row['last_name'])) ?></a></td>
                    <td class="text-white-50"><?= e($row['email']) ?></td>
                    <td class="text-end"><?= (int) $row['order_count'] ?></td>
                    <td class="text-end"><?= money($row['total_spent']) ?></td>
                    <td class="text-white-50"><?= e(date('M j, Y', strtotime($row['last_order_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
