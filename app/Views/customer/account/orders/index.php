<?php /** @var array $orders */ ?>
<section class="section">
    <div class="container">
        <?php require dirname(__DIR__, 3) . '/partials/account-nav.php'; ?>

        <h4 class="mb-4">Order History</h4>

        <?php if (empty($orders)): ?>
            <p class="text-white-50">You haven't placed any orders yet. <a href="/shop">Start shopping &rarr;</a></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark align-middle">
                    <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th class="text-end">Total</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= e($order['order_number']) ?></td>
                                <td class="text-white-50"><?= e(date('M j, Y', strtotime($order['created_at']))) ?></td>
                                <td><span class="badge bg-secondary"><?= e(ucfirst($order['status'])) ?></span></td>
                                <td class="text-white-50"><?= e(ucfirst($order['payment_status'])) ?></td>
                                <td class="text-end"><?= money($order['total']) ?></td>
                                <td class="text-end">
                                    <a href="/account/orders/<?= e($order['order_number']) ?>" class="sans small">Track</a> &middot;
                                    <a href="/order/<?= e($order['order_number']) ?>/invoice" class="sans small" target="_blank">Invoice</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
