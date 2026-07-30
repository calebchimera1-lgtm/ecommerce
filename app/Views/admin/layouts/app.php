<?php

use App\Core\Auth;

$admin = Auth::user();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Only sections that actually exist yet are listed - later modules
// (Products, Orders, Customers, Reports, ...) add their own entries
// here, each gated by the permission that already exists in the
// role_permissions matrix from Module 1.
$navItems = [
    ['label' => 'Dashboard', 'icon' => 'fa-gauge', 'href' => '/admin/dashboard', 'permission' => 'dashboard.view'],
    ['label' => 'Products', 'icon' => 'fa-box', 'href' => '/admin/products', 'permission' => 'products.manage'],
    ['label' => 'Categories', 'icon' => 'fa-sitemap', 'href' => '/admin/categories', 'permission' => 'categories.manage'],
    ['label' => 'Brands', 'icon' => 'fa-tags', 'href' => '/admin/brands', 'permission' => 'brands.manage'],
    ['label' => 'Coupons', 'icon' => 'fa-ticket', 'href' => '/admin/coupons', 'permission' => 'coupons.manage'],
    ['label' => 'Settings', 'icon' => 'fa-gear', 'href' => '/admin/settings', 'permission' => 'settings.manage'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Kymera Collection Admin') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --kymera-black: #0b0b0c;
            --kymera-panel: #141414;
            --kymera-gold: #c9a24b;
            --kymera-white: #f8f7f4;
        }
        body { background: var(--kymera-black); color: var(--kymera-white); font-family: "Georgia", serif; min-height: 100vh; }
        .admin-shell { display: flex; min-height: 100vh; }
        .admin-sidebar {
            width: 240px;
            flex-shrink: 0;
            background: var(--kymera-panel);
            border-right: 1px solid rgba(201, 162, 75, 0.25);
            padding: 1.5rem 1rem;
        }
        .sidebar-brand { letter-spacing: 0.2em; text-transform: uppercase; color: var(--kymera-gold); font-size: 1rem; margin-bottom: 2rem; padding: 0 0.5rem; }
        .sidebar-brand span { color: rgba(248, 247, 244, 0.5); font-size: 0.7rem; display: block; letter-spacing: 0.15em; }
        .admin-nav-link {
            display: block;
            padding: 0.6rem 0.75rem;
            border-radius: 0.5rem;
            color: rgba(248, 247, 244, 0.75);
            text-decoration: none;
            margin-bottom: 0.25rem;
            font-family: Arial, sans-serif;
            font-size: 0.9rem;
        }
        .admin-nav-link i { width: 20px; display: inline-block; color: var(--kymera-gold); }
        .admin-nav-link:hover { background: rgba(201, 162, 75, 0.1); color: var(--kymera-white); }
        .admin-nav-link.active { background: rgba(201, 162, 75, 0.18); color: var(--kymera-white); }
        .admin-main { flex-grow: 1; display: flex; flex-direction: column; }
        .admin-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            font-family: Arial, sans-serif;
        }
        .admin-content { padding: 2rem; flex-grow: 1; }
        .btn-outline-light:hover { color: #0b0b0c; }
    </style>
</head>
<body>
    <div class="admin-shell">
        <nav class="admin-sidebar">
            <div class="sidebar-brand">Kymera<span>Admin</span></div>
            <?php foreach ($navItems as $item): ?>
                <?php if (Auth::can($item['permission'])): ?>
                    <a class="admin-nav-link<?= str_starts_with((string) $currentPath, $item['href']) ? ' active' : '' ?>" href="<?= e($item['href']) ?>">
                        <i class="fa-solid <?= e($item['icon']) ?>"></i> <?= e($item['label']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="admin-main">
            <header class="admin-topbar">
                <div class="small text-white-50">Kymera Collection &mdash; Admin</div>
                <div class="d-flex align-items-center gap-3">
                    <span class="small"><?= e(trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''))) ?></span>
                    <form method="POST" action="/admin/logout">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-light">Logout</button>
                    </form>
                </div>
            </header>
            <main class="admin-content">
                <?php require dirname(__DIR__, 2) . '/partials/alerts.php'; ?>
                <?php $content(); ?>
            </main>
        </div>
    </div>
</body>
</html>
