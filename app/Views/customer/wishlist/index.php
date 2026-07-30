<?php /** @var array $items */ ?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <div class="section-eyebrow">Kymera Collection</div>
            <h1 class="section-title">Your Wishlist</h1>
        </div>

        <?php if (empty($items)): ?>
            <p class="text-white-50 text-center">Your wishlist is empty. <a href="/shop">Browse the collection &rarr;</a></p>
        <?php else: ?>
            <div class="row">
                <?php foreach ($items as $item): ?>
                    <div class="col-6 col-md-4 col-lg-3 mb-4">
                        <?php
                        $product = [
                            'slug' => $item['slug'],
                            'name' => $item['name'],
                            'price' => $item['price'],
                            'sale_price' => $item['sale_price'],
                            'primary_image' => $item['image_path'],
                            'category_name' => '',
                        ];
                        require dirname(__DIR__, 2) . '/partials/product-card.php';
                        ?>
                        <form method="POST" action="/wishlist/toggle" class="mt-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100">Remove</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
