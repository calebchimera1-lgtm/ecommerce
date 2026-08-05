<?php
/**
 * @var array $order @var array $items @var array $addresses @var array|null $payment
 * @var array $statusHistory @var array|null $shipment @var array $statuses
 */
$billing = $addresses['billing'] ?? null;
$shipping = $addresses['shipping'] ?? null;
?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1" style="color:#f8f7f4;">Order <?= e($order['order_number']) ?></h4>
        <p class="text-white-50 small mb-0">Placed <?= e(date('F j, Y g:ia', strtotime($order['created_at']))) ?></p>
    </div>
    <a href="/admin/orders" class="btn btn-outline-light btn-sm">&larr; Back to Orders</a>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <h6 class="text-white-50 small text-uppercase">Billing Address</h6>
        <?php if ($billing !== null): ?>
            <p class="mb-0 small"><?= e($billing['full_name']) ?><br><?= e($billing['address_line1']) ?><br><?= e($billing['city']) ?>, <?= e($billing['country']) ?><br><?= e($billing['phone']) ?></p>
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <h6 class="text-white-50 small text-uppercase">Shipping Address</h6>
        <?php if ($shipping !== null): ?>
            <p class="mb-0 small"><?= e($shipping['full_name']) ?><br><?= e($shipping['address_line1']) ?><br><?= e($shipping['city']) ?>, <?= e($shipping['country']) ?><br><?= e($shipping['phone']) ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="table-responsive mb-4">
    <table class="table table-dark align-middle">
        <thead><tr><th>Item</th><th>SKU</th><th>Qty</th><th class="text-end">Total</th></tr></thead>
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
        <div class="d-flex justify-content-between"><span class="text-white-50">Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
        <div class="d-flex justify-content-between"><span class="text-white-50">Discount</span><span>-<?= money($order['discount_amount']) ?></span></div>
        <div class="d-flex justify-content-between"><span class="text-white-50">Shipping</span><span><?= money($order['shipping_amount']) ?></span></div>
        <div class="d-flex justify-content-between"><span class="text-white-50">Tax</span><span><?= money($order['tax_amount']) ?></span></div>
        <hr class="border-secondary">
        <div class="d-flex justify-content-between fs-5"><span>Total</span><span><?= money($order['total']) ?></span></div>
        <div class="d-flex justify-content-between mt-2"><span class="text-white-50">Payment</span><span><?= e(ucfirst($order['payment_method'])) ?> &mdash; <?= e(ucfirst($order['payment_status'])) ?></span></div>
        <?php if ($payment !== null && $payment['transaction_id'] !== null): ?>
            <div class="d-flex justify-content-between"><span class="text-white-50">Transaction ID</span><span><?= e($payment['transaction_id']) ?></span></div>
        <?php endif; ?>
        <?php if ($order['payment_status'] !== 'paid'): ?>
            <form method="POST" action="/admin/orders/<?= (int) $order['id'] ?>/payment" class="mt-2" onsubmit="return confirm('Mark this order as paid?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-warning w-100">Mark as Paid</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <h6 class="mb-3" style="color:#f8f7f4;">Update Status</h6>
        <form method="POST" action="/admin/orders/<?= (int) $order['id'] ?>/status" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-8">
                <select class="form-select" name="status">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
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

        <?php if (!empty($statusHistory)): ?>
            <h6 class="mt-4 mb-2 small text-white-50">History</h6>
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

    <div class="col-md-6">
        <h6 class="mb-3" style="color:#f8f7f4;">Shipment / Tracking</h6>
        <form method="POST" action="/admin/orders/<?= (int) $order['id'] ?>/shipment" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-6">
                <input type="text" class="form-control" name="courier" placeholder="Courier" value="<?= e($shipment['courier'] ?? '') ?>">
            </div>
            <div class="col-6">
                <input type="text" class="form-control" name="tracking_number" placeholder="Tracking number" value="<?= e($shipment['tracking_number'] ?? '') ?>">
            </div>
            <div class="col-6">
                <select class="form-select" name="shipment_status">
                    <?php foreach (['pending', 'in_transit', 'out_for_delivery', 'delivered', 'failed'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($shipment['status'] ?? 'pending') === $status ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $status))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6">
                <input type="date" class="form-control" name="estimated_delivery" value="<?= e($shipment['estimated_delivery'] ?? '') ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-gold btn-sm">Save Shipment</button>
            </div>
        </form>
    </div>
</div>
