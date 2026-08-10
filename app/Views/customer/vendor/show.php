<?php
/**
 * @var array $vendor @var array $products @var int $total @var string $sort @var int $page @var int $perPage
 * @var array $reviews @var array{count:int,average:float} $ratingSummary @var ?string $tier @var bool $canReview @var bool $isLoggedIn
 */
$sortOptions = [
    'newest' => 'Newest',
    'price_asc' => 'Price: Low to High',
    'price_desc' => 'Price: High to Low',
    'name_asc' => 'Name: A-Z',
];
?>
<section class="section pb-2">
    <div class="container">
        <div class="d-flex align-items-center gap-4 mb-4 flex-wrap">
            <?php if (!empty($vendor['logo'])): ?>
                <img src="<?= e($vendor['logo']) ?>" alt="<?= e($vendor['store_name']) ?>" class="rounded"
                     style="width:96px;height:96px;object-fit:cover;">
            <?php else: ?>
                <div class="d-flex align-items-center justify-content-center rounded"
                     style="width:96px;height:96px;background:rgba(201,162,75,0.08);border:1px solid rgba(201,162,75,0.2);">
                    <i class="fa-solid fa-store text-warning fs-3"></i>
                </div>
            <?php endif; ?>
            <div>
                <div class="section-eyebrow">Kymera Collection Marketplace Seller</div>
                <h1 class="section-title mb-1">
                    <?= e($vendor['store_name']) ?>
                    <?php if ($tier !== null): ?>
                        <span class="badge bg-warning text-dark ms-2 align-middle" style="font-size:0.55em;vertical-align:middle;">
                            <i class="fa-solid fa-award"></i> <?= e($tier) ?>
                        </span>
                    <?php endif; ?>
                </h1>
                <div class="sans small mb-1">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fa-<?= $i <= round($ratingSummary['average']) ? 'solid' : 'regular' ?> fa-star text-warning small"></i>
                    <?php endfor; ?>
                    <span class="text-white-50">
                        <?= $ratingSummary['average'] ?> (<?= $ratingSummary['count'] ?> review<?= $ratingSummary['count'] === 1 ? '' : 's' ?>)
                    </span>
                </div>
                <p class="text-white-50 small mb-0">
                    <?= $total ?> product<?= $total === 1 ? '' : 's' ?> &middot;
                    Selling since <?= e(date('F Y', strtotime($vendor['approved_at'] ?? $vendor['created_at']))) ?>
                </p>
            </div>
        </div>

        <?php if (!empty($vendor['description'])): ?>
            <p class="text-white-50 mb-4" style="max-width:720px;white-space:pre-line;"><?= e($vendor['description']) ?></p>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 sans">
            <span class="text-white-50 small"><?= $total ?> product<?= $total === 1 ? '' : 's' ?></span>
            <form method="GET" action="/store/<?= e($vendor['slug']) ?>" class="d-flex align-items-center gap-2">
                <label for="sort" class="text-white-50 small mb-0">Sort:</label>
                <select id="sort" name="sort" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    <?php foreach ($sortOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (empty($products)): ?>
            <p class="text-white-50">This seller doesn't have any products live yet. Check back soon.</p>
        <?php else: ?>
            <div class="row">
                <?php foreach ($products as $product): ?>
                    <div class="col-6 col-md-4 col-lg-3 mb-4">
                        <?php require dirname(__DIR__, 2) . '/partials/product-card.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>

        <div class="row mt-5 pt-4 border-top border-secondary">
            <div class="col-lg-8">
                <h5 class="mb-4">Store Reviews</h5>
                <?php if (empty($reviews)): ?>
                    <p class="text-white-50">No reviews yet.</p>
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
                        <h6 class="mb-3">Rate This Store</h6>
                        <form method="POST" action="/store/<?= e($vendor['slug']) ?>/reviews">
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
                <?php elseif ($isLoggedIn): ?>
                    <p class="text-white-50 small mt-4">You can review this store once an order from them has been delivered to you.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
