<?php /** @var array $review */ ?>
<section class="section">
    <div class="container" style="max-width:640px;">
        <?php require dirname(__DIR__, 3) . '/partials/account-nav.php'; ?>

        <h4 class="mb-4">Edit Store Review</h4>
        <form method="POST" action="/account/reviews/vendor/<?= (int) $review['id'] ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label sans small">Rating</label>
                <select name="rating" class="form-select" style="max-width:160px;" required>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= (int) $review['rating'] === $i ? 'selected' : '' ?>><?= $i ?> star<?= $i === 1 ? '' : 's' ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label sans small">Title</label>
                <input type="text" class="form-control" name="title" maxlength="191" value="<?= e($review['title'] ?? '') ?>">
            </div>
            <div class="mb-4">
                <label class="form-label sans small">Comment</label>
                <textarea class="form-control" name="comment" rows="4" maxlength="2000"><?= e($review['comment'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-gold">Save Changes</button>
            <a href="/account/reviews" class="btn btn-outline-gold">Cancel</a>
        </form>
        <p class="sans small text-white-50 mt-3">Editing will resubmit your review for approval.</p>
    </div>
</section>
