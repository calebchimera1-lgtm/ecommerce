<?php
/**
 * @var array $items @var array|null $coupon @var array|null $shippingMethod
 * @var array{subtotal:float,discount:float,shippingCost:float,taxAmount:float,total:float} $summary
 * @var array $paymentGateways
 */
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <div class="section-eyebrow">Kymera Collection</div>
            <h1 class="section-title">Checkout</h1>
        </div>

        <form method="POST" action="/checkout" id="checkoutForm">
            <?= csrf_field() ?>
            <div class="row g-5">
                <div class="col-lg-8">
                    <h5 class="mb-3">Billing Address</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label sans small">Full name</label>
                            <input type="text" class="form-control" name="billing_full_name" value="<?= e(old('billing_full_name')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label sans small">Phone</label>
                            <input type="text" class="form-control" name="billing_phone" value="<?= e(old('billing_phone')) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label sans small">Address line 1</label>
                            <input type="text" class="form-control" name="billing_address_line1" value="<?= e(old('billing_address_line1')) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label sans small">Address line 2 (optional)</label>
                            <input type="text" class="form-control" name="billing_address_line2" value="<?= e(old('billing_address_line2')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label sans small">City</label>
                            <input type="text" class="form-control" name="billing_city" value="<?= e(old('billing_city')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label sans small">State / Region</label>
                            <input type="text" class="form-control" name="billing_state" value="<?= e(old('billing_state')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label sans small">Postal code</label>
                            <input type="text" class="form-control" name="billing_postal_code" value="<?= e(old('billing_postal_code')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label sans small">Country</label>
                            <input type="text" class="form-control" name="billing_country" value="<?= e(old('billing_country')) ?>" required placeholder="e.g. United States, Kenya, United Kingdom">
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="same_as_shipping" name="same_as_shipping" value="1" checked>
                        <label class="form-check-label" for="same_as_shipping">Shipping address is the same as billing</label>
                    </div>

                    <div id="shippingAddressFields" class="d-none">
                        <h5 class="mb-3">Shipping Address</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label sans small">Full name</label>
                                <input type="text" class="form-control" name="shipping_full_name" value="<?= e(old('shipping_full_name')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label sans small">Phone</label>
                                <input type="text" class="form-control" name="shipping_phone" value="<?= e(old('shipping_phone')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label sans small">Address line 1</label>
                                <input type="text" class="form-control" name="shipping_address_line1" value="<?= e(old('shipping_address_line1')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label sans small">Address line 2 (optional)</label>
                                <input type="text" class="form-control" name="shipping_address_line2" value="<?= e(old('shipping_address_line2')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label sans small">City</label>
                                <input type="text" class="form-control" name="shipping_city" value="<?= e(old('shipping_city')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label sans small">State / Region</label>
                                <input type="text" class="form-control" name="shipping_state" value="<?= e(old('shipping_state')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label sans small">Postal code</label>
                                <input type="text" class="form-control" name="shipping_postal_code" value="<?= e(old('shipping_postal_code')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label sans small">Country</label>
                                <input type="text" class="form-control" name="shipping_country" value="<?= e(old('shipping_country')) ?>">
                            </div>
                        </div>
                    </div>

                    <h5 class="mb-3">Payment Method</h5>
                    <div class="mb-4">
                        <?php foreach ($paymentGateways as $index => $gateway): ?>
                            <div class="form-check mb-2">
                                <input type="radio" class="form-check-input" id="payment_<?= e($gateway['slug']) ?>"
                                       name="payment_method" value="<?= e($gateway['slug']) ?>"
                                       <?= !$gateway['configured'] ? 'disabled' : '' ?>
                                       <?= $gateway['configured'] && $index === 0 ? 'checked' : '' ?>>
                                <label class="form-check-label <?= !$gateway['configured'] ? 'text-white-50' : '' ?>" for="payment_<?= e($gateway['slug']) ?>">
                                    <?= e($gateway['label']) ?>
                                    <?php if (!$gateway['configured']): ?>
                                        <span class="sans small">&mdash; currently unavailable</span>
                                    <?php endif; ?>
                                </label>
                                <?php if ($gateway['slug'] === 'mpesa' && $gateway['configured']): ?>
                                    <input type="text" class="form-control form-control-sm mt-1" name="mpesa_phone" placeholder="M-Pesa phone number (2547XXXXXXXX)">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="p-4 rounded" style="background:var(--kymera-black-soft);border:1px solid rgba(255,255,255,0.08);">
                        <h5 class="mb-3">Order Summary</h5>
                        <?php foreach ($items as $item): ?>
                            <div class="d-flex justify-content-between sans small mb-2">
                                <span class="text-white-50"><?= e($item['product_name']) ?> &times; <?= (int) $item['quantity'] ?></span>
                                <span><?= money((float) $item['price'] * (int) $item['quantity']) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <hr class="border-secondary">
                        <div class="sans small">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-white-50">Subtotal</span>
                                <span><?= money($summary['subtotal']) ?></span>
                            </div>
                            <?php if ($summary['discount'] > 0): ?>
                                <div class="d-flex justify-content-between mb-2 text-warning">
                                    <span>Discount<?php if ($coupon !== null): ?> (<?= e($coupon['code']) ?>)<?php endif; ?></span>
                                    <span>-<?= money($summary['discount']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-white-50">Shipping<?php if ($shippingMethod !== null): ?> (<?= e($shippingMethod['name']) ?>)<?php endif; ?></span>
                                <span><?= $summary['shippingCost'] === 0.0 ? 'Free' : money($summary['shippingCost']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-white-50">Estimated Tax</span>
                                <span><?= money($summary['taxAmount']) ?></span>
                            </div>
                            <hr class="border-secondary">
                            <div class="d-flex justify-content-between fs-5">
                                <span>Total</span>
                                <span class="text-warning"><?= money($summary['total']) ?></span>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-gold w-100 mt-4">Place Order</button>
                        <p class="sans small text-white-50 mt-2 mb-0">Final tax is calculated from your shipping address above.</p>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var checkbox = document.getElementById('same_as_shipping');
    var fields = document.getElementById('shippingAddressFields');
    var shippingInputs = fields.querySelectorAll('input');

    function sync() {
        var same = checkbox.checked;
        fields.classList.toggle('d-none', same);
        shippingInputs.forEach(function (input) { input.required = !same; });
    }

    checkbox.addEventListener('change', sync);
    sync();
});
</script>
