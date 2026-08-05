<?php
/** @var array|null $staffUser @var array $roles @var array $statuses */
$isEdit = $staffUser !== null;
$action = $isEdit ? '/admin/users/' . (int) $staffUser['id'] : '/admin/users';

$field = static function (string $key, mixed $default = '') use ($isEdit, $staffUser): mixed {
    return $isEdit ? ($staffUser[$key] ?? $default) : old($key, $default);
};
?>
<h4 class="mb-4" style="color:#f8f7f4;"><?= $isEdit ? 'Edit Staff User' : 'New Staff User' ?></h4>
<form method="POST" action="<?= e($action) ?>" class="row g-3" style="max-width:640px;">
    <?= csrf_field() ?>
    <div class="col-md-6">
        <label class="form-label" for="first_name">First name</label>
        <input type="text" class="form-control" id="first_name" name="first_name" required value="<?= e((string) $field('first_name')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="last_name">Last name</label>
        <input type="text" class="form-control" id="last_name" name="last_name" required value="<?= e((string) $field('last_name')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="email">Email</label>
        <input type="email" class="form-control" id="email" name="email" required value="<?= e((string) $field('email')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="role_id">Role</label>
        <select class="form-select" id="role_id" name="role_id" required>
            <?php foreach ($roles as $role): ?>
                <option value="<?= (int) $role['id'] ?>" <?= (string) $field('role_id') === (string) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="password"><?= $isEdit ? 'New password (optional)' : 'Password' ?></label>
        <input type="password" class="form-control" id="password" name="password" <?= $isEdit ? '' : 'required' ?>>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="password_confirmation">Confirm password</label>
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" <?= $isEdit ? '' : 'required' ?>>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="status">Status</label>
        <select class="form-select" id="status" name="status">
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status) ?>" <?= (string) $field('status', 'active') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-gold"><?= $isEdit ? 'Save Changes' : 'Create Staff User' ?></button>
        <a href="/admin/users" class="btn btn-outline-light">Cancel</a>
    </div>
</form>
