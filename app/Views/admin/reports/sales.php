<?php /** @var string $startDate @var string $endDate @var array $summary @var array $daily */ ?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <h4 class="mb-0" style="color:#f8f7f4;">Sales Report</h4>
    <a href="/admin/reports" class="btn btn-outline-light btn-sm">&larr; Back to Reports</a>
</div>

<form method="GET" action="/admin/reports/sales" class="row g-2 mb-4">
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

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Orders</div>
            <div class="stat-value"><?= $summary['order_count'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Sales</div>
            <div class="stat-value"><?= money($summary['sales_total']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Revenue</div>
            <div class="stat-value"><?= money($summary['revenue_total']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Avg Order Value</div>
            <div class="stat-value"><?= money($summary['avg_order_value']) ?></div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end mb-3">
    <?php $exportBase = '/admin/reports/sales?start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate); ?>
    <?php require __DIR__ . '/_export-buttons.php'; ?>
</div>

<div class="table-responsive">
    <table class="table table-dark table-sm align-middle">
        <thead><tr><th>Date</th><th class="text-end">Orders</th><th class="text-end">Sales</th><th class="text-end">Revenue</th></tr></thead>
        <tbody>
            <?php foreach ($daily as $d): ?>
                <tr>
                    <td class="text-white-50"><?= e(date('D, M j', strtotime($d['day']))) ?></td>
                    <td class="text-end"><?= $d['order_count'] ?></td>
                    <td class="text-end"><?= money($d['sales_total']) ?></td>
                    <td class="text-end"><?= money($d['revenue_total']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
    .stat-tile { background: rgba(201,162,75,0.06); border: 1px solid rgba(201,162,75,0.2); border-radius: 0.6rem; padding: 1rem; height: 100%; }
    .stat-label { font-family: Arial, sans-serif; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(248,247,244,0.5); }
    .stat-value { font-size: 1.4rem; font-weight: 600; color: #f8f7f4; margin-top: 0.25rem; }
</style>
