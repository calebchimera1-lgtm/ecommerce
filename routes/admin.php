<?php

declare(strict_types=1);

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\BrandController;
use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ProductController;
use App\Controllers\Admin\SettingsController;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AdminMiddleware;
use App\Middleware\GuestAdminMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\VerifyCsrfMiddleware;

/**
 * Admin panel routes. Merged into the same Router as routes/web.php
 * by App::run() - the /admin prefix is what separates the two areas.
 */
return function (Router $router): void {
    $router->get('/admin', static function (): void {
        Response::redirect('/admin/dashboard');
    }, [AdminMiddleware::class]);

    $router->get('/admin/login', [AuthController::class, 'showLogin'], [GuestAdminMiddleware::class]);
    $router->post('/admin/login', [AuthController::class, 'login'], [GuestAdminMiddleware::class, VerifyCsrfMiddleware::class]);
    $router->post('/admin/logout', [AuthController::class, 'logout'], [AdminMiddleware::class, VerifyCsrfMiddleware::class]);

    $router->get('/admin/dashboard', [DashboardController::class, 'index'], [AdminMiddleware::class]);

    // Demonstrates fine-grained RBAC: gated on 'settings.manage', not
    // just "is staff" - only Super Admin has this permission per the
    // Module 1 seed data, so Manager/Support get a 403 here.
    $router->get('/admin/settings', [SettingsController::class, 'index'], [[PermissionMiddleware::class, 'settings.manage']]);

    $categoriesPermission = [[PermissionMiddleware::class, 'categories.manage']];
    $router->get('/admin/categories', [CategoryController::class, 'index'], $categoriesPermission);
    $router->get('/admin/categories/create', [CategoryController::class, 'create'], $categoriesPermission);
    $router->post('/admin/categories', [CategoryController::class, 'store'], [...$categoriesPermission, VerifyCsrfMiddleware::class]);
    $router->get('/admin/categories/{id}/edit', [CategoryController::class, 'edit'], $categoriesPermission);
    $router->post('/admin/categories/{id}', [CategoryController::class, 'update'], [...$categoriesPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/categories/{id}/delete', [CategoryController::class, 'destroy'], [...$categoriesPermission, VerifyCsrfMiddleware::class]);

    $brandsPermission = [[PermissionMiddleware::class, 'brands.manage']];
    $router->get('/admin/brands', [BrandController::class, 'index'], $brandsPermission);
    $router->get('/admin/brands/create', [BrandController::class, 'create'], $brandsPermission);
    $router->post('/admin/brands', [BrandController::class, 'store'], [...$brandsPermission, VerifyCsrfMiddleware::class]);
    $router->get('/admin/brands/{id}/edit', [BrandController::class, 'edit'], $brandsPermission);
    $router->post('/admin/brands/{id}', [BrandController::class, 'update'], [...$brandsPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/brands/{id}/delete', [BrandController::class, 'destroy'], [...$brandsPermission, VerifyCsrfMiddleware::class]);

    $productsPermission = [[PermissionMiddleware::class, 'products.manage']];
    $router->get('/admin/products', [ProductController::class, 'index'], $productsPermission);
    $router->get('/admin/products/create', [ProductController::class, 'create'], $productsPermission);
    $router->post('/admin/products', [ProductController::class, 'store'], [...$productsPermission, VerifyCsrfMiddleware::class]);
    $router->get('/admin/products/{id}/edit', [ProductController::class, 'edit'], $productsPermission);
    $router->post('/admin/products/{id}', [ProductController::class, 'update'], [...$productsPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/products/{id}/delete', [ProductController::class, 'destroy'], [...$productsPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/products/{id}/images/{imageId}/delete', [ProductController::class, 'deleteImage'], [...$productsPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/products/{id}/images/{imageId}/primary', [ProductController::class, 'setPrimaryImage'], [...$productsPermission, VerifyCsrfMiddleware::class]);
};
