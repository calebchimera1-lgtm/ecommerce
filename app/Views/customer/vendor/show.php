<?php
/** @var array $vendor @var array $products @var int $total @var string $sort @var int $page @var int $perPage */
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
                <h1 class="section-title mb-1"><?= e($vendor['store_name']) ?></h1>
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
    </div>
</section>
