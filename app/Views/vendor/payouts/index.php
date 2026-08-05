<?php /** @var array $vendorOrders @var float $unpaidBalance @var float $paidToDate @var array $filters @var int $page @var int $perPage @var int $total */ ?>
<h4 class="mb-4" style="color:#f8f7f4;">Payouts</h4>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-tile">
            <div class="stat-label">Unpaid Balance</div>
            <div class="stat-value text-warning"><?= money($unpaidBalance) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-tile">
            <div class="stat-label">Paid To Date</div>
            <div class="stat-value text-success"><?= money($paidToDate) ?></div>
        </div>
    </div>
</div>

<p class="text-white-50 small mb-4">
    Payouts are recorded and paid manually by our team once your order's commission is finalized - this
    page reflects what our records show, not a live payment system.
</p>

<form method="GET" action="/vendor/payouts" class="row g-2 mb-4">
    <div class="col-md-3">
        <select class="form-select" name="payout_status" onchange="this.form.submit()">
            <option value="">All payouts</option>
            <option value="unpaid" <?= $filters['payout_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
            <option value="paid" <?= $filters['payout_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
        </select>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>Order</th>
                <th>Date</th>
                <th class="text-end">Subtotal</th>
                <th class="text-end">Commission</th>
                <th class="text-end">Payout</th>
                <th>Status</th>
                <th>Paid On</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($vendorOrders)): ?>
                <tr><td colspan="7" class="text-white-50">No orders yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($vendorOrders as $vendorOrder): ?>
                <tr>
                    <td><?= e($vendorOrder['order_number']) ?></td>
                    <td class="text-white-50"><?= e(date('M j, Y', strtotime($vendorOrder['order_created_at']))) ?></td>
                    <td class="text-end"><?= money($vendorOrder['subtotal']) ?></td>
                    <td class="text-end text-white-50">-<?= money($vendorOrder['commission_amount']) ?></td>
                    <td class="text-end"><?= money($vendorOrder['payout_amount']) ?></td>
                    <td>
                        <?php if ($vendorOrder['payout_status'] === 'paid'): ?>
                            <span class="badge bg-success">Paid</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Unpaid</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-white-50"><?= $vendorOrder['paid_at'] !== null ? e(date('M j, Y', strtotime($vendorOrder['paid_at']))) : '&mdash;' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>

<style>
    .stat-tile { background: rgba(201,162,75,0.06); border: 1px solid rgba(201,162,75,0.2); border-radius: 0.6rem; padding: 1rem; height: 100%; }
    .stat-label { font-family: Arial, sans-serif; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(248,247,244,0.5); }
    .stat-value { font-size: 1.4rem; font-weight: 600; color: #f8f7f4; margin-top: 0.25rem; }
</style>
