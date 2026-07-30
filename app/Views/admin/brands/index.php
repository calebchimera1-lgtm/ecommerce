<?php /** @var array $brands */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0" style="color:#f8f7f4;">Brands</h4>
    <a href="/admin/brands/create" class="btn btn-gold btn-sm">+ New Brand</a>
</div>
<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($brands)): ?>
                <tr><td colspan="4" class="text-white-50">No brands yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($brands as $brand): ?>
                <tr>
                    <td><?= e($brand['name']) ?></td>
                    <td class="text-white-50"><?= e($brand['slug']) ?></td>
                    <td>
                        <?php if ((int) $brand['is_active'] === 1): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/admin/brands/<?= (int) $brand['id'] ?>/edit" class="btn btn-sm btn-outline-light">Edit</a>
                        <form method="POST" action="/admin/brands/<?= (int) $brand['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this brand?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
