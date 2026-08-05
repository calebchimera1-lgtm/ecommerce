<?php

declare(strict_types=1);

use App\Controllers\Vendor\AuthController;
use App\Controllers\Vendor\DashboardController;
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

    $router->get('/vendor/login', [AuthController::class, 'showLogin'], [GuestVendorMiddleware::class]);
    $router->post('/vendor/login', [AuthController::class, 'login'], [GuestVendorMiddleware::class, VerifyCsrfMiddleware::class]);
    $router->post('/vendor/logout', [AuthController::class, 'logout'], [VendorMiddleware::class, VerifyCsrfMiddleware::class]);

    $router->get('/vendor/dashboard', [DashboardController::class, 'index'], [VendorMiddleware::class]);
};
