<?php
/** @var array $order @var array $items @var array $addresses @var array|null $payment */
$billing = $addresses['billing'] ?? null;
$shipping = $addresses['shipping'] ?? null;
?>
<div class="invoice-header">
    <div class="brand">Kymera Collection<span>Luxury Redefined</span></div>
    <div class="text-end">
        <div class="fw-bold">INVOICE</div>
        <div class="small text-muted"><?= e($order['order_number']) ?></div>
        <div class="small text-muted"><?= e(date('F j, Y', strtotime($order['created_at']))) ?></div>
    </div>
</div>

<div class="row my-4">
    <div class="col-6">
        <div class="text-muted small text-uppercase mb-1">Billed To</div>
        <?php if ($billing !== null): ?>
            <div><?= e($billing['full_name']) ?></div>
            <div><?= e($billing['address_line1']) ?><?php if (!empty($billing['address_line2'])): ?>, <?= e($billing['address_line2']) ?><?php endif; ?></div>
            <div><?= e($billing['city']) ?><?php if (!empty($billing['state'])): ?>, <?= e($billing['state']) ?><?php endif; ?> <?= e($billing['postal_code']) ?></div>
            <div><?= e($billing['country']) ?></div>
        <?php endif; ?>
    </div>
    <div class="col-6">
        <div class="text-muted small text-uppercase mb-1">Shipped To</div>
        <?php if ($shipping !== null): ?>
            <div><?= e($shipping['full_name']) ?></div>
            <div><?= e($shipping['address_line1']) ?><?php if (!empty($shipping['address_line2'])): ?>, <?= e($shipping['address_line2']) ?><?php endif; ?></div>
            <div><?= e($shipping['city']) ?><?php if (!empty($shipping['state'])): ?>, <?= e($shipping['state']) ?><?php endif; ?> <?= e($shipping['postal_code']) ?></div>
            <div><?= e($shipping['country']) ?></div>
        <?php endif; ?>
    </div>
</div>

<table class="table table-bordered invoice-table">
    <thead>
        <tr>
            <th>Item</th>
            <th>SKU</th>
            <th class="text-end">Unit Price</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= e($item['product_name']) ?></td>
                <td><?= e($item['sku']) ?></td>
                <td class="text-end"><?= money($item['price']) ?></td>
                <td class="text-end"><?= (int) $item['quantity'] ?></td>
                <td class="text-end"><?= money($item['subtotal']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="row justify-content-end">
    <div class="col-5">
        <div class="d-flex justify-content-between"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
        <?php if ((float) $order['discount_amount'] > 0): ?>
            <div class="d-flex justify-content-between"><span>Discount</span><span>-<?= money($order['discount_amount']) ?></span></div>
        <?php endif; ?>
        <div class="d-flex justify-content-between"><span>Shipping</span><span><?= money($order['shipping_amount']) ?></span></div>
        <div class="d-flex justify-content-between"><span>Tax</span><span><?= money($order['tax_amount']) ?></span></div>
        <hr>
        <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span><?= money($order['total']) ?></span></div>
    </div>
</div>

<div class="mt-4 pt-3 border-top">
    <div class="small text-muted">Payment method: <?= e(ucfirst($order['payment_method'])) ?></div>
    <div class="small text-muted">Payment status: <?= e(ucfirst($order['payment_status'])) ?></div>
    <?php if ($payment !== null && $payment['transaction_id'] !== null): ?>
        <div class="small text-muted">Transaction ID: <?= e($payment['transaction_id']) ?></div>
    <?php endif; ?>
</div>

<div class="mt-5 text-center text-muted small">
    Thank you for shopping with Kymera Collection.
</div>
