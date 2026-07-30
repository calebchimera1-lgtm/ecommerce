<?php

declare(strict_types=1);

use App\Controllers\Customer\AuthController;
use App\Controllers\Customer\DashboardController;
use App\Controllers\Customer\HomeController;
use App\Controllers\Customer\NewsletterController;
use App\Controllers\Customer\ProductController;
use App\Controllers\Customer\ShopController;
use App\Controllers\Customer\StaticController;
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

    $router->post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'], [VerifyCsrfMiddleware::class]);
};
