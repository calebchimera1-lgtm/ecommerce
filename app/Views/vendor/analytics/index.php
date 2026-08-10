<?php
/**
 * @var array $vendor @var float $todaySales @var int $todayOrderCount
 * @var float $monthlySales @var float $monthlyPayout @var float $allTimeSales
 * @var float $unpaidBalance @var int $orderCount
 * @var array $salesTrend @var array $statusBreakdown @var array $bestSellingProducts
 */
$statLabels = [
    'pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped',
    'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
];
?>
<h4 class="mb-1" style="color:#f8f7f4;">Analytics</h4>
<p class="text-white-50 mb-4">How <?= e($vendor['store_name']) ?> is performing on Kymera Collection.</p>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Today's Sales</div>
            <div class="stat-value"><?= money($todaySales) ?></div>
            <div class="stat-sub"><?= $todayOrderCount ?> order<?= $todayOrderCount === 1 ? '' : 's' ?> today</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Monthly Sales</div>
            <div class="stat-value"><?= money($monthlySales) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Monthly Payout Earned</div>
            <div class="stat-value text-success"><?= money($monthlyPayout) ?></div>
            <div class="stat-sub">After platform commission</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">All-Time Sales</div>
            <div class="stat-value"><?= money($allTimeSales) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Total Orders</div>
            <div class="stat-value"><?= $orderCount ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Unpaid Balance</div>
            <div class="stat-value text-warning"><?= money($unpaidBalance) ?></div>
            <div class="stat-sub"><a href="/vendor/payouts" class="text-warning">View Payouts</a></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="chart-card">
            <h6 class="mb-3">Sales Trend (Last 14 Days)</h6>
            <canvas id="salesTrendChart" height="90"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="chart-card">
            <h6 class="mb-3">Orders by Status</h6>
            <?php if (empty($statusBreakdown)): ?>
                <p class="text-white-50 small mb-0">No orders yet.</p>
            <?php else: ?>
                <canvas id="statusChart" height="90"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-12">
        <div class="chart-card">
            <h6 class="mb-3">Best Selling Products</h6>
            <?php if (empty($bestSellingProducts)): ?>
                <p class="text-white-50 small mb-0">No sales yet.</p>
            <?php else: ?>
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>Product</th><th class="text-end">Units Sold</th><th class="text-end">Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach ($bestSellingProducts as $product): ?>
                            <tr>
                                <td><a href="/vendor/products" class="text-white"><?= e($product['name']) ?></a></td>
                                <td class="text-end"><?= (int) $product['units_sold'] ?></td>
                                <td class="text-end"><?= money($product['revenue']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .stat-tile { background: rgba(201,162,75,0.06); border: 1px solid rgba(201,162,75,0.2); border-radius: 0.6rem; padding: 1rem; height: 100%; }
    .stat-label { font-family: Arial, sans-serif; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(248,247,244,0.5); }
    .stat-value { font-size: 1.4rem; font-weight: 600; color: #f8f7f4; margin-top: 0.25rem; }
    .stat-sub { font-family: Arial, sans-serif; font-size: 0.75rem; color: rgba(248,247,244,0.4); margin-top: 0.2rem; }
    .chart-card { background: #141414; border: 1px solid rgba(255,255,255,0.08); border-radius: 0.6rem; padding: 1.25rem; height: 100%; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var goldColor = '#c9a24b';
    var gridColor = 'rgba(255,255,255,0.08)';
    var textColor = 'rgba(248,247,244,0.6)';

    var trendLabels = <?= json_encode(array_map(static fn (array $d): string => date('M j', strtotime($d['day'])), $salesTrend)) ?>;
    var trendData = <?= json_encode(array_map(static fn (array $d): float => round($d['total'], 2), $salesTrend)) ?>;

    new Chart(document.getElementById('salesTrendChart'), {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Sales',
                data: trendData,
                borderColor: goldColor,
                backgroundColor: 'rgba(201,162,75,0.15)',
                fill: true,
                tension: 0.3,
                pointRadius: 2,
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: textColor }, grid: { color: gridColor } },
                y: { ticks: { color: textColor }, grid: { color: gridColor }, beginAtZero: true },
            },
        },
    });

    <?php if (!empty($statusBreakdown)): ?>
    var statusLabels = <?= json_encode(array_map(static fn (string $s): string => $statLabels[$s] ?? ucfirst($s), array_keys($statusBreakdown))) ?>;
    var statusData = <?= json_encode(array_values($statusBreakdown)) ?>;

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: ['#c9a24b', '#8a8a8a', '#5a8fd6', '#4caf50', '#e05656'],
                borderColor: '#141414',
            }],
        },
        options: {
            plugins: { legend: { position: 'bottom', labels: { color: textColor } } },
        },
    });
    <?php endif; ?>
});
</script>
