<?php
/**
 * @var array $product @var array $images @var array $groupedAttributes @var array $specifications
 * @var array $reviews @var array{count:int,average:float} $ratingSummary @var bool $canReview
 * @var bool $isLoggedIn @var bool $isWishlisted @var array $relatedProducts
 */
$hasSale = $product['sale_price'] !== null;
$stockLabel = match ($product['stock_status']) {
    'in_stock' => 'In Stock',
    'backorder' => 'Available on Backorder',
    default => 'Out of Stock',
};
$stockClass = match ($product['stock_status']) {
    'in_stock' => 'text-success',
    'backorder' => 'text-warning',
    default => 'text-danger',
};
?>
<section class="section pb-2">
    <div class="container">
        <nav class="sans small text-white-50 mb-4">
            <a href="/shop">Shop</a>
            <?php if (!empty($product['category_slug'])): ?>
                / <a href="/shop/category/<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a>
            <?php endif; ?>
            / <span class="text-white"><?= e($product['name']) ?></span>
        </nav>

        <div class="row g-5">
            <div class="col-lg-6">
                <div class="product-gallery-main" id="galleryMain">
                    <?php if (!empty($images)): ?>
                        <img src="<?= e($images[0]['image_path']) ?>" alt="<?= e($product['name']) ?>" id="galleryMainImage">
                    <?php else: ?>
                        <div class="product-card-placeholder" style="height:100%;"><i class="fa-solid fa-image fa-3x"></i></div>
                    <?php endif; ?>
                </div>
                <?php if (count($images) > 1): ?>
                    <div class="product-gallery-thumbs">
                        <?php foreach ($images as $index => $image): ?>
                            <div class="product-gallery-thumb <?= $index === 0 ? 'active' : '' ?>" data-image="<?= e($image['image_path']) ?>">
                                <img src="<?= e($image['image_path']) ?>" alt="">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-6">
                <?php if (!empty($product['brand_name'])): ?>
                    <div class="product-card-category mb-2"><?= e($product['brand_name']) ?></div>
                <?php endif; ?>
                <h1 class="mb-2" style="font-size:2rem;"><?= e($product['name']) ?></h1>

                <div class="d-flex align-items-center gap-2 mb-3 sans">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fa-<?= $i <= round($ratingSummary['average']) ? 'solid' : 'regular' ?> fa-star text-warning small"></i>
                    <?php endfor; ?>
                    <span class="text-white-50 small">
                        <?= $ratingSummary['average'] ?> (<?= $ratingSummary['count'] ?> review<?= $ratingSummary['count'] === 1 ? '' : 's' ?>)
                    </span>
                </div>

                <div class="mb-3">
                    <?php if ($hasSale): ?>
                        <span class="fs-3 text-warning"><?= money($product['sale_price']) ?></span>
                        <span class="fs-6 text-decoration-line-through text-white-50 ms-2"><?= money($product['price']) ?></span>
                    <?php else: ?>
                        <span class="fs-3"><?= money($product['price']) ?></span>
                    <?php endif; ?>
                </div>

                <p class="sans <?= $stockClass ?> mb-3"><i class="fa-solid fa-circle-check"></i> <?= e($stockLabel) ?></p>

                <?php if (!empty($product['short_description'])): ?>
                    <p class="text-white-50"><?= e($product['short_description']) ?></p>
                <?php endif; ?>

                <div class="mt-4">
                    <form method="POST" action="/cart/add" id="addToCartForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">

                        <?php if (!empty($groupedAttributes)): ?>
                            <div class="mb-3">
                                <label class="sans small text-white-50 mb-1 d-block" for="product_attribute_id">Options</label>
                                <select class="form-select" id="product_attribute_id" name="product_attribute_id" style="max-width:320px;">
                                    <?php foreach ($groupedAttributes as $attributeName => $options): ?>
                                        <?php foreach ($options as $option): ?>
                                            <option value="<?= (int) $option['id'] ?>">
                                                <?= e($attributeName) ?>: <?= e($option['attribute_value']) ?>
                                                <?php if ((float) $option['price_modifier'] > 0): ?> (+<?= money($option['price_modifier']) ?>)<?php endif; ?>
                                                <?= (int) $option['stock_quantity'] === 0 ? ' - Out of stock' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex align-items-center gap-3">
                            <input type="number" name="quantity" value="1" min="1" class="form-control" style="width:80px;">
                            <button type="submit" class="btn btn-gold btn-lg px-4" <?= $product['stock_status'] === 'out_of_stock' ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-bag-shopping"></i> Add to Cart
                            </button>
                            <?php if ($isLoggedIn): ?>
                                <button type="submit" formaction="/wishlist/toggle" class="btn btn-outline-gold btn-lg" title="<?= $isWishlisted ? 'Remove from wishlist' : 'Add to wishlist' ?>">
                                    <i class="fa-<?= $isWishlisted ? 'solid' : 'regular' ?> fa-heart"></i>
                                </button>
                            <?php else: ?>
                                <a href="/login" class="btn btn-outline-gold btn-lg" title="Sign in to save to your wishlist">
                                    <i class="fa-regular fa-heart"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="mt-4 sans small text-white-50">
                    SKU: <?= e($product['sku']) ?>
                    <?php if (!empty($product['barcode'])): ?> &middot; Barcode: <?= e($product['barcode']) ?><?php endif; ?>
                    <?php if ($product['weight_grams'] !== null): ?> &middot; Weight: <?= (int) $product['weight_grams'] ?>g<?php endif; ?>
                </div>
                <?php if (!empty($product['vendor_store_name'])): ?>
                    <div class="mt-2 sans small">
                        Sold by <a href="/store/<?= e($product['vendor_slug']) ?>" class="text-warning"><?= e($product['vendor_store_name']) ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($product['description']) || !empty($specifications)): ?>
        <div class="row mt-5 pt-4 border-top border-secondary">
            <?php if (!empty($product['description'])): ?>
                <div class="col-lg-7 mb-4">
                    <h5 class="mb-3">Description</h5>
                    <p class="text-white-50" style="white-space:pre-line;"><?= e($product['description']) ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($specifications)): ?>
                <div class="col-lg-5">
                    <h5 class="mb-3">Specifications</h5>
                    <table class="table table-borderless spec-table mb-0">
                        <?php foreach ($specifications as $key => $value): ?>
                            <tr>
                                <td><?= e((string) $key) ?></td>
                                <td class="text-white"><?= e((string) $value) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="row mt-5 pt-4 border-top border-secondary">
            <div class="col-lg-8">
                <h5 class="mb-4">Customer Reviews</h5>
                <?php if (empty($reviews)): ?>
                    <p class="text-white-50">No reviews yet. Be the first to share your thoughts.</p>
                <?php endif; ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="sans">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa-<?= $i <= (int) $review['rating'] ? 'solid' : 'regular' ?> fa-star text-warning small"></i>
                            <?php endfor; ?>
                            <strong class="ms-2"><?= e($review['title'] ?? '') ?></strong>
                        </div>
                        <p class="text-white-50 mt-2 mb-1"><?= e($review['comment'] ?? '') ?></p>
                        <div class="sans small text-white-50">&mdash; <?= e($review['first_name']) ?> <?= e(mb_substr((string) $review['last_name'], 0, 1)) ?>.</div>
                    </div>
                <?php endforeach; ?>

                <?php if ($canReview): ?>
                    <div class="mt-4">
                        <h6 class="mb-3">Write a Review</h6>
                        <form method="POST" action="/product/<?= e($product['slug']) ?>/reviews">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label sans small">Rating</label>
                                <select name="rating" class="form-select" style="max-width:160px;" required>
                                    <option value="">Select</option>
                                    <option value="5">5 - Excellent</option>
                                    <option value="4">4 - Very Good</option>
                                    <option value="3">3 - Good</option>
                                    <option value="2">2 - Fair</option>
                                    <option value="1">1 - Poor</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label sans small">Title (optional)</label>
                                <input type="text" name="title" class="form-control" maxlength="191">
                            </div>
                            <div class="mb-3">
                                <label class="form-label sans small">Comment</label>
                                <textarea name="comment" class="form-control" rows="3" maxlength="2000"></textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-gold">Submit Review</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($relatedProducts)): ?>
        <div class="mt-5 pt-4 border-top border-secondary">
            <h5 class="mb-4">You May Also Like</h5>
            <div class="row">
                <?php foreach ($relatedProducts as $product): ?>
                    <div class="col-6 col-md-3 mb-4">
                        <?php require dirname(__DIR__, 2) . '/partials/product-card.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<script src="/assets/js/product-gallery.js"></script>
