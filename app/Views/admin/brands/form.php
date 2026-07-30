<?php
/** @var array|null $brand */
$isEdit = $brand !== null;
$action = $isEdit ? '/admin/brands/' . (int) $brand['id'] : '/admin/brands';
?>
<h4 class="mb-4" style="color:#f8f7f4;"><?= $isEdit ? 'Edit Brand' : 'New Brand' ?></h4>
<form method="POST" action="<?= e($action) ?>" class="row g-3" style="max-width:640px;">
    <?= csrf_field() ?>
    <div class="col-12">
        <label class="form-label" for="name">Name</label>
        <input type="text" class="form-control" id="name" name="name" required
               value="<?= e($isEdit ? $brand['name'] : old('name')) ?>">
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Description</label>
        <textarea class="form-control" id="description" name="description" rows="3"><?= e($isEdit ? $brand['description'] : old('description')) ?></textarea>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                <?= (!$isEdit || (int) $brand['is_active'] === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-gold"><?= $isEdit ? 'Save Changes' : 'Create Brand' ?></button>
        <a href="/admin/brands" class="btn btn-outline-light">Cancel</a>
    </div>
</form>
