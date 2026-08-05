<?php /** @var array $testimonials */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0" style="color:#f8f7f4;">Testimonials</h4>
    <a href="/admin/testimonials/create" class="btn btn-gold btn-sm">+ New Testimonial</a>
</div>
<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Rating</th>
                <th>Message</th>
                <th>Status</th>
                <th class="text-end">Sort</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($testimonials)): ?>
                <tr><td colspan="6" class="text-white-50">No testimonials yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($testimonials as $testimonial): ?>
                <tr>
                    <td><?= e($testimonial['customer_name']) ?></td>
                    <td><?= str_repeat('&#9733;', (int) $testimonial['rating']) . str_repeat('&#9734;', 5 - (int) $testimonial['rating']) ?></td>
                    <td class="text-white-50 small" style="max-width:320px;"><?= e(mb_strimwidth($testimonial['message'], 0, 100, '...')) ?></td>
                    <td>
                        <?php if ((int) $testimonial['is_active'] === 1): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= (int) $testimonial['sort_order'] ?></td>
                    <td class="text-end">
                        <a href="/admin/testimonials/<?= (int) $testimonial['id'] ?>/edit" class="btn btn-sm btn-outline-light">Edit</a>
                        <form method="POST" action="/admin/testimonials/<?= (int) $testimonial['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this testimonial?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
