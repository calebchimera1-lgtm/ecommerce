<?php /** @var array $coupons */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0" style="color:#f8f7f4;">Coupons</h4>
    <a href="/admin/coupons/create" class="btn btn-gold btn-sm">+ New Coupon</a>
</div>
<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>Code</th>
                <th>Type</th>
                <th>Value</th>
                <th>Usage</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($coupons)): ?>
                <tr><td colspan="6" class="text-white-50">No coupons yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($coupons as $coupon): ?>
                <tr>
                    <td><code><?= e($coupon['code']) ?></code></td>
                    <td><?= e(ucfirst($coupon['type'])) ?></td>
                    <td><?= $coupon['type'] === 'percentage' ? e($coupon['value']) . '%' : money($coupon['value']) ?></td>
                    <td><?= (int) $coupon['used_count'] ?><?= $coupon['usage_limit'] !== null ? ' / ' . (int) $coupon['usage_limit'] : '' ?></td>
                    <td>
                        <?php if ((int) $coupon['is_active'] === 1): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/admin/coupons/<?= (int) $coupon['id'] ?>/edit" class="btn btn-sm btn-outline-light">Edit</a>
                        <form method="POST" action="/admin/coupons/<?= (int) $coupon['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this coupon?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
