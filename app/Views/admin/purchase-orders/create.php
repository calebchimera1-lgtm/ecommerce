<?php /** @var array $suppliers @var array $products */ ?>
<h4 class="mb-4" style="color:#f8f7f4;">New Purchase Order</h4>

<form method="POST" action="/admin/purchase-orders">
    <?= csrf_field() ?>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <label class="form-label" for="supplier_id">Supplier</label>
            <select class="form-select" id="supplier_id" name="supplier_id" required>
                <option value="">Select a supplier</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= (int) $supplier['id'] ?>" <?= old('supplier_id') === (string) $supplier['id'] ? 'selected' : '' ?>>
                        <?= e($supplier['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="expected_at">Expected delivery date (optional)</label>
            <input type="date" class="form-control" id="expected_at" name="expected_at" value="<?= e(old('expected_at')) ?>">
        </div>
    </div>

    <h6 class="text-white-50 mb-3">Line Items</h6>
    <div id="po-item-rows">
        <div class="row g-2 mb-2 po-item-row">
            <div class="col-5">
                <select class="form-select" name="product_id[]">
                    <option value="">Select a product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id'] ?>"><?= e($product['name']) ?> (<?= e($product['sku']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-3"><input type="number" min="1" class="form-control" name="quantity[]" placeholder="Quantity"></div>
            <div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="unit_cost[]" placeholder="Unit cost"></div>
            <div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm remove-row">&times;</button></div>
        </div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-light mb-4" id="add-po-item-row">+ Add line item</button>

    <div>
        <button type="submit" class="btn btn-gold">Create Purchase Order</button>
        <a href="/admin/purchase-orders" class="btn btn-outline-light">Cancel</a>
    </div>
</form>

<script src="/assets/js/admin-po-form.js"></script>
