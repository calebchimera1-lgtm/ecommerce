<?php
/** @var array $returns */
$badge = static function (string $status): string {
    return match ($status) {
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'refunded' => 'bg-info text-dark',
        default => 'bg-secondary',
    };
};
?>
<section class="section">
    <div class="container">
        <?php require dirname(__DIR__, 3) . '/partials/account-nav.php'; ?>

        <h4 class="mb-4">My Returns</h4>

        <?php if (empty($returns)): ?>
            <p class="text-white-50">You haven't requested any returns. You can request one from an order's detail page once it's delivered.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark align-middle">
                    <thead><tr><th>Order</th><th>Reason</th><th>Status</th><th>Requested</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($returns as $r): ?>
                            <tr>
                                <td><a href="/account/orders/<?= e($r['order_number']) ?>" class="text-white"><?= e($r['order_number']) ?></a></td>
                                <td class="text-white-50"><?= e($r['reason']) ?></td>
                                <td><span class="badge <?= $badge($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                                <td class="text-white-50"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
                                <td class="text-end"><a href="/account/returns/<?= (int) $r['id'] ?>" class="text-warning">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
