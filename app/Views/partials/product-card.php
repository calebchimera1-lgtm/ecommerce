<?php
/** @var array $product */
$discountPercent = null;
if ($product['sale_price'] !== null && (float) $product['price'] > 0) {
    $discountPercent = (int) round((((float) $product['price'] - (float) $product['sale_price']) / (float) $product['price']) * 100);
}
?>
<div class="product-card">
    <a href="/product/<?= e($product['slug']) ?>" class="product-card-media">
        <?php if (!empty($product['primary_image'])): ?>
            <img src="<?= e($product['primary_image']) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
        <?php else: ?>
            <div class="product-card-placeholder"><i class="fa-solid fa-image"></i></div>
        <?php endif; ?>
        <?php if ($discountPercent !== null): ?>
            <span class="product-card-badge">-<?= $discountPercent ?>%</span>
        <?php endif; ?>
    </a>
    <div class="product-card-body">
        <div class="product-card-category"><?= e($product['category_name'] ?? '') ?></div>
        <a href="/product/<?= e($product['slug']) ?>" class="product-card-name"><?= e($product['name']) ?></a>
        <div class="product-card-price">
            <?php if ($product['sale_price'] !== null): ?>
                <span class="text-decoration-line-through text-white-50 small"><?= money($product['price']) ?></span>
                <span class="text-warning"><?= money($product['sale_price']) ?></span>
            <?php else: ?>
                <span><?= money($product['price']) ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>
