<?php /** @var array $posts @var array $categories @var array $filters @var int $page @var int $perPage @var int $total */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0" style="color:#f8f7f4;">Blog Posts</h4>
    <a href="/admin/blog-posts/create" class="btn btn-gold btn-sm">+ New Post</a>
</div>

<form method="GET" action="/admin/blog-posts" class="row g-2 mb-4">
    <div class="col-md-3">
        <input type="text" class="form-control" name="search" placeholder="Search by title" value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-md-3">
        <select class="form-select" name="category_id">
            <option value="">All categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>" <?= $filters['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-select" name="status">
            <option value="">All statuses</option>
            <option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>Published</option>
            <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-light w-100">Filter</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>Title</th>
                <th>Category</th>
                <th>Author</th>
                <th>Status</th>
                <th>Created</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($posts)): ?>
                <tr><td colspan="6" class="text-white-50">No blog posts yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($posts as $post): ?>
                <tr>
                    <td><?= e($post['title']) ?></td>
                    <td class="text-white-50"><?= e($post['category_name'] ?? '&mdash;') ?></td>
                    <td class="text-white-50"><?= e(trim($post['first_name'] . ' ' . $post['last_name'])) ?></td>
                    <td>
                        <?php if ((int) $post['is_published'] === 1): ?>
                            <span class="badge bg-success">Published</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-white-50"><?= e(date('M j, Y', strtotime($post['created_at']))) ?></td>
                    <td class="text-end">
                        <a href="/admin/blog-posts/<?= (int) $post['id'] ?>/edit" class="btn btn-sm btn-outline-light">Edit</a>
                        <form method="POST" action="/admin/blog-posts/<?= (int) $post['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this post?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
