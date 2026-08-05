<?php

declare(strict_types=1);

use App\Controllers\Vendor\AuthController;
use App\Controllers\Vendor\DashboardController;
use App\Controllers\Vendor\OrderController;
use App\Controllers\Vendor\PayoutController;
use App\Controllers\Vendor\ProductController;
use App\Controllers\Vendor\ProfileController;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\GuestVendorMiddleware;
use App\Middleware\VendorMiddleware;
use App\Middleware\VerifyCsrfMiddleware;

/**
 * Vendor portal routes - the marketplace seller-facing counterpart to
 * routes/admin.php, merged into the same Router by App::run(). The
 * /vendor prefix is what separates this area from both the storefront
 * and the staff admin panel.
 */
return function (Router $router): void {
    $router->get('/vendor', static function (): void {
        Response::redirect('/vendor/dashboard');
    }, [VendorMiddleware::class]);

    $router->get('/vendor/register', [AuthController::class, 'showRegister'], [GuestVendorMiddleware::class]);
    $router->post('/vendor/register', [AuthController::class, 'register'], [GuestVendorMiddleware::class, VerifyCsrfMiddleware::class]);
    $router->get('/vendor/login', [AuthController::class, 'showLogin'], [GuestVendorMiddleware::class]);
    $router->post('/vendor/login', [AuthController::class, 'login'], [GuestVendorMiddleware::class, VerifyCsrfMiddleware::class]);
    $router->post('/vendor/logout', [AuthController::class, 'logout'], [VendorMiddleware::class, VerifyCsrfMiddleware::class]);

    $router->get('/vendor/dashboard', [DashboardController::class, 'index'], [VendorMiddleware::class]);

    $router->get('/vendor/profile', [ProfileController::class, 'index'], [VendorMiddleware::class]);
    $router->post('/vendor/profile', [ProfileController::class, 'update'], [VendorMiddleware::class, VerifyCsrfMiddleware::class]);

    $router->get('/vendor/products', [ProductController::class, 'index'], [VendorMiddleware::class]);
    $router->get('/vendor/products/create', [ProductController::class, 'create'], [VendorMiddleware::class]);
    $router->post('/vendor/products', [ProductController::class, 'store'], [VendorMiddleware::class, VerifyCsrfMiddleware::class]);
    $router->get('/vendor/products/{id}/edit', [ProductController::class, 'edit'], [VendorMiddleware::class]);
    $router->post('/vendor/products/{id}', [ProductController::class, 'update'], [VendorMiddleware::class, VerifyCsrfMiddleware::class]);
    $router->post('/vendor/products/{id}/restock', [ProductController::class, 'restock'], [VendorMiddleware::class, VerifyCsrfMiddleware::class]);
    $router->post('/vendor/products/{id}/delete', [ProductController::class, 'destroy'], [VendorMiddleware::class, VerifyCsrfMiddleware::class]);
    $router->post('/vendor/products/{id}/images/{imageId}/delete', [ProductController::class, 'deleteImage'], [VendorMiddleware::class, VerifyCsrfMiddleware::class]);
    $router->post('/vendor/products/{id}/images/{imageId}/primary', [ProductController::class, 'setPrimaryImage'], [VendorMiddleware::class, VerifyCsrfMiddleware::class]);

    $router->get('/vendor/orders', [OrderController::class, 'index'], [VendorMiddleware::class]);
    $router->get('/vendor/orders/{id}', [OrderController::class, 'show'], [VendorMiddleware::class]);
    $router->post('/vendor/orders/{id}/status', [OrderController::class, 'updateStatus'], [VendorMiddleware::class, VerifyCsrfMiddleware::class]);

    $router->get('/vendor/payouts', [PayoutController::class, 'index'], [VendorMiddleware::class]);
};
