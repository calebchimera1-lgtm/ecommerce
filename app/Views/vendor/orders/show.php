<?php
/**
 * @var array $vendorOrder @var array $items @var array|null $shippingAddress
 * @var array $statusHistory @var array $statuses
 */
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
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1" style="color:#f8f7f4;">Order <?= e($vendorOrder['order_number']) ?></h4>
        <span class="badge <?= $statusBadge($vendorOrder['status']) ?>"><?= e(ucfirst($vendorOrder['status'])) ?></span>
        <?php if ($vendorOrder['payout_status'] === 'paid'): ?>
            <span class="badge bg-success">Payout Paid</span>
        <?php else: ?>
            <span class="badge bg-secondary">Payout Unpaid</span>
        <?php endif; ?>
    </div>
    <a href="/vendor/orders" class="btn btn-outline-light btn-sm">&larr; Back to Orders</a>
</div>

<?php if ($shippingAddress !== null): ?>
    <div class="mb-4">
        <h6 class="text-white-50 small text-uppercase">Ship To</h6>
        <p class="mb-0 small">
            <?= e($shippingAddress['full_name']) ?><br>
            <?= e($shippingAddress['address_line1']) ?><?php if (!empty($shippingAddress['address_line2'])): ?>, <?= e($shippingAddress['address_line2']) ?><?php endif; ?><br>
            <?= e($shippingAddress['city']) ?><?php if (!empty($shippingAddress['state'])): ?>, <?= e($shippingAddress['state']) ?><?php endif; ?> <?= e((string) $shippingAddress['postal_code']) ?><br>
            <?= e($shippingAddress['country']) ?><br>
            <?= e($shippingAddress['phone']) ?>
        </p>
    </div>
<?php endif; ?>

<div class="table-responsive mb-4">
    <table class="table table-dark align-middle">
        <thead><tr><th>Item</th><th>SKU</th><th>Qty</th><th class="text-end">Subtotal</th></tr></thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['product_name']) ?></td>
                    <td class="text-white-50"><?= e($item['sku']) ?></td>
                    <td><?= (int) $item['quantity'] ?></td>
                    <td class="text-end"><?= money($item['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="row justify-content-end mb-5">
    <div class="col-md-4 small">
        <div class="d-flex justify-content-between"><span class="text-white-50">Subtotal</span><span><?= money($vendorOrder['subtotal']) ?></span></div>
        <div class="d-flex justify-content-between"><span class="text-white-50">Platform Commission</span><span>-<?= money($vendorOrder['commission_amount']) ?></span></div>
        <hr class="border-secondary">
        <div class="d-flex justify-content-between fs-5"><span>Your Payout</span><span><?= money($vendorOrder['payout_amount']) ?></span></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <h6 class="mb-3" style="color:#f8f7f4;">Update Fulfillment Status</h6>
        <form method="POST" action="/vendor/orders/<?= (int) $vendorOrder['id'] ?>/status" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-8">
                <select class="form-select" name="status">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= $vendorOrder['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <input type="text" class="form-control" name="note" placeholder="Note (optional)">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-gold btn-sm">Update Status</button>
            </div>
        </form>
    </div>

    <div class="col-md-6">
        <?php if (!empty($statusHistory)): ?>
            <h6 class="mb-3" style="color:#f8f7f4;">History</h6>
            <ul class="list-unstyled small">
                <?php foreach (array_reverse($statusHistory) as $entry): ?>
                    <li class="text-white-50 mb-1">
                        <span class="text-white"><?= e(ucfirst($entry['status'])) ?></span> &mdash; <?= e(date('M j, Y g:ia', strtotime($entry['created_at']))) ?>
                        <?php if (!empty($entry['note'])): ?> &mdash; <?= e($entry['note']) ?><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
