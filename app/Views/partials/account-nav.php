<?php
$accountCurrentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$accountNavItems = [
    ['label' => 'Dashboard', 'href' => '/account'],
    ['label' => 'Orders', 'href' => '/account/orders'],
    ['label' => 'Returns', 'href' => '/account/returns'],
    ['label' => 'Wishlist', 'href' => '/wishlist'],
    ['label' => 'Reviews', 'href' => '/account/reviews'],
    ['label' => 'Profile', 'href' => '/account/profile'],
];
?>
<nav class="sans small mb-4 pb-3 border-bottom border-secondary">
    <?php foreach ($accountNavItems as $item): ?>
        <a href="<?= e($item['href']) ?>"
           class="me-4 d-inline-block <?= $accountCurrentPath === $item['href'] ? 'text-warning fw-bold' : 'text-white-50' ?>">
            <?= e($item['label']) ?>
        </a>
    <?php endforeach; ?>
    <form method="POST" action="/logout" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-link btn-sm text-white-50 p-0 sans small">Logout</button>
    </form>
</nav>
