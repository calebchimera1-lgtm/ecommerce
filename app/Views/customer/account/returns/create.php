<?php /** @var array $order @var array $eligibleItems */ ?>
<section class="section">
    <div class="container" style="max-width:720px;">
        <?php require dirname(__DIR__, 3) . '/partials/account-nav.php'; ?>

        <h4 class="mb-1">Request a Return</h4>
        <p class="text-white-50 sans small mb-4">Order <?= e($order['order_number']) ?></p>

        <form method="POST" action="/account/orders/<?= e($order['order_number']) ?>/return">
            <?= csrf_field() ?>

            <div class="mb-4">
                <label class="form-label sans small">Select items to return</label>
                <?php foreach ($eligibleItems as $item): ?>
                    <?php $id = (int) $item['id']; ?>
                    <div class="p-3 mb-2 rounded" style="background:var(--kymera-black-soft);border:1px solid rgba(255,255,255,0.08);">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="check_<?= $id ?>" name="selected_item_id[]" value="<?= $id ?>">
                                    <label class="form-check-label" for="check_<?= $id ?>"><?= e($item['product_name']) ?></label>
                                </div>
                                <div class="text-white-50 small">SKU: <?= e($item['sku']) ?> &middot; <?= (int) $item['returnable_quantity'] ?> available to return</div>
                            </div>
                            <div style="width:160px;">
                                <label class="form-label sans small mb-0">Quantity</label>
                                <input type="number" class="form-control form-control-sm"
                                       name="quantity_<?= $id ?>" min="1" max="<?= (int) $item['returnable_quantity'] ?>" value="1">
                            </div>
                        </div>
                        <div class="mt-2">
                            <input type="text" class="form-control form-control-sm" name="item_reason_<?= $id ?>" placeholder="Reason for this item (optional)">
                        </div>
                    </div>
                <?php endforeach; ?>
                <p class="text-white-50 small mb-0">Only checked items are included in your request; the quantity next to an unchecked item is ignored.</p>
            </div>

            <div class="mb-4">
                <label class="form-label sans small" for="reason">Overall reason for the return</label>
                <textarea class="form-control" id="reason" name="reason" rows="3" maxlength="255" required></textarea>
            </div>

            <button type="submit" class="btn btn-gold">Submit Return Request</button>
            <a href="/account/orders/<?= e($order['order_number']) ?>" class="btn btn-outline-gold">Cancel</a>
        </form>
    </div>
</section>
