<?php

declare(strict_types=1);

use App\Controllers\Admin\AuditLogController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\BlogCategoryController;
use App\Controllers\Admin\BlogPostController;
use App\Controllers\Admin\BrandController;
use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\CouponController;
use App\Controllers\Admin\CustomerController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ExpenseController;
use App\Controllers\Admin\InventoryController;
use App\Controllers\Admin\OrderController;
use App\Controllers\Admin\ProductController;
use App\Controllers\Admin\PurchaseOrderController;
use App\Controllers\Admin\ReportController;
use App\Controllers\Admin\ReviewController;
use App\Controllers\Admin\RoleController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\SupplierController;
use App\Controllers\Admin\TestimonialController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\VendorController;
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
    $router->post('/admin/products/{id}/approve', [ProductController::class, 'approve'], [...$productsPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/products/{id}/reject', [ProductController::class, 'reject'], [...$productsPermission, VerifyCsrfMiddleware::class]);

    $couponsPermission = [[PermissionMiddleware::class, 'coupons.manage']];
    $router->get('/admin/coupons', [CouponController::class, 'index'], $couponsPermission);
    $router->get('/admin/coupons/create', [CouponController::class, 'create'], $couponsPermission);
    $router->post('/admin/coupons', [CouponController::class, 'store'], [...$couponsPermission, VerifyCsrfMiddleware::class]);
    $router->get('/admin/coupons/{id}/edit', [CouponController::class, 'edit'], $couponsPermission);
    $router->post('/admin/coupons/{id}', [CouponController::class, 'update'], [...$couponsPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/coupons/{id}/delete', [CouponController::class, 'destroy'], [...$couponsPermission, VerifyCsrfMiddleware::class]);

    $ordersViewPermission = [[PermissionMiddleware::class, 'orders.view']];
    $ordersManagePermission = [[PermissionMiddleware::class, 'orders.manage']];
    $router->get('/admin/orders', [OrderController::class, 'index'], $ordersViewPermission);
    $router->get('/admin/orders/{id}', [OrderController::class, 'show'], $ordersViewPermission);
    $router->post('/admin/orders/{id}/status', [OrderController::class, 'updateStatus'], [...$ordersManagePermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/orders/{id}/shipment', [OrderController::class, 'updateShipment'], [...$ordersManagePermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/orders/{id}/payment', [OrderController::class, 'markPaid'], [...$ordersManagePermission, VerifyCsrfMiddleware::class]);

    $customersPermission = [[PermissionMiddleware::class, 'customers.manage']];
    $router->get('/admin/customers', [CustomerController::class, 'index'], $customersPermission);
    $router->get('/admin/customers/{id}', [CustomerController::class, 'show'], $customersPermission);
    $router->post('/admin/customers/{id}/status', [CustomerController::class, 'updateStatus'], [...$customersPermission, VerifyCsrfMiddleware::class]);

    $usersPermission = [[PermissionMiddleware::class, 'users.manage']];
    $router->get('/admin/users', [UserController::class, 'index'], $usersPermission);
    $router->get('/admin/users/create', [UserController::class, 'create'], $usersPermission);
    $router->post('/admin/users', [UserController::class, 'store'], [...$usersPermission, VerifyCsrfMiddleware::class]);
    $router->get('/admin/users/{id}/edit', [UserController::class, 'edit'], $usersPermission);
    $router->post('/admin/users/{id}', [UserController::class, 'update'], [...$usersPermission, VerifyCsrfMiddleware::class]);

    $rolesPermission = [[PermissionMiddleware::class, 'roles.manage']];
    $router->get('/admin/roles', [RoleController::class, 'index'], $rolesPermission);
    $router->get('/admin/roles/{id}/edit', [RoleController::class, 'edit'], $rolesPermission);
    $router->post('/admin/roles/{id}/permissions', [RoleController::class, 'updatePermissions'], [...$rolesPermission, VerifyCsrfMiddleware::class]);

    $reviewsPermission = [[PermissionMiddleware::class, 'reviews.manage']];
    $router->get('/admin/reviews', [ReviewController::class, 'index'], $reviewsPermission);
    $router->post('/admin/reviews/{id}/approve', [ReviewController::class, 'approve'], [...$reviewsPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/reviews/{id}/delete', [ReviewController::class, 'destroy'], [...$reviewsPermission, VerifyCsrfMiddleware::class]);

    $router->get('/admin/audit-logs', [AuditLogController::class, 'index'], [[PermissionMiddleware::class, 'audit_logs.view']]);

    $suppliersPermission = [[PermissionMiddleware::class, 'suppliers.manage']];
    $router->get('/admin/suppliers', [SupplierController::class, 'index'], $suppliersPermission);
    $router->get('/admin/suppliers/create', [SupplierController::class, 'create'], $suppliersPermission);
    $router->post('/admin/suppliers', [SupplierController::class, 'store'], [...$suppliersPermission, VerifyCsrfMiddleware::class]);
    $router->get('/admin/suppliers/{id}/edit', [SupplierController::class, 'edit'], $suppliersPermission);
    $router->post('/admin/suppliers/{id}', [SupplierController::class, 'update'], [...$suppliersPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/suppliers/{id}/delete', [SupplierController::class, 'destroy'], [...$suppliersPermission, VerifyCsrfMiddleware::class]);

    $inventoryPermission = [[PermissionMiddleware::class, 'inventory.manage']];
    $router->get('/admin/purchase-orders', [PurchaseOrderController::class, 'index'], $inventoryPermission);
    $router->get('/admin/purchase-orders/create', [PurchaseOrderController::class, 'create'], $inventoryPermission);
    $router->post('/admin/purchase-orders', [PurchaseOrderController::class, 'store'], [...$inventoryPermission, VerifyCsrfMiddleware::class]);
    $router->get('/admin/purchase-orders/{id}', [PurchaseOrderController::class, 'show'], $inventoryPermission);
    $router->post('/admin/purchase-orders/{id}/order', [PurchaseOrderController::class, 'markOrdered'], [...$inventoryPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive'], [...$inventoryPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/purchase-orders/{id}/cancel', [PurchaseOrderController::class, 'cancel'], [...$inventoryPermission, VerifyCsrfMiddleware::class]);

    $router->get('/admin/inventory', [InventoryController::class, 'index'], $inventoryPermission);
    $router->get('/admin/inventory/movements', [InventoryController::class, 'movements'], $inventoryPermission);
    $router->post('/admin/inventory/{id}/adjust', [InventoryController::class, 'adjust'], [...$inventoryPermission, VerifyCsrfMiddleware::class]);

    $reportsPermission = [[PermissionMiddleware::class, 'reports.view']];
    $router->get('/admin/reports', [ReportController::class, 'index'], $reportsPermission);
    $router->get('/admin/reports/sales', [ReportController::class, 'sales'], $reportsPermission);
    $router->get('/admin/reports/inventory', [ReportController::class, 'inventory'], $reportsPermission);
    $router->get('/admin/reports/customers', [ReportController::class, 'customers'], $reportsPermission);
    $router->get('/admin/reports/products', [ReportController::class, 'products'], $reportsPermission);

    $expensesPermission = [[PermissionMiddleware::class, 'expenses.manage']];
    $router->get('/admin/expenses', [ExpenseController::class, 'index'], $expensesPermission);
    $router->get('/admin/expenses/create', [ExpenseController::class, 'create'], $expensesPermission);
    $router->post('/admin/expenses', [ExpenseController::class, 'store'], [...$expensesPermission, VerifyCsrfMiddleware::class]);
    $router->get('/admin/expenses/{id}/edit', [ExpenseController::class, 'edit'], $expensesPermission);
    $router->post('/admin/expenses/{id}', [ExpenseController::class, 'update'], [...$expensesPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/expenses/{id}/delete', [ExpenseController::class, 'destroy'], [...$expensesPermission, VerifyCsrfMiddleware::class]);

    $blogPermission = [[PermissionMiddleware::class, 'blog.manage']];
    $router->get('/admin/blog-categories', [BlogCategoryController::class, 'index'], $blogPermission);
    $router->get('/admin/blog-categories/create', [BlogCategoryController::class, 'create'], $blogPermission);
    $router->post('/admin/blog-categories', [BlogCategoryController::class, 'store'], [...$blogPermission, VerifyCsrfMiddleware::class]);
    $router->get('/admin/blog-categories/{id}/edit', [BlogCategoryController::class, 'edit'], $blogPermission);
    $router->post('/admin/blog-categories/{id}', [BlogCategoryController::class, 'update'], [...$blogPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/blog-categories/{id}/delete', [BlogCategoryController::class, 'destroy'], [...$blogPermission, VerifyCsrfMiddleware::class]);

    $router->get('/admin/blog-posts', [BlogPostController::class, 'index'], $blogPermission);
    $router->get('/admin/blog-posts/create', [BlogPostController::class, 'create'], $blogPermission);
    $router->post('/admin/blog-posts', [BlogPostController::class, 'store'], [...$blogPermission, VerifyCsrfMiddleware::class]);
    $router->get('/admin/blog-posts/{id}/edit', [BlogPostController::class, 'edit'], $blogPermission);
    $router->post('/admin/blog-posts/{id}', [BlogPostController::class, 'update'], [...$blogPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/blog-posts/{id}/delete', [BlogPostController::class, 'destroy'], [...$blogPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/blog-posts/{id}/comments/{commentId}/approve', [BlogPostController::class, 'approveComment'], [...$blogPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/blog-posts/{id}/comments/{commentId}/delete', [BlogPostController::class, 'destroyComment'], [...$blogPermission, VerifyCsrfMiddleware::class]);

    $router->get('/admin/testimonials', [TestimonialController::class, 'index'], $blogPermission);
    $router->get('/admin/testimonials/create', [TestimonialController::class, 'create'], $blogPermission);
    $router->post('/admin/testimonials', [TestimonialController::class, 'store'], [...$blogPermission, VerifyCsrfMiddleware::class]);
    $router->get('/admin/testimonials/{id}/edit', [TestimonialController::class, 'edit'], $blogPermission);
    $router->post('/admin/testimonials/{id}', [TestimonialController::class, 'update'], [...$blogPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/testimonials/{id}/delete', [TestimonialController::class, 'destroy'], [...$blogPermission, VerifyCsrfMiddleware::class]);

    $vendorsPermission = [[PermissionMiddleware::class, 'vendors.manage']];
    $router->get('/admin/vendors', [VendorController::class, 'index'], $vendorsPermission);
    $router->get('/admin/vendors/{id}', [VendorController::class, 'show'], $vendorsPermission);
    $router->post('/admin/vendors/{id}/approve', [VendorController::class, 'approve'], [...$vendorsPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/vendors/{id}/reject', [VendorController::class, 'reject'], [...$vendorsPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/vendors/{id}/suspend', [VendorController::class, 'suspend'], [...$vendorsPermission, VerifyCsrfMiddleware::class]);
    $router->post('/admin/vendors/{id}/reactivate', [VendorController::class, 'reactivate'], [...$vendorsPermission, VerifyCsrfMiddleware::class]);
};
