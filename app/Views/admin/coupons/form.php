<?php
/** @var array|null $coupon */
$isEdit = $coupon !== null;
$action = $isEdit ? '/admin/coupons/' . (int) $coupon['id'] : '/admin/coupons';

$field = static function (string $key, mixed $default = '') use ($isEdit, $coupon): mixed {
    return $isEdit ? ($coupon[$key] ?? $default) : old($key, $default);
};

$toDatetimeLocal = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '';
    }
    return substr(str_replace(' ', 'T', $value), 0, 16);
};
?>
<h4 class="mb-4" style="color:#f8f7f4;"><?= $isEdit ? 'Edit Coupon' : 'New Coupon' ?></h4>
<form method="POST" action="<?= e($action) ?>" class="row g-3" style="max-width:640px;">
    <?= csrf_field() ?>
    <div class="col-md-6">
        <label class="form-label" for="code">Coupon code</label>
        <input type="text" class="form-control text-uppercase" id="code" name="code" required
               value="<?= e((string) $field('code')) ?>" placeholder="e.g. WELCOME10">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="type">Discount type</label>
        <select class="form-select" id="type" name="type" required>
            <option value="percentage" <?= $field('type', 'percentage') === 'percentage' ? 'selected' : '' ?>>Percentage</option>
            <option value="fixed" <?= $field('type') === 'fixed' ? 'selected' : '' ?>>Fixed amount</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="value">Value</label>
        <input type="number" step="0.01" min="0" class="form-control" id="value" name="value" required value="<?= e((string) $field('value')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="max_discount_amount">Max discount amount (optional)</label>
        <input type="number" step="0.01" min="0" class="form-control" id="max_discount_amount" name="max_discount_amount" value="<?= e((string) $field('max_discount_amount')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="min_order_amount">Minimum order amount (optional)</label>
        <input type="number" step="0.01" min="0" class="form-control" id="min_order_amount" name="min_order_amount" value="<?= e((string) $field('min_order_amount')) ?>">
    </div>
    <div class="col-md-6"></div>
    <div class="col-md-6">
        <label class="form-label" for="usage_limit">Total usage limit (optional)</label>
        <input type="number" min="0" class="form-control" id="usage_limit" name="usage_limit" value="<?= e((string) $field('usage_limit')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="per_user_limit">Per-customer usage limit (optional)</label>
        <input type="number" min="0" class="form-control" id="per_user_limit" name="per_user_limit" value="<?= e((string) $field('per_user_limit')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="starts_at">Starts at (optional)</label>
        <input type="datetime-local" class="form-control" id="starts_at" name="starts_at" value="<?= e($toDatetimeLocal($field('starts_at'))) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="expires_at">Expires at (optional)</label>
        <input type="datetime-local" class="form-control" id="expires_at" name="expires_at" value="<?= e($toDatetimeLocal($field('expires_at'))) ?>">
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                <?= (!$isEdit || (int) $field('is_active', 1) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-gold"><?= $isEdit ? 'Save Changes' : 'Create Coupon' ?></button>
        <a href="/admin/coupons" class="btn btn-outline-light">Cancel</a>
    </div>
</form>
