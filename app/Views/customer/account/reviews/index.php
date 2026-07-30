<?php /** @var array $reviews */ ?>
<section class="section">
    <div class="container">
        <?php require dirname(__DIR__, 3) . '/partials/account-nav.php'; ?>

        <h4 class="mb-4">My Reviews</h4>

        <?php if (empty($reviews)): ?>
            <p class="text-white-50">You haven't written any reviews yet.</p>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <a href="/product/<?= e($review['product_slug']) ?>" class="text-white d-block mb-1"><?= e($review['product_name']) ?></a>
                            <div class="sans mb-1">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-<?= $i <= (int) $review['rating'] ? 'solid' : 'regular' ?> fa-star text-warning small"></i>
                                <?php endfor; ?>
                                <?php if ((int) $review['is_approved'] === 1): ?>
                                    <span class="badge bg-success ms-2">Published</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary ms-2">Pending approval</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($review['title'])): ?><div class="fw-bold"><?= e($review['title']) ?></div><?php endif; ?>
                            <p class="text-white-50 small mb-0"><?= e($review['comment'] ?? '') ?></p>
                        </div>
                        <div class="text-end sans small">
                            <a href="/account/reviews/<?= (int) $review['id'] ?>/edit">Edit</a>
                            <form method="POST" action="/account/reviews/<?= (int) $review['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this review?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-link btn-sm text-danger p-0 ms-2">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
