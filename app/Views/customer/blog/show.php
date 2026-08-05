<?php
/**
 * @var array $post @var array $comments @var array $relatedPosts @var bool $isLoggedIn
 */
?>
<section class="section pb-2">
    <div class="container" style="max-width:820px;">
        <div class="section-heading text-start">
            <?php if (!empty($post['category_name'])): ?>
                <div class="section-eyebrow"><?= e($post['category_name']) ?></div>
            <?php endif; ?>
            <h1 class="section-title"><?= e($post['title']) ?></h1>
            <div class="text-white-50 small mt-2">
                By <?= e(trim($post['first_name'] . ' ' . $post['last_name'])) ?>
                &middot; <?= e(date('F j, Y', strtotime($post['published_at'] ?? $post['created_at']))) ?>
            </div>
        </div>

        <?php if (!empty($post['featured_image'])): ?>
            <img src="<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>" class="w-100 mb-4" style="border-radius:0.5rem;max-height:420px;object-fit:cover;">
        <?php endif; ?>

        <div class="blog-content text-white-50" style="white-space:pre-line;">
            <?= e($post['content']) ?>
        </div>

        <div class="mt-5 pt-4 border-top border-secondary">
            <h5 class="mb-4">Comments (<?= count($comments) ?>)</h5>
            <?php if (empty($comments)): ?>
                <p class="text-white-50">No comments yet. Be the first to share your thoughts.</p>
            <?php endif; ?>
            <?php foreach ($comments as $comment): ?>
                <div class="review-card">
                    <div class="sans small text-white-50"><?= e(trim($comment['first_name'] . ' ' . $comment['last_name'])) ?> &middot; <?= e(date('M j, Y', strtotime($comment['created_at']))) ?></div>
                    <p class="text-white-50 mt-2 mb-0"><?= e($comment['comment']) ?></p>
                </div>
            <?php endforeach; ?>

            <?php if ($isLoggedIn): ?>
                <div class="mt-4">
                    <h6 class="mb-3">Leave a Comment</h6>
                    <form method="POST" action="/blog/<?= e($post['slug']) ?>/comments">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <textarea name="comment" class="form-control" rows="3" maxlength="2000" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-gold">Post Comment</button>
                    </form>
                </div>
            <?php else: ?>
                <p class="text-white-50 mt-3"><a href="/login" class="text-warning">Sign in</a> to leave a comment.</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($relatedPosts)): ?>
            <div class="mt-5 pt-4 border-top border-secondary">
                <h5 class="mb-4">More Articles</h5>
                <div class="row g-4">
                    <?php foreach ($relatedPosts as $related): ?>
                        <div class="col-md-4">
                            <a href="/blog/<?= e($related['slug']) ?>" class="text-decoration-none">
                                <h6 class="text-white mb-1"><?= e($related['title']) ?></h6>
                                <div class="text-white-50 small"><?= e(date('M j, Y', strtotime($related['published_at'] ?? $related['created_at']))) ?></div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
