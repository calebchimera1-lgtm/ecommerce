<?php /** @var array $orders @var array $filters @var array $statuses @var int $page @var int $perPage @var int $total */ ?>
<h4 class="mb-4" style="color:#f8f7f4;">Orders</h4>

<form method="GET" action="/admin/orders" class="row g-2 mb-4">
    <div class="col-md-3">
        <select class="form-select" name="status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Status</th>
                <th>Payment</th>
                <th class="text-end">Total</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="7" class="text-white-50">No orders found.</td></tr>
            <?php endif; ?>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= e($order['order_number']) ?></td>
                    <td><?= e($order['first_name'] . ' ' . $order['last_name']) ?><br><span class="text-white-50 small"><?= e($order['email']) ?></span></td>
                    <td class="text-white-50"><?= e(date('M j, Y', strtotime($order['created_at']))) ?></td>
                    <td><span class="badge bg-secondary"><?= e(ucfirst($order['status'])) ?></span></td>
                    <td class="text-white-50"><?= e(ucfirst($order['payment_status'])) ?></td>
                    <td class="text-end"><?= money($order['total']) ?></td>
                    <td class="text-end"><a href="/admin/orders/<?= (int) $order['id'] ?>" class="btn btn-sm btn-outline-light">View</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
