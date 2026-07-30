<?php

declare(strict_types=1);

use App\Controllers\Customer\HomeController;
use App\Core\Router;

/**
 * Route definitions for the public/customer-facing site. Admin and API
 * route files (routes/admin.php, routes/api.php) will be merged in here
 * as those modules are built.
 */
return function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/health', [HomeController::class, 'health']);
};
