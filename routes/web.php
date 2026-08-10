<?php

declare(strict_types=1);

use App\Controllers\Customer\AddressController;
use App\Controllers\Customer\AuthController;
use App\Controllers\Customer\BlogController;
use App\Controllers\Customer\CartController;
use App\Controllers\Customer\CheckoutController;
use App\Controllers\Customer\DashboardController;
use App\Controllers\Customer\HomeController;
use App\Controllers\Customer\NewsletterController;
use App\Controllers\Customer\OrderController;
use App\Controllers\Customer\ProductController;
use App\Controllers\Customer\ProfileController;
use App\Controllers\Customer\ReviewController;
use App\Controllers\Customer\ShopController;
use App\Controllers\Customer\SitemapController;
use App\Controllers\Customer\StaticController;
use App\Controllers\Customer\VendorStorefrontController;
use App\Controllers\Customer\WishlistController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\VerifyCsrfMiddleware;

/**
 * Route definitions for the public/customer-facing site. Admin and API
 * route files (routes/admin.php, routes/api.php) will be merged in here
 * as those modules are built.
 */
return function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/health', [HomeController::class, 'health']);

    // Guest-only auth pages (redirect already-logged-in users to /account).
    $router->get('/register', [AuthController::class, 'showRegister'], [GuestMiddleware::class]);
    $router->post('/register', [AuthController::class, 'register'], [GuestMiddleware::class, VerifyCsrfMiddleware::class]);

    $router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
    $router->post('/login', [AuthController::class, 'login'], [GuestMiddleware::class, VerifyCsrfMiddleware::class]);

    $router->get('/forgot-password', [AuthController::class, 'showForgotPassword'], [GuestMiddleware::class]);
    $router->post('/forgot-password', [AuthController::class, 'forgotPassword'], [GuestMiddleware::class, VerifyCsrfMiddleware::class]);

    $router->get('/reset-password/{token}', [AuthController::class, 'showResetPassword'], [GuestMiddleware::class]);
    $router->post('/reset-password', [AuthController::class, 'resetPassword'], [GuestMiddleware::class, VerifyCsrfMiddleware::class]);

    $router->get('/verify-email/{token}', [AuthController::class, 'verifyEmail']);
    $router->post('/resend-verification', [AuthController::class, 'resendVerification'], [VerifyCsrfMiddleware::class]);

    $router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, VerifyCsrfMiddleware::class]);

    // Authenticated customer area.
    $router->get('/account', [DashboardController::class, 'index'], [AuthMiddleware::class]);

    // Storefront: shop, category/brand browsing, search.
    $router->get('/shop', [ShopController::class, 'index']);
    $router->get('/shop/category/{slug}', [ShopController::class, 'category']);
    $router->get('/shop/brand/{slug}', [ShopController::class, 'brand']);
    $router->get('/search', [ShopController::class, 'search']);
    $router->get('/store/{slug}', [VendorStorefrontController::class, 'show']);

    // Product detail + reviews (submitting a review requires being logged in).
    $router->get('/product/{slug}', [ProductController::class, 'show']);
    $router->post('/product/{slug}/reviews', [ProductController::class, 'storeReview'], [AuthMiddleware::class, VerifyCsrfMiddleware::class]);

    // Static/content pages.
    $router->get('/about', [StaticController::class, 'about']);
    $router->get('/contact', [StaticController::class, 'showContact']);
    $router->post('/contact', [StaticController::class, 'submitContact'], [VerifyCsrfMiddleware::class]);
    $router->get('/faqs', [StaticController::class, 'faqs']);
    $router->get('/privacy-policy', [StaticController::class, 'privacy']);
    $router->get('/terms', [StaticController::class, 'terms']);
    $router->get('/shipping-returns', [StaticController::class, 'shippingReturns']);

    // Blog ("Journal"). /blog/category/{slug} and /blog/{slug} are both
    // GET single-segment-under-/blog patterns, so category must be
    // registered first - the router matches in registration order, and
    // /blog/category would otherwise be swallowed by /blog/{slug} with
    // slug="category".
    $router->get('/blog', [BlogController::class, 'index']);
    $router->get('/blog/category/{slug}', [BlogController::class, 'category']);
    $router->get('/blog/{slug}', [BlogController::class, 'show']);
    $router->post('/blog/{slug}/comments', [BlogController::class, 'storeComment'], [AuthMiddleware::class, VerifyCsrfMiddleware::class]);

    $router->get('/sitemap.xml', [SitemapController::class, 'index']);
    $router->get('/robots.txt', [SitemapController::class, 'robots']);

    $router->post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'], [VerifyCsrfMiddleware::class]);

    // Cart - works for both guests (session-identified) and logged-in
    // customers (user-identified, merged from any guest cart on login).
    $router->get('/cart', [CartController::class, 'index']);
    $router->post('/cart/add', [CartController::class, 'add'], [VerifyCsrfMiddleware::class]);
    $router->post('/cart/update/{id}', [CartController::class, 'update'], [VerifyCsrfMiddleware::class]);
    $router->post('/cart/remove/{id}', [CartController::class, 'remove'], [VerifyCsrfMiddleware::class]);
    $router->post('/cart/coupon', [CartController::class, 'applyCoupon'], [VerifyCsrfMiddleware::class]);
    $router->post('/cart/coupon/remove', [CartController::class, 'removeCoupon'], [VerifyCsrfMiddleware::class]);
    $router->post('/cart/shipping', [CartController::class, 'setShipping'], [VerifyCsrfMiddleware::class]);

    // Wishlist requires an account (no guest wishlist in the schema).
    $router->get('/wishlist', [WishlistController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/wishlist/toggle', [WishlistController::class, 'toggle'], [AuthMiddleware::class, VerifyCsrfMiddleware::class]);

    // Checkout requires an account - the schema's orders.user_id is
    // NOT NULL, so there is no guest checkout to support.
    $router->get('/checkout', [CheckoutController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/checkout', [CheckoutController::class, 'store'], [AuthMiddleware::class, VerifyCsrfMiddleware::class]);

    $router->get('/order/{orderNumber}/confirmation', [OrderController::class, 'confirmation'], [AuthMiddleware::class]);
    $router->get('/order/{orderNumber}/invoice', [OrderController::class, 'invoice'], [AuthMiddleware::class]);

    // Order history / tracking.
    $router->get('/account/orders', [OrderController::class, 'history'], [AuthMiddleware::class]);
    $router->get('/account/orders/{orderNumber}', [OrderController::class, 'show'], [AuthMiddleware::class]);

    // My Reviews.
    $router->get('/account/reviews', [ReviewController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/account/reviews/{id}/edit', [ReviewController::class, 'edit'], [AuthMiddleware::class]);
    $router->post('/account/reviews/{id}', [ReviewController::class, 'update'], [AuthMiddleware::class, VerifyCsrfMiddleware::class]);
    $router->post('/account/reviews/{id}/delete', [ReviewController::class, 'destroy'], [AuthMiddleware::class, VerifyCsrfMiddleware::class]);

    // Profile + password + address book.
    $router->get('/account/profile', [ProfileController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/account/profile', [ProfileController::class, 'updateProfile'], [AuthMiddleware::class, VerifyCsrfMiddleware::class]);
    $router->post('/account/password', [ProfileController::class, 'updatePassword'], [AuthMiddleware::class, VerifyCsrfMiddleware::class]);

    $router->post('/account/addresses', [AddressController::class, 'store'], [AuthMiddleware::class, VerifyCsrfMiddleware::class]);
    $router->post('/account/addresses/{id}', [AddressController::class, 'update'], [AuthMiddleware::class, VerifyCsrfMiddleware::class]);
    $router->post('/account/addresses/{id}/delete', [AddressController::class, 'destroy'], [AuthMiddleware::class, VerifyCsrfMiddleware::class]);
};
