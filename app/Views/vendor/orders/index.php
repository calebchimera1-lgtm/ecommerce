<?php
/** @var array $vendorOrders @var array $filters @var array $statuses @var int $page @var int $perPage @var int $total */
$statusBadge = static function (string $status): string {
    return match ($status) {
        'delivered' => 'bg-success',
        'cancelled' => 'bg-danger',
        'shipped' => 'bg-info text-dark',
        'processing' => 'bg-warning text-dark',
        default => 'bg-secondary',
    };
};
?>
<h4 class="mb-4" style="color:#f8f7f4;">My Orders</h4>

<form method="GET" action="/vendor/orders" class="row g-2 mb-4">
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
                <th>Date</th>
                <th>Items</th>
                <th class="text-end">Subtotal</th>
                <th class="text-end">Commission</th>
                <th class="text-end">Your Payout</th>
                <th>Status</th>
                <th>Payout</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($vendorOrders)): ?>
                <tr><td colspan="9" class="text-white-50">No orders yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($vendorOrders as $vendorOrder): ?>
                <tr>
                    <td><?= e($vendorOrder['order_number']) ?></td>
                    <td class="text-white-50"><?= e(date('M j, Y', strtotime($vendorOrder['order_created_at']))) ?></td>
                    <td><?= (int) $vendorOrder['item_count'] ?></td>
                    <td class="text-end"><?= money($vendorOrder['subtotal']) ?></td>
                    <td class="text-end text-white-50">-<?= money($vendorOrder['commission_amount']) ?></td>
                    <td class="text-end"><?= money($vendorOrder['payout_amount']) ?></td>
                    <td><span class="badge <?= $statusBadge($vendorOrder['status']) ?>"><?= e(ucfirst($vendorOrder['status'])) ?></span></td>
                    <td>
                        <?php if ($vendorOrder['payout_status'] === 'paid'): ?>
                            <span class="badge bg-success">Paid</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Unpaid</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><a href="/vendor/orders/<?= (int) $vendorOrder['id'] ?>" class="btn btn-sm btn-outline-light">Manage</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
