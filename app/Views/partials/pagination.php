<?php
/** @var int $page @var int $perPage @var int $total */
$totalPages = (int) max(1, ceil($total / $perPage));

if ($totalPages > 1):
    $parts = parse_url($_SERVER['REQUEST_URI'] ?? '/');
    parse_str($parts['query'] ?? '', $queryParams);

    $buildUrl = static function (int $targetPage) use ($parts, $queryParams): string {
        $queryParams['page'] = $targetPage;
        return ($parts['path'] ?? '/') . '?' . http_build_query($queryParams);
    };
?>
<nav class="mt-3">
    <ul class="pagination pagination-sm">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link bg-dark text-white border-secondary" href="<?= e($buildUrl(max(1, $page - 1))) ?>">Prev</a>
        </li>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link <?= $i === $page ? '' : 'bg-dark text-white border-secondary' ?>" href="<?= e($buildUrl($i)) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link bg-dark text-white border-secondary" href="<?= e($buildUrl(min($totalPages, $page + 1))) ?>">Next</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
