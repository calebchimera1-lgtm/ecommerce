<?php /** @var array $reviews @var array $filters @var int $page @var int $perPage @var int $total */ ?>
<h4 class="mb-4" style="color:#f8f7f4;">Reviews</h4>

<form method="GET" action="/admin/reviews" class="row g-2 mb-4">
    <div class="col-md-3">
        <select class="form-select" name="status">
            <option value="">All reviews</option>
            <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="approved" <?= $filters['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
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
                <th>Product</th>
                <th>Customer</th>
                <th>Rating</th>
                <th>Comment</th>
                <th>Status</th>
                <th>Posted</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reviews)): ?>
                <tr><td colspan="7" class="text-white-50">No reviews found.</td></tr>
            <?php endif; ?>
            <?php foreach ($reviews as $review): ?>
                <tr>
                    <td><a href="/product/<?= e($review['product_slug']) ?>" class="text-white" target="_blank"><?= e($review['product_name']) ?></a></td>
                    <td><?= e($review['first_name'] . ' ' . $review['last_name']) ?></td>
                    <td><?= str_repeat('&#9733;', (int) $review['rating']) . str_repeat('&#9734;', 5 - (int) $review['rating']) ?></td>
                    <td class="small text-white-50" style="max-width:260px;"><?= e($review['title'] ?? '') ?><br><?= e(mb_strimwidth((string) ($review['comment'] ?? ''), 0, 120, '...')) ?></td>
                    <td>
                        <?php if ((int) $review['is_approved'] === 1): ?>
                            <span class="badge bg-success">Approved</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-white-50"><?= e(date('M j, Y', strtotime($review['created_at']))) ?></td>
                    <td class="text-end">
                        <?php if ((int) $review['is_approved'] !== 1): ?>
                            <form method="POST" action="/admin/reviews/<?= (int) $review['id'] ?>/approve" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-success">Approve</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="/admin/reviews/<?= (int) $review['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Remove this review?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
