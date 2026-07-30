<?php
/**
 * @var string $roleName @var float $todaySales @var int $todayOrderCount
 * @var float $monthlySales @var float $monthlyRevenue @var float $allTimeRevenue
 * @var float $monthlyExpenses @var float $monthlyProfit @var int $orderCount
 * @var int $customerCount @var int $newCustomersThisMonth
 * @var array $bestSellingProducts @var array $lowStockProducts
 * @var array $latestOrders @var array $recentCustomers
 * @var array $salesTrend @var array $statusBreakdown
 */
$statLabels = [
    'pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped',
    'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded',
];
?>
<h4 class="mb-1" style="color:#f8f7f4;">Welcome back.</h4>
<p class="text-white-50 mb-4">You're signed in with the <strong><?= e($roleName) ?></strong> role.</p>

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
            <div class="stat-label">Revenue (All-Time, Paid)</div>
            <div class="stat-value"><?= money($allTimeRevenue) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Monthly Profit</div>
            <div class="stat-value <?= $monthlyProfit >= 0 ? 'text-success' : 'text-danger' ?>"><?= money($monthlyProfit) ?></div>
            <div class="stat-sub">Revenue <?= money($monthlyRevenue) ?> &minus; Expenses <?= money($monthlyExpenses) ?></div>
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
            <div class="stat-label">Customers</div>
            <div class="stat-value"><?= $customerCount ?></div>
            <div class="stat-sub">+<?= $newCustomersThisMonth ?> this month</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Monthly Expenses</div>
            <div class="stat-value"><?= money($monthlyExpenses) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Low Stock Items</div>
            <div class="stat-value <?= count($lowStockProducts) > 0 ? 'text-danger' : '' ?>"><?= count($lowStockProducts) ?></div>
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
            <canvas id="statusChart" height="90"></canvas>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="chart-card">
            <h6 class="mb-3">Best Selling Products</h6>
            <?php if (empty($bestSellingProducts)): ?>
                <p class="text-white-50 small mb-0">No sales yet.</p>
            <?php else: ?>
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach ($bestSellingProducts as $product): ?>
                            <tr>
                                <td><?= e($product['name']) ?></td>
                                <td class="text-end"><?= (int) $product['units_sold'] ?></td>
                                <td class="text-end"><?= money($product['revenue']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-card">
            <h6 class="mb-3">Low Stock Alerts</h6>
            <?php if (empty($lowStockProducts)): ?>
                <p class="text-white-50 small mb-0">All products are sufficiently stocked.</p>
            <?php else: ?>
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>Product</th><th class="text-end">Stock</th><th class="text-end">Threshold</th></tr></thead>
                    <tbody>
                        <?php foreach ($lowStockProducts as $product): ?>
                            <tr>
                                <td><a href="/admin/products/<?= (int) $product['id'] ?>/edit" class="text-white"><?= e($product['name']) ?></a></td>
                                <td class="text-end <?= (int) $product['stock_quantity'] === 0 ? 'text-danger' : 'text-warning' ?>"><?= (int) $product['stock_quantity'] ?></td>
                                <td class="text-end text-white-50"><?= (int) $product['low_stock_threshold'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="chart-card">
            <h6 class="mb-3">Latest Orders</h6>
            <?php if (empty($latestOrders)): ?>
                <p class="text-white-50 small mb-0">No orders yet.</p>
            <?php else: ?>
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($latestOrders as $order): ?>
                            <tr>
                                <td><a href="/admin/orders/<?= (int) $order['id'] ?>" class="text-white"><?= e($order['order_number']) ?></a></td>
                                <td><?= e($order['first_name'] . ' ' . $order['last_name']) ?></td>
                                <td><span class="badge bg-secondary"><?= e($statLabels[$order['status']] ?? ucfirst($order['status'])) ?></span></td>
                                <td class="text-end"><?= money($order['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-card">
            <h6 class="mb-3">Recent Customers</h6>
            <?php if (empty($recentCustomers)): ?>
                <p class="text-white-50 small mb-0">No customers yet.</p>
            <?php else: ?>
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>Name</th><th>Email</th><th class="text-end">Joined</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentCustomers as $customer): ?>
                            <tr>
                                <td><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></td>
                                <td class="text-white-50"><?= e($customer['email']) ?></td>
                                <td class="text-end text-white-50"><?= e(date('M j, Y', strtotime($customer['created_at']))) ?></td>
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

    var statusLabels = <?= json_encode(array_map(static fn (string $s): string => $statLabels[$s] ?? ucfirst($s), array_keys($statusBreakdown))) ?>;
    var statusData = <?= json_encode(array_values($statusBreakdown)) ?>;

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: ['#c9a24b', '#8a8a8a', '#5a8fd6', '#4caf50', '#e05656', '#b06fd1'],
                borderColor: '#141414',
            }],
        },
        options: {
            plugins: { legend: { position: 'bottom', labels: { color: textColor } } },
        },
    });
});
</script>
