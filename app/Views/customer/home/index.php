<?php
/**
 * @var array $featuredCategories @var array $newArrivals @var array $trending
 * @var array $onSale @var array $featuredProducts @var array $testimonials
 */
$categoryIcons = [
    'fashion' => 'fa-shirt',
    'shoes' => 'fa-shoe-prints',
    'watches' => 'fa-clock',
    'perfumes' => 'fa-spray-can-sparkles',
    'jewelry' => 'fa-gem',
    'bags' => 'fa-bag-shopping',
    'accessories' => 'fa-glasses',
];

$renderGrid = static function (array $products): void {
    foreach ($products as $product) {
        echo '<div class="col-6 col-md-4 col-lg-3 mb-4">';
        require dirname(__DIR__, 2) . '/partials/product-card.php';
        echo '</div>';
    }
};
?>
<section class="hero">
    <div class="hero-content">
        <div class="hero-eyebrow">Kymera Collection</div>
        <h1 class="hero-title">Luxury, Curated<br>For the Discerning Few</h1>
        <p class="hero-subtitle">Fashion, watches, jewelry, perfumes, bags, and accessories - each piece chosen for its craftsmanship and character.</p>
        <a href="/shop" class="btn btn-gold btn-lg px-4 py-2">Explore the Collection</a>
    </div>
</section>

<?php if (!empty($featuredCategories)): ?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <div class="section-eyebrow">Collections</div>
            <h2 class="section-title">Shop by Category</h2>
        </div>
        <div class="row g-3">
            <?php foreach ($featuredCategories as $category): ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="/shop/category/<?= e($category['slug']) ?>" class="collection-card">
                        <i class="fa-solid <?= e($categoryIcons[$category['slug']] ?? 'fa-tags') ?>"></i>
                        <span class="collection-card-name"><?= e($category['name']) ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($newArrivals)): ?>
<section class="section section-alt">
    <div class="container">
        <div class="section-heading">
            <div class="section-eyebrow">Just In</div>
            <h2 class="section-title">New Arrivals</h2>
        </div>
        <div class="row">
            <?php $renderGrid($newArrivals); ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($onSale)): ?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <div class="section-eyebrow">Limited Time</div>
            <h2 class="section-title">Flash Sale</h2>
        </div>
        <div class="row">
            <?php $renderGrid($onSale); ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($featuredProducts)): ?>
<section class="section section-alt">
    <div class="container">
        <div class="section-heading">
            <div class="section-eyebrow">Editor's Pick</div>
            <h2 class="section-title">Featured Products</h2>
        </div>
        <div class="row">
            <?php $renderGrid($featuredProducts); ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($trending)): ?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <div class="section-eyebrow">Most Viewed</div>
            <h2 class="section-title">Trending Now</h2>
        </div>
        <div class="row">
            <?php $renderGrid($trending); ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($testimonials)): ?>
<section class="section section-alt">
    <div class="container">
        <div class="section-heading">
            <div class="section-eyebrow">Testimonials</div>
            <h2 class="section-title">What Our Clients Say</h2>
        </div>
        <div class="row g-4">
            <?php foreach ($testimonials as $testimonial): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="testimonial-card">
                        <div class="testimonial-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa-<?= $i <= (int) $testimonial['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-white-50 small">&ldquo;<?= e($testimonial['message']) ?>&rdquo;</p>
                        <div class="testimonial-name">&mdash; <?= e($testimonial['customer_name']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
