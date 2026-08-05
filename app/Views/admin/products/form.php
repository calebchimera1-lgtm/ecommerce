<?php
/**
 * @var array|null $product @var array $images @var array $attributes
 * @var array $specifications @var array $categories @var array $brands
 * @var array $suppliers
 */
$isEdit = $product !== null;
$action = $isEdit ? '/admin/products/' . (int) $product['id'] : '/admin/products';

$field = static function (string $key, mixed $default = '') use ($isEdit, $product): mixed {
    if ($isEdit) {
        return $product[$key] ?? $default;
    }
    return old($key, $default);
};
?>
<h4 class="mb-4" style="color:#f8f7f4;"><?= $isEdit ? 'Edit Product' : 'New Product' ?></h4>

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

        <div class="col-md-4">
            <label class="form-label" for="barcode">Barcode</label>
            <input type="text" class="form-control" id="barcode" name="barcode" value="<?= e((string) $field('barcode')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="category_id">Category</label>
            <select class="form-select" id="category_id" name="category_id" required>
                <option value="">Select a category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= (int) $field('category_id', 0) === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= str_repeat('&mdash; ', $category['depth']) ?><?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="brand_id">Brand</label>
            <select class="form-select" id="brand_id" name="brand_id">
                <option value="">No brand</option>
                <?php foreach ($brands as $brand): ?>
                    <option value="<?= (int) $brand['id'] ?>" <?= (int) $field('brand_id', 0) === (int) $brand['id'] ? 'selected' : '' ?>>
                        <?= e($brand['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="supplier_id">Supplier</label>
            <select class="form-select" id="supplier_id" name="supplier_id">
                <option value="">No supplier</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= (int) $supplier['id'] ?>" <?= (int) $field('supplier_id', 0) === (int) $supplier['id'] ? 'selected' : '' ?>>
                        <?= e($supplier['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
            <label class="form-label" for="cost_price">Cost price</label>
            <input type="number" step="0.01" min="0" class="form-control" id="cost_price" name="cost_price" value="<?= e((string) $field('cost_price')) ?>">
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
        <div class="col-md-4 d-flex align-items-end gap-4">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" value="1" <?= (int) $field('is_featured', 0) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_featured">Featured</label>
            </div>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= (!$isEdit || (int) $field('is_active', 1) === 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Active</label>
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
                        <form method="POST" action="/admin/products/<?= (int) $product['id'] ?>/images/<?= (int) $image['id'] ?>/primary" class="mb-1">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-light w-100">Make primary</button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" action="/admin/products/<?= (int) $product['id'] ?>/images/<?= (int) $image['id'] ?>/delete" onsubmit="return confirm('Remove this image?');">
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

    <button type="submit" class="btn btn-gold"><?= $isEdit ? 'Save Changes' : 'Create Product' ?></button>
    <a href="/admin/products" class="btn btn-outline-light">Cancel</a>
</form>

<script src="/assets/js/admin-product-form.js"></script>
