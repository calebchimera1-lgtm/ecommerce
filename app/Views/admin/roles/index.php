<?php /** @var array $roles */ ?>
<h4 class="mb-4" style="color:#f8f7f4;">Roles &amp; Permissions</h4>

<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>Role</th>
                <th>Description</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $role): ?>
                <tr>
                    <td><?= e($role['name']) ?></td>
                    <td class="text-white-50"><?= e($role['description'] ?? '') ?></td>
                    <td class="text-end">
                        <?php if ($role['slug'] === 'customer'): ?>
                            <span class="text-white-50 small">Storefront role &mdash; no admin permissions</span>
                        <?php else: ?>
                            <a href="/admin/roles/<?= (int) $role['id'] ?>/edit" class="btn btn-sm btn-outline-light">Edit Permissions</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
