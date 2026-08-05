<?php
/** @var array|null $category */
$isEdit = $category !== null;
$action = $isEdit ? '/admin/blog-categories/' . (int) $category['id'] : '/admin/blog-categories';
?>
<h4 class="mb-4" style="color:#f8f7f4;"><?= $isEdit ? 'Edit Blog Category' : 'New Blog Category' ?></h4>
<form method="POST" action="<?= e($action) ?>" class="row g-3" style="max-width:640px;">
    <?= csrf_field() ?>
    <div class="col-12">
        <label class="form-label" for="name">Name</label>
        <input type="text" class="form-control" id="name" name="name" required
               value="<?= e($isEdit ? $category['name'] : old('name')) ?>">
    </div>
    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-gold"><?= $isEdit ? 'Save Changes' : 'Create Category' ?></button>
        <a href="/admin/blog-categories" class="btn btn-outline-light">Cancel</a>
    </div>
</form>
