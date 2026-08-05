<?php
/**
 * @var array $order @var array $items @var array $addresses @var array|null $payment
 * @var array|null $shippingMethod @var array $statusHistory @var array|null $shipment
 */
$billing = $addresses['billing'] ?? null;
$shipping = $addresses['shipping'] ?? null;
$progression = ['pending', 'processing', 'shipped', 'delivered'];
$currentStep = array_search($order['status'], $progression, true);
$isTerminalException = in_array($order['status'], ['cancelled', 'refunded'], true);

$shipmentStatusLabels = [
    'pending' => 'Preparing shipment',
    'in_transit' => 'In transit',
    'out_for_delivery' => 'Out for delivery',
    'delivered' => 'Delivered',
    'failed' => 'Delivery failed',
];
?>
<section class="section">
    <div class="container">
        <?php require dirname(__DIR__, 3) . '/partials/account-nav.php'; ?>

        <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">Order <?= e($order['order_number']) ?></h4>
                <p class="text-white-50 sans small mb-0">Placed <?= e(date('F j, Y', strtotime($order['created_at']))) ?></p>
            </div>
            <a href="/order/<?= e($order['order_number']) ?>/invoice" class="btn btn-outline-gold btn-sm" target="_blank">View Invoice</a>
        </div>

        <?php if ($isTerminalException): ?>
            <div class="alert alert-warning">This order has been <?= e($order['status']) ?>.</div>
        <?php else: ?>
            <div class="d-flex justify-content-between mb-2 sans small text-white-50">
                <?php foreach ($progression as $index => $step): ?>
                    <span class="<?= $currentStep !== false && $index <= $currentStep ? 'text-warning fw-bold' : '' ?>"><?= e(ucfirst($step)) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="progress mb-4" style="height:6px;background:rgba(255,255,255,0.08);">
                <div class="progress-bar bg-warning" style="width:<?= $currentStep !== false ? (($currentStep + 1) / count($progression) * 100) : 0 ?>%;"></div>
            </div>
        <?php endif; ?>

        <?php if ($shipment !== null): ?>
            <div class="p-3 rounded mb-4" style="background:var(--kymera-black-soft);border:1px solid rgba(255,255,255,0.08);">
                <h6 class="text-warning mb-2">Shipment Tracking</h6>
                <div class="sans small">
                    <div>Status: <strong><?= e($shipmentStatusLabels[$shipment['status']] ?? $shipment['status']) ?></strong></div>
                    <?php if (!empty($shipment['courier'])): ?><div>Courier: <?= e($shipment['courier']) ?></div><?php endif; ?>
                    <?php if (!empty($shipment['tracking_number'])): ?><div>Tracking number: <?= e($shipment['tracking_number']) ?></div><?php endif; ?>
                    <?php if (!empty($shipment['estimated_delivery'])): ?><div>Estimated delivery: <?= e(date('F j, Y', strtotime($shipment['estimated_delivery']))) ?></div><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <h6 class="text-white-50 sans small text-uppercase">Billing Address</h6>
                <?php if ($billing !== null): ?>
                    <p class="mb-0 small"><?= e($billing['full_name']) ?><br><?= e($billing['address_line1']) ?><br><?= e($billing['city']) ?>, <?= e($billing['country']) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h6 class="text-white-50 sans small text-uppercase">Shipping Address</h6>
                <?php if ($shipping !== null): ?>
                    <p class="mb-0 small"><?= e($shipping['full_name']) ?><br><?= e($shipping['address_line1']) ?><br><?= e($shipping['city']) ?>, <?= e($shipping['country']) ?></p>
                <?php endif; ?>
                <?php if ($shippingMethod !== null): ?><p class="sans small text-white-50 mt-1">Method: <?= e($shippingMethod['name']) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="table-responsive mb-4">
            <table class="table table-dark align-middle">
                <thead><tr><th>Item</th><th>Qty</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <?= e($item['product_name']) ?>
                                <?php if ($item['vendor_store_name'] !== null): ?>
                                    <br><span class="text-white-50 sans small">Sold by <?= e($item['vendor_store_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $item['quantity'] ?></td>
                            <td class="text-end"><?= money($item['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row justify-content-end mb-5">
            <div class="col-md-5 sans small">
                <div class="d-flex justify-content-between fs-5"><span>Total</span><span class="text-warning"><?= money($order['total']) ?></span></div>
                <div class="d-flex justify-content-between mt-2"><span class="text-white-50">Payment</span><span><?= e(ucfirst($order['payment_method'])) ?> &mdash; <?= e(ucfirst($order['payment_status'])) ?></span></div>
            </div>
        </div>

        <?php if (!empty($statusHistory)): ?>
            <h6 class="mb-3">Order History</h6>
            <ul class="list-unstyled sans small">
                <?php foreach (array_reverse($statusHistory) as $entry): ?>
                    <li class="mb-2 text-white-50">
                        <span class="text-white"><?= e(ucfirst($entry['status'])) ?></span>
                        &mdash; <?= e(date('M j, Y g:ia', strtotime($entry['created_at']))) ?>
                        <?php if (!empty($entry['note'])): ?><br><?= e($entry['note']) ?><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
