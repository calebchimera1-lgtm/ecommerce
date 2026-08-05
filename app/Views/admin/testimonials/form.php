<?php
/** @var array|null $testimonial */
$isEdit = $testimonial !== null;
$action = $isEdit ? '/admin/testimonials/' . (int) $testimonial['id'] : '/admin/testimonials';

$field = static function (string $key, mixed $default = '') use ($isEdit, $testimonial): mixed {
    return $isEdit ? ($testimonial[$key] ?? $default) : old($key, $default);
};
?>
<h4 class="mb-4" style="color:#f8f7f4;"><?= $isEdit ? 'Edit Testimonial' : 'New Testimonial' ?></h4>
<form method="POST" action="<?= e($action) ?>" enctype="multipart/form-data" class="row g-3" style="max-width:640px;">
    <?= csrf_field() ?>
    <div class="col-12">
        <label class="form-label" for="customer_name">Customer name</label>
        <input type="text" class="form-control" id="customer_name" name="customer_name" required maxlength="150" value="<?= e((string) $field('customer_name')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="rating">Rating</label>
        <select class="form-select" id="rating" name="rating" required>
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <option value="<?= $i ?>" <?= (int) $field('rating', 5) === $i ? 'selected' : '' ?>><?= $i ?> star<?= $i === 1 ? '' : 's' ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="sort_order">Sort order</label>
        <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= e((string) $field('sort_order', '0')) ?>">
    </div>
    <div class="col-12">
        <label class="form-label" for="message">Message</label>
        <textarea class="form-control" id="message" name="message" rows="3" maxlength="500" required><?= e((string) $field('message')) ?></textarea>
    </div>

    <?php if ($isEdit && !empty($testimonial['customer_photo'])): ?>
        <div class="col-12">
            <img src="<?= e($testimonial['customer_photo']) ?>" alt="" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;">
        </div>
    <?php endif; ?>
    <div class="col-12">
        <label class="form-label" for="customer_photo">Customer photo (optional)</label>
        <input type="file" class="form-control" id="customer_photo" name="customer_photo" accept="image/jpeg,image/png,image/webp">
    </div>

    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                <?= (!$isEdit || (int) $field('is_active', 1) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Active (shown on homepage)</label>
        </div>
    </div>
    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-gold"><?= $isEdit ? 'Save Changes' : 'Create Testimonial' ?></button>
        <a href="/admin/testimonials" class="btn btn-outline-light">Cancel</a>
    </div>
</form>
