<?php
/** @var array|null $category @var array $parentOptions */
$isEdit = $category !== null;
$action = $isEdit ? '/admin/categories/' . (int) $category['id'] : '/admin/categories';
?>
<h4 class="mb-4" style="color:#f8f7f4;"><?= $isEdit ? 'Edit Category' : 'New Category' ?></h4>
<form method="POST" action="<?= e($action) ?>" class="row g-3" style="max-width:640px;">
    <?= csrf_field() ?>
    <div class="col-12">
        <label class="form-label" for="name">Name</label>
        <input type="text" class="form-control" id="name" name="name" required
               value="<?= e($isEdit ? $category['name'] : old('name')) ?>">
    </div>
    <div class="col-12">
        <label class="form-label" for="parent_id">Parent category</label>
        <select class="form-select" id="parent_id" name="parent_id">
            <option value="">None (top-level)</option>
            <?php foreach ($parentOptions as $option): ?>
                <option value="<?= (int) $option['id'] ?>"
                    <?= $isEdit && (int) ($category['parent_id'] ?? 0) === (int) $option['id'] ? 'selected' : '' ?>>
                    <?= str_repeat('&mdash; ', $option['depth']) ?><?= e($option['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Description</label>
        <textarea class="form-control" id="description" name="description" rows="3"><?= e($isEdit ? $category['description'] : old('description')) ?></textarea>
    </div>
    <div class="col-6">
        <label class="form-label" for="sort_order">Sort order</label>
        <input type="number" class="form-control" id="sort_order" name="sort_order"
               value="<?= e((string) ($isEdit ? $category['sort_order'] : old('sort_order', '0'))) ?>">
    </div>
    <div class="col-6 d-flex align-items-end">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                <?= (!$isEdit || (int) $category['is_active'] === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-gold"><?= $isEdit ? 'Save Changes' : 'Create Category' ?></button>
        <a href="/admin/categories" class="btn btn-outline-light">Cancel</a>
    </div>
</form>
