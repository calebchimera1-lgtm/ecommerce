<?php
/** @var array $returnRequest @var array $items @var array $statusHistory @var float $suggestedRefund */
$badge = static function (string $status): string {
    return match ($status) {
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'refunded' => 'bg-info text-dark',
        default => 'bg-warning text-dark',
    };
};
?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1" style="color:#f8f7f4;">Return Request &mdash; Order <?= e($returnRequest['order_number']) ?></h4>
        <span class="badge <?= $badge($returnRequest['status']) ?>"><?= e(ucfirst($returnRequest['status'])) ?></span>
    </div>
    <a href="/admin/returns" class="btn btn-outline-light btn-sm">&larr; Back to Returns</a>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <h6 class="text-white-50 small text-uppercase">Customer</h6>
        <p class="mb-1"><?= e($returnRequest['first_name'] . ' ' . $returnRequest['last_name']) ?></p>
        <p class="mb-1 text-white-50"><?= e($returnRequest['email']) ?></p>
    </div>
    <div class="col-md-6">
        <h6 class="text-white-50 small text-uppercase">Reason</h6>
        <p class="mb-1 text-white-50"><?= e($returnRequest['reason']) ?></p>
    </div>
    <?php if (!empty($returnRequest['admin_note'])): ?>
        <div class="col-12">
            <h6 class="text-white-50 small text-uppercase">Admin Note</h6>
            <p class="mb-0 text-white-50"><?= e($returnRequest['admin_note']) ?></p>
        </div>
    <?php endif; ?>
</div>

<div class="table-responsive mb-4">
    <table class="table table-dark table-sm align-middle">
        <thead><tr><th>Item</th><th>SKU</th><th>Qty</th><th class="text-end">Line Value</th><th>Vendor Item?</th><th>Restocked</th></tr></thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['product_name']) ?></td>
                    <td class="text-white-50"><?= e($item['sku']) ?></td>
                    <td><?= (int) $item['quantity'] ?></td>
                    <td class="text-end"><?= money((float) $item['price'] * (int) $item['quantity']) ?></td>
                    <td><?= $item['vendor_order_id'] !== null ? '<span class="text-warning">Yes</span>' : '<span class="text-white-50">No</span>' ?></td>
                    <td><?= (int) $item['restocked'] === 1 ? '<span class="text-success">Yes</span>' : '<span class="text-white-50">No</span>' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($returnRequest['status'] === 'pending'): ?>
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <h6 class="mb-3" style="color:#f8f7f4;">Approve</h6>
            <form method="POST" action="/admin/returns/<?= (int) $returnRequest['id'] ?>/approve" class="row g-2">
                <?= csrf_field() ?>
                <div class="col-12">
                    <textarea class="form-control form-control-sm" name="admin_note" rows="2" placeholder="Note (optional)"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-gold btn-sm">Approve Return</button>
                </div>
            </form>
        </div>
        <div class="col-md-6">
            <h6 class="mb-3" style="color:#f8f7f4;">Reject</h6>
            <form method="POST" action="/admin/returns/<?= (int) $returnRequest['id'] ?>/reject" class="row g-2">
                <?= csrf_field() ?>
                <div class="col-12">
                    <textarea class="form-control form-control-sm" name="admin_note" rows="2" placeholder="Reason for rejection" required></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Reject Return</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($returnRequest['status'] === 'approved'): ?>
    <div class="mb-4">
        <h6 class="mb-3" style="color:#f8f7f4;">Mark as Refunded</h6>
        <form method="POST" action="/admin/returns/<?= (int) $returnRequest['id'] ?>/refund" class="row g-3" style="max-width:520px;">
            <?= csrf_field() ?>
            <div class="col-12">
                <label class="form-label small">Refunded amount</label>
                <input type="number" step="0.01" min="0" class="form-control" name="refunded_amount" value="<?= number_format($suggestedRefund, 2, '.', '') ?>" required>
                <div class="form-text text-white-50">Pre-filled with the item line total - adjust for a restocking fee or partial refund.</div>
            </div>
            <div class="col-12">
                <label class="form-label small">Restock these items</label>
                <?php foreach ($items as $item): ?>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="restock[]" value="<?= (int) $item['id'] ?>" id="restock_<?= (int) $item['id'] ?>" checked>
                        <label class="form-check-label small" for="restock_<?= (int) $item['id'] ?>"><?= e($item['product_name']) ?> (<?= (int) $item['quantity'] ?>)</label>
                    </div>
                <?php endforeach; ?>
                <div class="form-text text-white-50">Uncheck an item if it's defective or otherwise shouldn't go back into sellable stock.</div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-gold" onsubmit="return confirm('Mark this return as refunded?');">Confirm Refund</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if (!empty($statusHistory)): ?>
    <h6 class="mb-2 text-white-50 small text-uppercase">History</h6>
    <ul class="list-unstyled small">
        <?php foreach ($statusHistory as $entry): ?>
            <li class="text-white-50 mb-1">
                <span class="text-white"><?= e(ucfirst($entry['status'])) ?></span> &mdash; <?= e(date('M j, Y g:ia', strtotime($entry['created_at']))) ?>
                <?php if (!empty($entry['note'])): ?> &mdash; <?= e($entry['note']) ?><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
