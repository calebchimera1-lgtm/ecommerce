<?php
/**
 * @var array $items @var array|null $coupon @var array|null $shippingMethod
 * @var array $shippingMethods @var array|null $taxRate
 * @var array{subtotal:float,discount:float,shippingCost:float,taxAmount:float,total:float} $summary
 */
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <div class="section-eyebrow">Kymera Collection</div>
            <h1 class="section-title">Your Cart</h1>
        </div>

        <?php if (empty($items)): ?>
            <p class="text-white-50 text-center">Your cart is empty. <a href="/shop">Continue shopping &rarr;</a></p>
        <?php else: ?>
        <div class="row g-5">
            <div class="col-lg-8">
                <?php foreach ($items as $item): ?>
                    <div class="d-flex align-items-center gap-3 pb-3 mb-3 border-bottom border-secondary">
                        <div style="width:80px;height:80px;flex-shrink:0;" class="rounded overflow-hidden bg-black">
                            <?php if (!empty($item['image_path'])): ?>
                                <img src="<?= e($item['image_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <a href="/product/<?= e($item['product_slug']) ?>" class="d-block text-white"><?= e($item['product_name']) ?></a>
                            <?php if (!empty($item['attribute_name'])): ?>
                                <div class="sans small text-white-50"><?= e($item['attribute_name']) ?>: <?= e($item['attribute_value']) ?></div>
                            <?php endif; ?>
                            <div class="sans small text-white-50"><?= money($item['price']) ?> each</div>
                        </div>
                        <form method="POST" action="/cart/update/<?= (int) $item['id'] ?>" class="d-flex align-items-center gap-2">
                            <?= csrf_field() ?>
                            <input type="number" name="quantity" value="<?= (int) $item['quantity'] ?>" min="1" class="form-control form-control-sm" style="width:70px;">
                            <button type="submit" class="btn btn-sm btn-outline-light">Update</button>
                        </form>
                        <div class="sans text-end" style="min-width:90px;"><?= money((float) $item['price'] * (int) $item['quantity']) ?></div>
                        <form method="POST" action="/cart/remove/<?= (int) $item['id'] ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="col-lg-4">
                <div class="p-4 rounded" style="background:var(--kymera-black-soft);border:1px solid rgba(255,255,255,0.08);">
                    <h5 class="mb-3">Order Summary</h5>

                    <div class="sans small mb-4">
                        <?php if ($coupon !== null): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-white-50">Coupon: <code><?= e($coupon['code']) ?></code></span>
                                <form method="POST" action="/cart/coupon/remove">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-link btn-sm text-danger p-0">Remove</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="/cart/coupon" class="d-flex gap-2 mb-3">
                                <?= csrf_field() ?>
                                <input type="text" name="code" class="form-control form-control-sm" placeholder="Coupon code">
                                <button type="submit" class="btn btn-sm btn-outline-gold">Apply</button>
                            </form>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="text-white-50 d-block mb-1">Shipping method</label>
                            <?php foreach ($shippingMethods as $method): ?>
                                <form method="POST" action="/cart/shipping" class="d-flex justify-content-between align-items-center py-1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="shipping_method_id" value="<?= (int) $method['id'] ?>">
                                    <button type="submit" class="btn btn-link btn-sm p-0 text-start <?= ($shippingMethod['id'] ?? null) === $method['id'] ? 'text-warning fw-bold' : 'text-white-50' ?>">
                                        <?= ($shippingMethod['id'] ?? null) === $method['id'] ? '&#9679;' : '&#9675;' ?>
                                        <?= e($method['name']) ?> &mdash; <?= (float) $method['cost'] === 0.0 ? 'Free' : money($method['cost']) ?>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="sans small">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-white-50">Subtotal</span>
                            <span><?= money($summary['subtotal']) ?></span>
                        </div>
                        <?php if ($summary['discount'] > 0): ?>
                            <div class="d-flex justify-content-between mb-2 text-warning">
                                <span>Discount</span>
                                <span>-<?= money($summary['discount']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-white-50">Shipping</span>
                            <span><?= $summary['shippingCost'] === 0.0 ? 'Free' : money($summary['shippingCost']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-white-50">
                                Estimated Tax<?php if ($taxRate !== null): ?> (<?= e($taxRate['rate']) ?>%)<?php endif; ?>
                            </span>
                            <span><?= money($summary['taxAmount']) ?></span>
                        </div>
                        <hr class="border-secondary">
                        <div class="d-flex justify-content-between fs-5">
                            <span>Total</span>
                            <span class="text-warning"><?= money($summary['total']) ?></span>
                        </div>
                    </div>

                    <a href="/checkout" class="btn btn-gold w-100 mt-4">Proceed to Checkout</a>
                    <p class="sans small text-white-50 mt-2 mb-0">Final tax is calculated at checkout based on your shipping address.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
