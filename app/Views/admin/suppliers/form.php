<?php
/** @var array|null $supplier */
$isEdit = $supplier !== null;
$action = $isEdit ? '/admin/suppliers/' . (int) $supplier['id'] : '/admin/suppliers';

$field = static function (string $key, mixed $default = '') use ($isEdit, $supplier): mixed {
    return $isEdit ? ($supplier[$key] ?? $default) : old($key, $default);
};
?>
<h4 class="mb-4" style="color:#f8f7f4;"><?= $isEdit ? 'Edit Supplier' : 'New Supplier' ?></h4>
<form method="POST" action="<?= e($action) ?>" class="row g-3" style="max-width:640px;">
    <?= csrf_field() ?>
    <div class="col-12">
        <label class="form-label" for="name">Name</label>
        <input type="text" class="form-control" id="name" name="name" required value="<?= e((string) $field('name')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="contact_person">Contact person</label>
        <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?= e((string) $field('contact_person')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">Phone</label>
        <input type="text" class="form-control" id="phone" name="phone" value="<?= e((string) $field('phone')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="email">Email</label>
        <input type="email" class="form-control" id="email" name="email" value="<?= e((string) $field('email')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="address">Address</label>
        <input type="text" class="form-control" id="address" name="address" value="<?= e((string) $field('address')) ?>">
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                <?= (!$isEdit || (int) $field('is_active', 1) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-gold"><?= $isEdit ? 'Save Changes' : 'Create Supplier' ?></button>
        <a href="/admin/suppliers" class="btn btn-outline-light">Cancel</a>
    </div>
</form>
