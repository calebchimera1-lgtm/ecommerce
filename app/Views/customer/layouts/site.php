<?php

use App\Core\Auth;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Currency;

$megaMenuCategories = Category::activeOrdered();
$isLoggedIn = Auth::check();
$cartItemCount = Cart::currentItemCount();
$availableCurrencies = Currency::active();
$selectedCurrency = currentCurrency();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Kymera Collection') ?></title>
    <meta name="description" content="<?= e($metaDescription ?? 'Kymera Collection - a luxury destination for fashion, watches, jewelry, perfumes, bags and accessories.') ?>">
    <meta property="og:title" content="<?= e($pageTitle ?? 'Kymera Collection') ?>">
    <meta property="og:description" content="<?= e($metaDescription ?? 'Luxury fashion, watches, jewelry, perfumes, bags and accessories.') ?>">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/site.css" rel="stylesheet">
</head>
<body>
    <header class="site-header">
        <nav class="navbar navbar-expand-lg navbar-dark py-3">
            <div class="container">
                <a class="navbar-brand" href="/">Kymera Collection</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="siteNav">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-lg-center">
                        <li class="nav-item">
                            <a class="nav-link" href="/">Home</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="/shop" role="button" data-bs-toggle="dropdown">Shop</a>
                            <div class="dropdown-menu mega-menu">
                                <div class="mega-menu-grid">
                                    <?php foreach ($megaMenuCategories as $category): ?>
                                        <a href="/shop/category/<?= e($category['slug']) ?>"><?= e($category['name']) ?></a>
                                    <?php endforeach; ?>
                                </div>
                                <hr class="border-secondary my-3">
                                <a href="/shop" class="fw-bold">View All Products &rarr;</a>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/blog">Journal</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/about">About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/contact">Contact</a>
                        </li>
                    </ul>
                    <form class="d-flex search-form me-3 my-2 my-lg-0" method="GET" action="/search">
                        <input class="form-control form-control-sm" type="search" name="q" placeholder="Search products..." value="<?= e($_GET['q'] ?? '') ?>">
                    </form>
                    <ul class="navbar-nav align-items-lg-center">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <?= e($selectedCurrency['code']) ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <?php foreach ($availableCurrencies as $currencyOption): ?>
                                    <a class="dropdown-item<?= $currencyOption['code'] === $selectedCurrency['code'] ? ' active' : '' ?>"
                                       href="/currency/<?= e($currencyOption['code']) ?>">
                                        <?= e($currencyOption['code']) ?> &mdash; <?= e($currencyOption['name']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </li>
                        <?php if ($isLoggedIn): ?>
                            <li class="nav-item"><a class="nav-link" href="/wishlist"><i class="fa-regular fa-heart"></i> Wishlist</a></li>
                            <li class="nav-item"><a class="nav-link" href="/account"><i class="fa-regular fa-user"></i> Account</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link" href="/login"><i class="fa-regular fa-user"></i> Sign In</a></li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="/cart">
                                <i class="fa-solid fa-bag-shopping"></i> Cart
                                <?php if ($cartItemCount > 0): ?>
                                    <span class="badge rounded-pill bg-warning text-dark ms-1"><?= $cartItemCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <div class="container mt-4">
            <?php require dirname(__DIR__, 2) . '/partials/alerts.php'; ?>
        </div>
        <?php $content(); ?>
    </main>

    <div class="newsletter-section">
        <div class="container">
            <div class="section-eyebrow">Stay Informed</div>
            <h3 class="mt-2 mb-3">Join the Kymera Circle</h3>
            <p class="text-white-50 mb-4">Be first to know about new arrivals, private sales, and exclusive collections.</p>
            <form method="POST" action="/newsletter/subscribe" class="d-flex justify-content-center gap-2 flex-wrap">
                <?= csrf_field() ?>
                <input type="email" name="email" class="form-control search-form-input" style="max-width:320px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.15);color:#f8f7f4;" placeholder="you@example.com" required>
                <button type="submit" class="btn btn-gold">Subscribe</button>
            </form>
        </div>
    </div>

    <footer class="site-footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="navbar-brand d-inline-block mb-3">Kymera Collection</div>
                    <p class="text-white-50 sans small">Luxury Redefined. Fashion, watches, jewelry, perfumes, bags, and accessories for the discerning few.</p>
                </div>
                <div class="col-6 col-lg-2">
                    <h6>Shop</h6>
                    <?php foreach (array_slice($megaMenuCategories, 0, 5) as $category): ?>
                        <a href="/shop/category/<?= e($category['slug']) ?>"><?= e($category['name']) ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="col-6 col-lg-2">
                    <h6>Company</h6>
                    <a href="/about">About Us</a>
                    <a href="/blog">Journal</a>
                    <a href="/contact">Contact</a>
                    <a href="/faqs">FAQs</a>
                </div>
                <div class="col-6 col-lg-2">
                    <h6>Legal</h6>
                    <a href="/shipping-returns">Shipping &amp; Returns</a>
                    <a href="/privacy-policy">Privacy Policy</a>
                    <a href="/terms">Terms of Service</a>
                </div>
                <div class="col-6 col-lg-2">
                    <h6>Follow</h6>
                    <a href="#"><i class="fa-brands fa-instagram"></i> Instagram</a>
                    <a href="#"><i class="fa-brands fa-facebook"></i> Facebook</a>
                    <a href="#"><i class="fa-brands fa-x-twitter"></i> X</a>
                </div>
            </div>
            <div class="footer-bottom text-center">
                &copy; <?= date('Y') ?> Kymera Collection. All rights reserved.
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
