<?php /** @var array $role @var array $permissionGroups @var array $grantedSlugs */ ?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1" style="color:#f8f7f4;"><?= e($role['name']) ?> Permissions</h4>
        <p class="text-white-50 small mb-0"><?= e($role['description'] ?? '') ?></p>
    </div>
    <a href="/admin/roles" class="btn btn-outline-light btn-sm">&larr; Back to Roles</a>
</div>

<form method="POST" action="/admin/roles/<?= (int) $role['id'] ?>/permissions">
    <?= csrf_field() ?>
    <div class="row g-4">
        <?php foreach ($permissionGroups as $module => $permissions): ?>
            <div class="col-md-4">
                <h6 class="text-uppercase text-white-50 small mb-2"><?= e($module) ?></h6>
                <?php foreach ($permissions as $permission): ?>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="perm-<?= (int) $permission['id'] ?>"
                               name="permissions[]" value="<?= (int) $permission['id'] ?>"
                               <?= in_array($permission['slug'], $grantedSlugs, true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="perm-<?= (int) $permission['id'] ?>"><?= e($permission['name']) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-4">
        <button type="submit" class="btn btn-gold">Save Permissions</button>
    </div>
</form>
