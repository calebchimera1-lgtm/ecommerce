<?php /** @var array $vendor */
$badge = match ($vendor['status']) {
    'approved' => 'bg-success',
    'rejected' => 'bg-danger',
    'suspended' => 'bg-danger',
    default => 'bg-secondary',
};
?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1" style="color:#f8f7f4;"><?= e($vendor['store_name']) ?></h4>
        <span class="badge <?= $badge ?>"><?= e(ucfirst($vendor['status'])) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/payouts/<?= (int) $vendor['id'] ?>" class="btn btn-outline-light btn-sm">Payout Ledger</a>
        <a href="/admin/vendors" class="btn btn-outline-light btn-sm">&larr; Back to Vendors</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <h6 class="text-white-50 small text-uppercase">Contact</h6>
        <p class="mb-1"><?= e($vendor['first_name'] . ' ' . $vendor['last_name']) ?></p>
        <p class="mb-1 text-white-50"><?= e($vendor['email']) ?></p>
        <?php if (!empty($vendor['phone'])): ?>
            <p class="mb-1 text-white-50"><?= e($vendor['phone']) ?></p>
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <h6 class="text-white-50 small text-uppercase">Business</h6>
        <?php if (!empty($vendor['business_registration_number'])): ?>
            <p class="mb-1 text-white-50">Reg. No: <?= e($vendor['business_registration_number']) ?></p>
        <?php endif; ?>
        <p class="mb-1 text-white-50">Applied <?= e(date('F j, Y', strtotime($vendor['created_at']))) ?></p>
        <?php if ($vendor['approved_at'] !== null): ?>
            <p class="mb-1 text-white-50">Approved <?= e(date('F j, Y', strtotime($vendor['approved_at']))) ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($vendor['description'])): ?>
        <div class="col-12">
            <h6 class="text-white-50 small text-uppercase">Store Description</h6>
            <p class="text-white-50" style="white-space:pre-line;"><?= e($vendor['description']) ?></p>
        </div>
    <?php endif; ?>
    <?php if ($vendor['status'] === 'rejected' && !empty($vendor['rejection_reason'])): ?>
        <div class="col-12">
            <h6 class="text-white-50 small text-uppercase">Rejection Reason</h6>
            <p class="text-danger"><?= e($vendor['rejection_reason']) ?></p>
        </div>
    <?php endif; ?>
</div>

<div class="d-flex gap-2 flex-wrap">
    <?php if (in_array($vendor['status'], ['pending', 'rejected'], true)): ?>
        <form method="POST" action="/admin/vendors/<?= (int) $vendor['id'] ?>/approve">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-gold btn-sm">Approve</button>
        </form>
    <?php endif; ?>

    <?php if ($vendor['status'] === 'pending'): ?>
        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="collapse" data-bs-target="#rejectForm">Reject</button>
    <?php endif; ?>

    <?php if ($vendor['status'] === 'approved'): ?>
        <form method="POST" action="/admin/vendors/<?= (int) $vendor['id'] ?>/suspend" onsubmit="return confirm('Suspend this vendor? They will lose access immediately.');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-danger btn-sm">Suspend</button>
        </form>
    <?php endif; ?>

    <?php if ($vendor['status'] === 'suspended'): ?>
        <form method="POST" action="/admin/vendors/<?= (int) $vendor['id'] ?>/reactivate">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-gold btn-sm">Reactivate</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($vendor['status'] === 'pending'): ?>
    <div class="collapse mt-3" id="rejectForm">
        <form method="POST" action="/admin/vendors/<?= (int) $vendor['id'] ?>/reject" class="row g-2" style="max-width:520px;">
            <?= csrf_field() ?>
            <div class="col-12">
                <label class="form-label small" for="rejection_reason">Reason for rejection</label>
                <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="2" maxlength="255" required></textarea>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-outline-danger btn-sm">Confirm Rejection</button>
            </div>
        </form>
    </div>
<?php endif; ?>
