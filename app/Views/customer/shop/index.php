<?php
/**
 * @var array $products @var array $categories @var array $brands @var array $filters
 * @var string $sort @var int $page @var int $perPage @var int $total @var string $heading
 * @var array|null $activeCategory @var array|null $activeBrand
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
        <div class="section-heading">
            <div class="section-eyebrow">Kymera Collection</div>
            <h1 class="section-title"><?= e($heading) ?></h1>
            <?php if ($activeCategory !== null && !empty($activeCategory['description'])): ?>
                <p class="text-white-50 mt-2"><?= e($activeCategory['description']) ?></p>
            <?php endif; ?>
        </div>

        <div class="row">
            <aside class="col-lg-3 shop-sidebar mb-4">
                <form method="GET" action="/shop">
                    <h6>Categories</h6>
                    <ul class="list-unstyled">
                        <li><a href="/shop" class="<?= $activeCategory === null && $filters['category_id'] === '' ? 'active' : '' ?>">All Categories</a></li>
                        <?php foreach ($categories as $category): ?>
                            <li>
                                <a href="/shop/category/<?= e($category['slug']) ?>"
                                   class="<?= ($activeCategory['id'] ?? null) === $category['id'] ? 'active' : '' ?>">
                                    <?= e($category['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <h6>Brands</h6>
                    <ul class="list-unstyled">
                        <li><a href="/shop" class="<?= $activeBrand === null ? 'active' : '' ?>">All Brands</a></li>
                        <?php foreach ($brands as $brand): ?>
                            <li>
                                <a href="/shop/brand/<?= e($brand['slug']) ?>"
                                   class="<?= ($activeBrand['id'] ?? null) === $brand['id'] ? 'active' : '' ?>">
                                    <?= e($brand['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <h6>Price Range</h6>
                    <div class="d-flex gap-2 align-items-center mb-3">
                        <input type="number" min="0" step="1" name="min_price" class="form-control form-control-sm" placeholder="Min" value="<?= e($filters['min_price']) ?>">
                        <span class="text-white-50">&ndash;</span>
                        <input type="number" min="0" step="1" name="max_price" class="form-control form-control-sm" placeholder="Max" value="<?= e($filters['max_price']) ?>">
                    </div>
                    <?php if ($filters['category_id'] !== ''): ?><input type="hidden" name="category_id" value="<?= e($filters['category_id']) ?>"><?php endif; ?>
                    <?php if ($filters['brand_id'] !== ''): ?><input type="hidden" name="brand_id" value="<?= e($filters['brand_id']) ?>"><?php endif; ?>
                    <?php if ($filters['search'] !== ''): ?><input type="hidden" name="q" value="<?= e($filters['search']) ?>"><?php endif; ?>
                    <button type="submit" class="btn btn-outline-gold btn-sm w-100">Apply</button>
                </form>
            </aside>

            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-4 sans">
                    <span class="text-white-50 small"><?= $total ?> product<?= $total === 1 ? '' : 's' ?></span>
                    <form method="GET" action="" class="d-flex align-items-center gap-2">
                        <?php foreach (['category_id', 'brand_id', 'q', 'min_price', 'max_price'] as $preserve): ?>
                            <?php if (($filters[$preserve === 'q' ? 'search' : $preserve] ?? '') !== ''): ?>
                                <input type="hidden" name="<?= e($preserve) ?>" value="<?= e($filters[$preserve === 'q' ? 'search' : $preserve]) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <label for="sort" class="text-white-50 small mb-0">Sort:</label>
                        <select id="sort" name="sort" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                            <?php foreach ($sortOptions as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <?php if (empty($products)): ?>
                    <p class="text-white-50">No products match your search. Try adjusting your filters.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($products as $product): ?>
                            <div class="col-6 col-md-4 mb-4">
                                <?php require dirname(__DIR__, 2) . '/partials/product-card.php'; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
            </div>
        </div>
    </div>
</section>
