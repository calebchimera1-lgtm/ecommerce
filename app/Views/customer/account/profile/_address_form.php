<?php
/** @var string $formAction @var array|null $addr */
$field = static fn (string $key, mixed $default = '') => $addr[$key] ?? $default;
?>
<form method="POST" action="<?= e($formAction) ?>" class="row g-2">
    <?= csrf_field() ?>
    <div class="col-md-6">
        <label class="form-label sans small">Full name</label>
        <input type="text" class="form-control form-control-sm" name="full_name" value="<?= e((string) $field('full_name')) ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label sans small">Phone</label>
        <input type="text" class="form-control form-control-sm" name="phone" value="<?= e((string) $field('phone')) ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label sans small">Type</label>
        <select class="form-select form-select-sm" name="type">
            <option value="shipping" <?= $field('type', 'shipping') === 'shipping' ? 'selected' : '' ?>>Shipping</option>
            <option value="billing" <?= $field('type') === 'billing' ? 'selected' : '' ?>>Billing</option>
        </select>
    </div>
    <div class="col-md-6 d-flex align-items-end">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" name="is_default" value="1" id="default<?= e($formAction) ?>" <?= (int) $field('is_default', 0) === 1 ? 'checked' : '' ?>>
            <label class="form-check-label sans small" for="default<?= e($formAction) ?>">Set as default</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label sans small">Address line 1</label>
        <input type="text" class="form-control form-control-sm" name="address_line1" value="<?= e((string) $field('address_line1')) ?>" required>
    </div>
    <div class="col-12">
        <label class="form-label sans small">Address line 2</label>
        <input type="text" class="form-control form-control-sm" name="address_line2" value="<?= e((string) $field('address_line2')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label sans small">City</label>
        <input type="text" class="form-control form-control-sm" name="city" value="<?= e((string) $field('city')) ?>" required>
    </div>
    <div class="col-md-4">
        <label class="form-label sans small">State</label>
        <input type="text" class="form-control form-control-sm" name="state" value="<?= e((string) $field('state')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label sans small">Postal code</label>
        <input type="text" class="form-control form-control-sm" name="postal_code" value="<?= e((string) $field('postal_code')) ?>">
    </div>
    <div class="col-12">
        <label class="form-label sans small">Country</label>
        <input type="text" class="form-control form-control-sm" name="country" value="<?= e((string) $field('country')) ?>" required>
    </div>
    <div class="col-12 mt-2">
        <button type="submit" class="btn btn-sm btn-gold"><?= $addr === null ? 'Add Address' : 'Save Address' ?></button>
    </div>
</form>
