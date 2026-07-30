<?php
/** @var array $user @var array $recentOrders @var int $orderCount @var int $wishlistCount @var int $reviewCount */
?>
<section class="section">
    <div class="container">
        <?php require dirname(__DIR__, 2) . '/partials/account-nav.php'; ?>

        <div class="mb-4">
            <h4 class="mb-1">Welcome back, <?= e($user['first_name']) ?>.</h4>
            <p class="text-white-50 sans small"><?= e($user['email']) ?></p>
        </div>

        <div class="row g-3 mb-5">
            <div class="col-6 col-md-3">
                <div class="p-3 rounded text-center" style="background:var(--kymera-black-soft);border:1px solid rgba(255,255,255,0.08);">
                    <div class="fs-3 text-warning"><?= $orderCount ?></div>
                    <div class="sans small text-white-50">Orders</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded text-center" style="background:var(--kymera-black-soft);border:1px solid rgba(255,255,255,0.08);">
                    <div class="fs-3 text-warning"><?= $wishlistCount ?></div>
                    <div class="sans small text-white-50">Wishlist Items</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded text-center" style="background:var(--kymera-black-soft);border:1px solid rgba(255,255,255,0.08);">
                    <div class="fs-3 text-warning"><?= $reviewCount ?></div>
                    <div class="sans small text-white-50">Reviews Written</div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Recent Orders</h5>
            <a href="/account/orders" class="sans small">View all &rarr;</a>
        </div>

        <?php if (empty($recentOrders)): ?>
            <p class="text-white-50">You haven't placed any orders yet. <a href="/shop">Start shopping &rarr;</a></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark align-middle">
                    <thead><tr><th>Order</th><th>Date</th><th>Status</th><th class="text-end">Total</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><?= e($order['order_number']) ?></td>
                                <td class="text-white-50"><?= e(date('M j, Y', strtotime($order['created_at']))) ?></td>
                                <td><span class="badge bg-secondary"><?= e(ucfirst($order['status'])) ?></span></td>
                                <td class="text-end"><?= money($order['total']) ?></td>
                                <td class="text-end"><a href="/account/orders/<?= e($order['order_number']) ?>" class="sans small">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
