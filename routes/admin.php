<?php

declare(strict_types=1);

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
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
};
