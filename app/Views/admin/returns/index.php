<?php
/** @var array $returns @var array $filters @var int $page @var int $perPage @var int $total */
$badge = static function (string $status): string {
    return match ($status) {
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'refunded' => 'bg-info text-dark',
        default => 'bg-warning text-dark',
    };
};
?>
<h4 class="mb-4" style="color:#f8f7f4;">Returns</h4>

<form method="GET" action="/admin/returns" class="row g-2 mb-4">
    <div class="col-md-3">
        <select class="form-select" name="status">
            <option value="">All statuses</option>
            <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="approved" <?= $filters['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="rejected" <?= $filters['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            <option value="refunded" <?= $filters['status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-light w-100">Filter</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Requested</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($returns)): ?>
                <tr><td colspan="6" class="text-white-50">No return requests found.</td></tr>
            <?php endif; ?>
            <?php foreach ($returns as $r): ?>
                <tr>
                    <td><a href="/admin/orders/<?= (int) $r['order_id'] ?>" class="text-white" target="_blank"><?= e($r['order_number']) ?></a></td>
                    <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?><br><span class="text-white-50 small"><?= e($r['email']) ?></span></td>
                    <td class="text-white-50" style="max-width:220px;"><?= e($r['reason']) ?></td>
                    <td><span class="badge <?= $badge($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                    <td class="text-white-50"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
                    <td class="text-end"><a href="/admin/returns/<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-light">Review</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
