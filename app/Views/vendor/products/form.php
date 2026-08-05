<?php
/**
 * @var array|null $product @var array $images @var array $attributes
 * @var array $specifications @var array $categories
 */
$isEdit = $product !== null;
$action = $isEdit ? '/vendor/products/' . (int) $product['id'] : '/vendor/products';

$field = static function (string $key, mixed $default = '') use ($isEdit, $product): mixed {
    if ($isEdit) {
        return $product[$key] ?? $default;
    }
    return old($key, $default);
};

$statusBadge = static function (string $status): string {
    return match ($status) {
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        default => 'bg-secondary',
    };
};
?>
<h4 class="mb-3" style="color:#f8f7f4;"><?= $isEdit ? 'Edit Product' : 'New Product' ?></h4>

<?php if ($isEdit): ?>
    <div class="mb-4">
        <span class="badge <?= $statusBadge($product['approval_status']) ?>"><?= e(ucfirst($product['approval_status'])) ?></span>
        <?php if ($product['approval_status'] === 'pending'): ?>
            <span class="text-white-50 small ms-2">Awaiting admin review - not yet visible on the storefront.</span>
        <?php elseif ($product['approval_status'] === 'rejected' && !empty($product['rejection_reason'])): ?>
            <span class="text-danger small ms-2">Rejected: <?= e($product['rejection_reason']) ?></span>
        <?php elseif ($product['approval_status'] === 'approved'): ?>
            <span class="text-white-50 small ms-2">Live on the storefront (if also marked Active below). Saving changes will resubmit this listing for review.</span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="POST" action="<?= e($action) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label" for="name">Product name</label>
            <input type="text" class="form-control" id="name" name="name" required value="<?= e((string) $field('name')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="sku">SKU</label>
            <input type="text" class="form-control" id="sku" name="sku" value="<?= e((string) $field('sku')) ?>"
                   placeholder="<?= $isEdit ? '' : 'Leave blank to auto-generate' ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label" for="barcode">Barcode</label>
            <input type="text" class="form-control" id="barcode" name="barcode" value="<?= e((string) $field('barcode')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="category_id">Category</label>
            <select class="form-select" id="category_id" name="category_id" required>
                <option value="">Select a category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= (int) $field('category_id', 0) === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= str_repeat('&mdash; ', $category['depth']) ?><?= e($category['name']) ?>
                        (<?= rtrim(rtrim(number_format($category['effective_commission_rate'], 2), '0'), '.') ?>% commission)
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text text-white-50">The commission rate shown is what Kymera Collection retains on each sale in that category.</div>
        </div>

        <div class="col-12">
            <label class="form-label" for="short_description">Short description</label>
            <input type="text" class="form-control" id="short_description" name="short_description" maxlength="500"
                   value="<?= e((string) $field('short_description')) ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="description">Full description</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?= e((string) $field('description')) ?></textarea>
        </div>

        <div class="col-md-3">
            <label class="form-label" for="price">Price</label>
            <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required value="<?= e((string) $field('price')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="sale_price">Sale price</label>
            <input type="number" step="0.01" min="0" class="form-control" id="sale_price" name="sale_price" value="<?= e((string) $field('sale_price')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="cost_price">Your cost price</label>
            <input type="number" step="0.01" min="0" class="form-control" id="cost_price" name="cost_price" value="<?= e((string) $field('cost_price')) ?>">
            <div class="form-text text-white-50">For your own records - never shown to customers.</div>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="weight_grams">Weight (grams)</label>
            <input type="number" min="0" class="form-control" id="weight_grams" name="weight_grams" value="<?= e((string) $field('weight_grams')) ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label" for="stock_quantity">Stock quantity</label>
            <input type="number" min="0" class="form-control" id="stock_quantity" name="stock_quantity" required value="<?= e((string) $field('stock_quantity', '0')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="low_stock_threshold">Low stock threshold</label>
            <input type="number" min="0" class="form-control" id="low_stock_threshold" name="low_stock_threshold" value="<?= e((string) $field('low_stock_threshold', '5')) ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= (!$isEdit || (int) $field('is_active', 1) === 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Active (show once approved)</label>
            </div>
        </div>
    </div>

    <hr class="my-4 border-secondary">

    <h6 class="text-white-50 mb-3">Specifications</h6>
    <div id="specs-rows">
        <?php if (empty($specifications)): ?>
            <div class="row g-2 mb-2 spec-row">
                <div class="col-5"><input type="text" class="form-control" name="spec_keys[]" placeholder="e.g. Material"></div>
                <div class="col-6"><input type="text" class="form-control" name="spec_values[]" placeholder="e.g. Italian Leather"></div>
                <div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm remove-row">&times;</button></div>
            </div>
        <?php else: ?>
            <?php foreach ($specifications as $key => $value): ?>
                <div class="row g-2 mb-2 spec-row">
                    <div class="col-5"><input type="text" class="form-control" name="spec_keys[]" value="<?= e((string) $key) ?>"></div>
                    <div class="col-6"><input type="text" class="form-control" name="spec_values[]" value="<?= e((string) $value) ?>"></div>
                    <div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm remove-row">&times;</button></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="btn btn-sm btn-outline-light mb-4" id="add-spec-row">+ Add specification</button>

    <h6 class="text-white-50 mb-3">Variants (colors, sizes, etc.)</h6>
    <div id="attr-rows">
        <?php if (empty($attributes)): ?>
            <div class="row g-2 mb-2 attr-row">
                <div class="col-2"><input type="text" class="form-control" name="attr_name[]" placeholder="Color"></div>
                <div class="col-2"><input type="text" class="form-control" name="attr_value[]" placeholder="Black"></div>
                <div class="col-2"><input type="number" step="0.01" class="form-control" name="attr_price_modifier[]" placeholder="+0.00"></div>
                <div class="col-2"><input type="number" class="form-control" name="attr_stock[]" placeholder="Stock"></div>
                <div class="col-3"><input type="text" class="form-control" name="attr_sku_suffix[]" placeholder="SKU suffix"></div>
                <div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm remove-row">&times;</button></div>
            </div>
        <?php else: ?>
            <?php foreach ($attributes as $attribute): ?>
                <div class="row g-2 mb-2 attr-row">
                    <div class="col-2"><input type="text" class="form-control" name="attr_name[]" value="<?= e($attribute['attribute_name']) ?>"></div>
                    <div class="col-2"><input type="text" class="form-control" name="attr_value[]" value="<?= e($attribute['attribute_value']) ?>"></div>
                    <div class="col-2"><input type="number" step="0.01" class="form-control" name="attr_price_modifier[]" value="<?= e($attribute['price_modifier']) ?>"></div>
                    <div class="col-2"><input type="number" class="form-control" name="attr_stock[]" value="<?= e((string) $attribute['stock_quantity']) ?>"></div>
                    <div class="col-3"><input type="text" class="form-control" name="attr_sku_suffix[]" value="<?= e((string) $attribute['sku_suffix']) ?>"></div>
                    <div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm remove-row">&times;</button></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button type="button" class="btn btn-sm btn-outline-light mb-4" id="add-attr-row">+ Add variant</button>

    <h6 class="text-white-50 mb-3">Images</h6>
    <?php if ($isEdit && !empty($images)): ?>
        <div class="d-flex flex-wrap gap-3 mb-3">
            <?php foreach ($images as $image): ?>
                <div class="border border-secondary rounded p-2" style="width:140px;">
                    <img src="<?= e($image['image_path']) ?>" alt="" class="img-fluid rounded mb-2" style="aspect-ratio:1;object-fit:cover;">
                    <?php if ((int) $image['is_primary'] === 1): ?>
                        <span class="badge bg-warning text-dark d-block mb-1">Primary</span>
                    <?php else: ?>
                        <form method="POST" action="/vendor/products/<?= (int) $product['id'] ?>/images/<?= (int) $image['id'] ?>/primary" class="mb-1">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-light w-100">Make primary</button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" action="/vendor/products/<?= (int) $product['id'] ?>/images/<?= (int) $image['id'] ?>/delete" onsubmit="return confirm('Remove this image?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">Remove</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="mb-4">
        <label class="form-label" for="images">Upload images (JPG, PNG, or WEBP)</label>
        <input type="file" class="form-control" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
    </div>

    <h6 class="text-white-50 mb-3">SEO (optional)</h6>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <label class="form-label" for="meta_title">Meta title</label>
            <input type="text" class="form-control" id="meta_title" name="meta_title" maxlength="191"
                   placeholder="Defaults to the product name"
                   value="<?= e((string) $field('meta_title')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="meta_description">Meta description</label>
            <input type="text" class="form-control" id="meta_description" name="meta_description" maxlength="255"
                   placeholder="Defaults to the short description"
                   value="<?= e((string) $field('meta_description')) ?>">
        </div>
    </div>

    <button type="submit" class="btn btn-gold"><?= $isEdit ? 'Save & Resubmit for Review' : 'Submit for Review' ?></button>
    <a href="/vendor/products" class="btn btn-outline-light">Cancel</a>
</form>

<script src="/assets/js/admin-product-form.js"></script>
