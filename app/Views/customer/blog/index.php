<?php
/**
 * @var array $posts @var array $categories @var array $filters
 * @var int $page @var int $perPage @var int $total @var array|null $activeCategory
 */
$activeCategory ??= null;
?>
<section class="section pb-2">
    <div class="container">
        <div class="section-heading">
            <div class="section-eyebrow">Kymera Collection</div>
            <h1 class="section-title"><?= $activeCategory !== null ? e($activeCategory['name']) : 'The Journal' ?></h1>
            <p class="text-white-50 mt-2">Style notes, craftsmanship stories, and news from Kymera Collection.</p>
        </div>

        <div class="row">
            <aside class="col-lg-3 shop-sidebar mb-4">
                <form method="GET" action="/blog">
                    <h6>Categories</h6>
                    <ul class="list-unstyled">
                        <li><a href="/blog" class="<?= $activeCategory === null ? 'active' : '' ?>">All Articles</a></li>
                        <?php foreach ($categories as $category): ?>
                            <li>
                                <a href="/blog/category/<?= e($category['slug']) ?>"
                                   class="<?= ($activeCategory['id'] ?? null) === $category['id'] ? 'active' : '' ?>">
                                    <?= e($category['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <h6 class="mt-3">Search</h6>
                    <input type="search" class="form-control form-control-sm" name="q" placeholder="Search articles..." value="<?= e($filters['search'] ?? '') ?>">
                </form>
            </aside>

            <div class="col-lg-9">
                <?php if (empty($posts)): ?>
                    <p class="text-white-50">No articles found.</p>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($posts as $post): ?>
                            <div class="col-md-6 col-lg-4">
                                <a href="/blog/<?= e($post['slug']) ?>" class="text-decoration-none blog-card d-block h-100">
                                    <?php if (!empty($post['featured_image'])): ?>
                                        <img src="<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>" class="w-100 mb-3" style="aspect-ratio:4/3;object-fit:cover;border-radius:0.4rem;">
                                    <?php endif; ?>
                                    <?php if (!empty($post['category_name'])): ?>
                                        <div class="small text-warning text-uppercase mb-1"><?= e($post['category_name']) ?></div>
                                    <?php endif; ?>
                                    <h5 class="text-white mb-2"><?= e($post['title']) ?></h5>
                                    <p class="text-white-50 small mb-2"><?= e(mb_strimwidth((string) ($post['excerpt'] ?? strip_tags($post['content'])), 0, 120, '...')) ?></p>
                                    <div class="text-white-50 small">
                                        By <?= e(trim($post['first_name'] . ' ' . $post['last_name'])) ?>
                                        &middot; <?= e(date('M j, Y', strtotime($post['published_at'] ?? $post['created_at']))) ?>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
