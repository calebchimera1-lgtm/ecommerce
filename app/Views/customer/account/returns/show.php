<?php
/** @var array $returnRequest @var array $items @var array $statusHistory */
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
    <div class="container" style="max-width:720px;">
        <?php require dirname(__DIR__, 3) . '/partials/account-nav.php'; ?>

        <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">Return Request &mdash; Order <?= e($returnRequest['order_number']) ?></h4>
                <span class="badge <?= $badge($returnRequest['status']) ?>"><?= e(ucfirst($returnRequest['status'])) ?></span>
            </div>
            <a href="/account/returns" class="btn btn-outline-gold btn-sm">&larr; My Returns</a>
        </div>

        <p class="text-white-50 mb-4"><strong>Reason:</strong> <?= e($returnRequest['reason']) ?></p>

        <?php if (!empty($returnRequest['admin_note'])): ?>
            <div class="p-3 mb-4 rounded" style="background:var(--kymera-black-soft);border:1px solid rgba(255,255,255,0.08);">
                <strong>Note from our team:</strong>
                <p class="text-white-50 mb-0 mt-1"><?= e($returnRequest['admin_note']) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($returnRequest['status'] === 'refunded'): ?>
            <p class="text-success mb-4">Refunded <?= money($returnRequest['refunded_amount']) ?> on <?= e(date('F j, Y', strtotime($returnRequest['refunded_at']))) ?>.</p>
        <?php endif; ?>

        <div class="table-responsive mb-4">
            <table class="table table-dark align-middle">
                <thead><tr><th>Item</th><th>Qty</th><th>Reason</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['product_name']) ?></td>
                            <td><?= (int) $item['quantity'] ?></td>
                            <td class="text-white-50"><?= e($item['reason'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($statusHistory)): ?>
            <h6 class="mb-2 text-white-50 small text-uppercase">History</h6>
            <ul class="list-unstyled small">
                <?php foreach ($statusHistory as $entry): ?>
                    <li class="text-white-50 mb-1">
                        <span class="text-white"><?= e(ucfirst($entry['status'])) ?></span> &mdash; <?= e(date('M j, Y g:ia', strtotime($entry['created_at']))) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
