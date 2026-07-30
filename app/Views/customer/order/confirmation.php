<?php
/**
 * @var array $order @var array $items @var array $addresses @var array|null $payment
 * @var array|null $shippingMethod
 */
$shipping = $addresses['shipping'] ?? null;
$billing = $addresses['billing'] ?? null;
?>
<section class="section">
    <div class="container" style="max-width:800px;">
        <div class="text-center mb-5">
            <i class="fa-solid fa-circle-check fa-3x text-warning mb-3"></i>
            <h1 class="section-title">Thank You for Your Order</h1>
            <p class="text-white-50">Order <strong class="text-white"><?= e($order['order_number']) ?></strong> has been placed successfully.</p>
            <a href="/order/<?= e($order['order_number']) ?>/invoice" class="btn btn-outline-gold btn-sm mt-2" target="_blank">
                <i class="fa-solid fa-file-invoice"></i> View / Print Invoice
            </a>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <h6 class="text-white-50 sans small text-uppercase">Billing Address</h6>
                <?php if ($billing !== null): ?>
                    <p class="mb-0"><?= e($billing['full_name']) ?><br>
                    <?= e($billing['address_line1']) ?><?php if (!empty($billing['address_line2'])): ?>, <?= e($billing['address_line2']) ?><?php endif; ?><br>
                    <?= e($billing['city']) ?><?php if (!empty($billing['state'])): ?>, <?= e($billing['state']) ?><?php endif; ?> <?= e($billing['postal_code']) ?><br>
                    <?= e($billing['country']) ?><br>
                    <?= e($billing['phone']) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h6 class="text-white-50 sans small text-uppercase">Shipping Address</h6>
                <?php if ($shipping !== null): ?>
                    <p class="mb-0"><?= e($shipping['full_name']) ?><br>
                    <?= e($shipping['address_line1']) ?><?php if (!empty($shipping['address_line2'])): ?>, <?= e($shipping['address_line2']) ?><?php endif; ?><br>
                    <?= e($shipping['city']) ?><?php if (!empty($shipping['state'])): ?>, <?= e($shipping['state']) ?><?php endif; ?> <?= e($shipping['postal_code']) ?><br>
                    <?= e($shipping['country']) ?><br>
                    <?= e($shipping['phone']) ?></p>
                <?php endif; ?>
                <?php if ($shippingMethod !== null): ?>
                    <p class="text-white-50 sans small mt-2">Shipping method: <?= e($shippingMethod['name']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive mb-4">
            <table class="table table-dark align-middle">
                <thead>
                    <tr><th>Item</th><th>SKU</th><th>Qty</th><th class="text-end">Total</th></tr>
                </thead>
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

        <div class="row justify-content-end">
            <div class="col-md-5 sans small">
                <div class="d-flex justify-content-between mb-2"><span class="text-white-50">Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
                <?php if ((float) $order['discount_amount'] > 0): ?>
                    <div class="d-flex justify-content-between mb-2 text-warning"><span>Discount</span><span>-<?= money($order['discount_amount']) ?></span></div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-2"><span class="text-white-50">Shipping</span><span><?= money($order['shipping_amount']) ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-white-50">Tax</span><span><?= money($order['tax_amount']) ?></span></div>
                <hr class="border-secondary">
                <div class="d-flex justify-content-between fs-5"><span>Total</span><span class="text-warning"><?= money($order['total']) ?></span></div>
                <div class="d-flex justify-content-between mt-3 sans small">
                    <span class="text-white-50">Payment method</span>
                    <span><?= e(ucfirst($order['payment_method'])) ?> &mdash; <?= e(ucfirst($order['payment_status'])) ?></span>
                </div>
                <?php if ($payment !== null && $payment['status'] === 'pending' && $order['payment_method'] === 'mpesa'): ?>
                    <p class="text-warning small mt-2">Check your phone to complete the M-Pesa payment.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center mt-5">
            <a href="/shop" class="btn btn-outline-gold">Continue Shopping</a>
        </div>
    </div>
</section>
