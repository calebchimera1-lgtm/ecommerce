<?php /** @var array $categories */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0" style="color:#f8f7f4;">Categories</h4>
    <a href="/admin/categories/create" class="btn btn-gold btn-sm">+ New Category</a>
</div>
<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Status</th>
                <th>Sort</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($categories)): ?>
                <tr><td colspan="5" class="text-white-50">No categories yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td><?= str_repeat('&mdash; ', $category['depth']) ?><?= e($category['name']) ?></td>
                    <td class="text-white-50"><?= e($category['slug']) ?></td>
                    <td>
                        <?php if ((int) $category['is_active'] === 1): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $category['sort_order'] ?></td>
                    <td class="text-end">
                        <a href="/admin/categories/<?= (int) $category['id'] ?>/edit" class="btn btn-sm btn-outline-light">Edit</a>
                        <form method="POST" action="/admin/categories/<?= (int) $category['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this category?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
