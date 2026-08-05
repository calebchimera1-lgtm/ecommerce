<?php /** @var array $po @var array $items */ ?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1" style="color:#f8f7f4;">Purchase Order #<?= (int) $po['id'] ?></h4>
        <p class="text-white-50 small mb-0">
            Supplier: <?= e($po['supplier_name']) ?>
            <?php if (!empty($po['contact_person'])): ?> &middot; <?= e($po['contact_person']) ?><?php endif; ?>
            <?php if (!empty($po['supplier_email'])): ?> &middot; <?= e($po['supplier_email']) ?><?php endif; ?>
        </p>
        <p class="text-white-50 small mb-0">Created <?= e(date('F j, Y', strtotime($po['created_at']))) ?></p>
    </div>
    <a href="/admin/purchase-orders" class="btn btn-outline-light btn-sm">&larr; Back to Purchase Orders</a>
</div>

<?php
$badge = match ($po['status']) {
    'received' => 'bg-success',
    'partially_received' => 'bg-warning text-dark',
    'cancelled' => 'bg-danger',
    'ordered' => 'bg-info text-dark',
    default => 'bg-secondary',
};
?>
<div class="mb-4">
    <span class="badge <?= $badge ?> fs-6"><?= e(ucfirst(str_replace('_', ' ', $po['status']))) ?></span>
    <span class="ms-3 text-white-50">Total: <strong class="text-white"><?= money($po['total_amount']) ?></strong></span>
    <?php if ($po['expected_at'] !== null): ?>
        <span class="ms-3 text-white-50">Expected: <?= e(date('M j, Y', strtotime($po['expected_at']))) ?></span>
    <?php endif; ?>
    <?php if ($po['received_at'] !== null): ?>
        <span class="ms-3 text-white-50">Received: <?= e(date('M j, Y', strtotime($po['received_at']))) ?></span>
    <?php endif; ?>
</div>

<div class="d-flex gap-2 mb-4">
    <?php if ($po['status'] === 'draft'): ?>
        <form method="POST" action="/admin/purchase-orders/<?= (int) $po['id'] ?>/order">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-gold btn-sm">Mark as Ordered</button>
        </form>
    <?php endif; ?>
    <?php if (in_array($po['status'], ['draft', 'ordered'], true)): ?>
        <form method="POST" action="/admin/purchase-orders/<?= (int) $po['id'] ?>/cancel" onsubmit="return confirm('Cancel this purchase order?');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>
        </form>
    <?php endif; ?>
</div>

<?php if (in_array($po['status'], ['ordered', 'partially_received'], true)): ?>
    <form method="POST" action="/admin/purchase-orders/<?= (int) $po['id'] ?>/receive">
        <?= csrf_field() ?>
        <div class="table-responsive mb-3">
            <table class="table table-dark align-middle">
                <thead><tr><th>Product</th><th>SKU</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Remaining</th><th style="width:160px;">Receive now</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $remaining = (int) $item['quantity'] - (int) $item['received_quantity']; ?>
                        <tr>
                            <td><?= e($item['product_name']) ?></td>
                            <td class="text-white-50"><?= e($item['sku']) ?></td>
                            <td class="text-end"><?= (int) $item['quantity'] ?></td>
                            <td class="text-end"><?= (int) $item['received_quantity'] ?></td>
                            <td class="text-end"><?= $remaining ?></td>
                            <td>
                                <?php if ($remaining > 0): ?>
                                    <input type="number" min="0" max="<?= $remaining ?>" class="form-control form-control-sm" name="receive_qty[<?= (int) $item['id'] ?>]" placeholder="0">
                                <?php else: ?>
                                    <span class="text-success small">Fully received</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" class="btn btn-gold btn-sm">Receive Stock</button>
    </form>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-dark align-middle">
            <thead><tr><th>Product</th><th>SKU</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Unit Cost</th></tr></thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= e($item['product_name']) ?></td>
                        <td class="text-white-50"><?= e($item['sku']) ?></td>
                        <td class="text-end"><?= (int) $item['quantity'] ?></td>
                        <td class="text-end"><?= (int) $item['received_quantity'] ?></td>
                        <td class="text-end"><?= money($item['unit_cost']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
