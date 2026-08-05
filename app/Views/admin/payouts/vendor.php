<?php
/**
 * @var array $vendor @var array $vendorOrders @var float $unpaidBalance @var float $paidToDate
 * @var array $filters @var int $page @var int $perPage @var int $total
 */
?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1" style="color:#f8f7f4;"><?= e($vendor['store_name']) ?> &mdash; Payouts</h4>
        <p class="text-white-50 small mb-0">Payout details on file: <?= e($vendor['payout_details'] ?? 'None provided')  ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/vendors/<?= (int) $vendor['id'] ?>" class="btn btn-outline-light btn-sm">Vendor Profile</a>
        <a href="/admin/payouts" class="btn btn-outline-light btn-sm">&larr; All Vendors</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Unpaid Balance</div>
            <div class="stat-value text-warning"><?= money($unpaidBalance) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Paid To Date</div>
            <div class="stat-value text-success"><?= money($paidToDate) ?></div>
        </div>
    </div>
</div>

<form method="GET" action="/admin/payouts/<?= (int) $vendor['id'] ?>" class="row g-2 mb-4">
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
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($vendorOrders)): ?>
                <tr><td colspan="7" class="text-white-50">No orders found.</td></tr>
            <?php endif; ?>
            <?php foreach ($vendorOrders as $vendorOrder): ?>
                <tr>
                    <td><a href="/admin/orders/<?= (int) $vendorOrder['order_id'] ?>" class="text-warning"><?= e($vendorOrder['order_number']) ?></a></td>
                    <td class="text-white-50"><?= e(date('M j, Y', strtotime($vendorOrder['order_created_at']))) ?></td>
                    <td class="text-end"><?= money($vendorOrder['subtotal']) ?></td>
                    <td class="text-end text-white-50">-<?= money($vendorOrder['commission_amount']) ?></td>
                    <td class="text-end"><?= money($vendorOrder['payout_amount']) ?></td>
                    <td>
                        <?php if ($vendorOrder['payout_status'] === 'paid'): ?>
                            <span class="badge bg-success">Paid</span>
                            <?php if (!empty($vendorOrder['payout_reference'])): ?>
                                <div class="small text-white-50 mt-1" style="max-width:200px;"><?= e($vendorOrder['payout_reference']) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge bg-secondary">Unpaid</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if ($vendorOrder['payout_status'] === 'unpaid'): ?>
                            <button type="button" class="btn btn-sm btn-gold" data-bs-toggle="collapse" data-bs-target="#markPaid<?= (int) $vendorOrder['id'] ?>">Mark Paid</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($vendorOrder['payout_status'] === 'unpaid'): ?>
                    <tr class="collapse" id="markPaid<?= (int) $vendorOrder['id'] ?>">
                        <td colspan="7">
                            <form method="POST" action="/admin/payouts/<?= (int) $vendor['id'] ?>/<?= (int) $vendorOrder['id'] ?>/mark-paid" class="row g-2 align-items-end" style="max-width:520px;">
                                <?= csrf_field() ?>
                                <div class="col-8">
                                    <label class="form-label small">Payout reference (optional)</label>
                                    <input type="text" class="form-control form-control-sm" name="payout_reference" maxlength="191" placeholder="e.g. Bank transfer ref #12345">
                                </div>
                                <div class="col-4">
                                    <button type="submit" class="btn btn-sm btn-gold w-100">Confirm <?= money($vendorOrder['payout_amount']) ?> Paid</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endif; ?>
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
