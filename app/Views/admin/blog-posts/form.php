<?php
/** @var array|null $post @var array $comments @var array $categories */
$isEdit = $post !== null;
$action = $isEdit ? '/admin/blog-posts/' . (int) $post['id'] : '/admin/blog-posts';

$field = static function (string $key, mixed $default = '') use ($isEdit, $post): mixed {
    return $isEdit ? ($post[$key] ?? $default) : old($key, $default);
};
?>
<h4 class="mb-4" style="color:#f8f7f4;"><?= $isEdit ? 'Edit Blog Post' : 'New Blog Post' ?></h4>

<form method="POST" action="<?= e($action) ?>" enctype="multipart/form-data" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-md-8">
        <label class="form-label" for="title">Title</label>
        <input type="text" class="form-control" id="title" name="title" required value="<?= e((string) $field('title')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="category_id">Category</label>
        <select class="form-select" id="category_id" name="category_id">
            <option value="">No category</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>" <?= (int) $field('category_id', 0) === (int) $category['id'] ? 'selected' : '' ?>>
                    <?= e($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label" for="excerpt">Excerpt (optional)</label>
        <input type="text" class="form-control" id="excerpt" name="excerpt" maxlength="500" value="<?= e((string) $field('excerpt')) ?>">
    </div>
    <div class="col-12">
        <label class="form-label" for="content">Content</label>
        <textarea class="form-control" id="content" name="content" rows="12" required><?= e((string) $field('content')) ?></textarea>
    </div>

    <?php if ($isEdit && !empty($post['featured_image'])): ?>
        <div class="col-12">
            <img src="<?= e($post['featured_image']) ?>" alt="" class="rounded" style="max-height:160px;">
        </div>
    <?php endif; ?>
    <div class="col-12">
        <label class="form-label" for="featured_image">Featured image (JPG, PNG, or WEBP)</label>
        <input type="file" class="form-control" id="featured_image" name="featured_image" accept="image/jpeg,image/png,image/webp">
    </div>

    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="is_published" name="is_published" value="1"
                <?= (int) $field('is_published', 0) === 1 ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_published">Published</label>
        </div>
    </div>

    <div class="col-12">
        <h6 class="text-white-50 small text-uppercase mt-2">SEO (optional)</h6>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="meta_title">Meta title</label>
        <input type="text" class="form-control" id="meta_title" name="meta_title" maxlength="191"
               placeholder="Defaults to the post title"
               value="<?= e((string) $field('meta_title')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="meta_description">Meta description</label>
        <input type="text" class="form-control" id="meta_description" name="meta_description" maxlength="255"
               placeholder="Defaults to the excerpt"
               value="<?= e((string) $field('meta_description')) ?>">
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-gold"><?= $isEdit ? 'Save Changes' : 'Create Post' ?></button>
        <a href="/admin/blog-posts" class="btn btn-outline-light">Cancel</a>
    </div>
</form>

<?php if ($isEdit): ?>
    <div class="mt-5 pt-4 border-top border-secondary">
        <h6 class="mb-3" style="color:#f8f7f4;">Comments (<?= count($comments) ?>)</h6>
        <?php if (empty($comments)): ?>
            <p class="text-white-50 small">No comments yet.</p>
        <?php endif; ?>
        <?php foreach ($comments as $comment): ?>
            <div class="d-flex justify-content-between align-items-start border-bottom border-secondary py-2">
                <div>
                    <div class="small text-white-50">
                        <?= e(trim($comment['first_name'] . ' ' . $comment['last_name'])) ?> &middot;
                        <?= e(date('M j, Y g:ia', strtotime($comment['created_at']))) ?>
                        <?php if ((int) $comment['is_approved'] === 1): ?>
                            <span class="badge bg-success ms-1">Approved</span>
                        <?php else: ?>
                            <span class="badge bg-secondary ms-1">Pending</span>
                        <?php endif; ?>
                    </div>
                    <div class="small"><?= e($comment['comment']) ?></div>
                </div>
                <div class="text-nowrap ms-3">
                    <?php if ((int) $comment['is_approved'] !== 1): ?>
                        <form method="POST" action="/admin/blog-posts/<?= (int) $post['id'] ?>/comments/<?= (int) $comment['id'] ?>/approve" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-success">Approve</button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" action="/admin/blog-posts/<?= (int) $post['id'] ?>/comments/<?= (int) $comment['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Remove this comment?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
