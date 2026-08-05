<?php /** @var array $movements @var array $filters @var int $page @var int $perPage @var int $total */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0" style="color:#f8f7f4;">Inventory Movements</h4>
    <a href="/admin/inventory" class="btn btn-outline-light btn-sm">&larr; Back to Inventory</a>
</div>

<div class="table-responsive">
    <table class="table table-dark table-sm align-middle">
        <thead>
            <tr>
                <th>When</th>
                <th>Product</th>
                <th>Type</th>
                <th class="text-end">Quantity</th>
                <th>Reference</th>
                <th>Note</th>
                <th>By</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($movements)): ?>
                <tr><td colspan="7" class="text-white-50">No inventory movements yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($movements as $movement): ?>
                <tr>
                    <td class="text-white-50 small"><?= e(date('M j, Y g:ia', strtotime($movement['created_at']))) ?></td>
                    <td><?= e($movement['product_name']) ?> <span class="text-white-50 small">(<?= e($movement['sku']) ?>)</span></td>
                    <td><span class="badge bg-secondary"><?= e(ucfirst($movement['type'])) ?></span></td>
                    <td class="text-end <?= (int) $movement['quantity'] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= (int) $movement['quantity'] >= 0 ? '+' : '' ?><?= (int) $movement['quantity'] ?>
                    </td>
                    <td class="text-white-50 small">
                        <?= $movement['reference_type'] !== null ? e($movement['reference_type']) . ($movement['reference_id'] !== null ? ' #' . (int) $movement['reference_id'] : '') : '&mdash;' ?>
                    </td>
                    <td class="text-white-50 small"><?= e($movement['note'] ?? '') ?></td>
                    <td class="small"><?= $movement['created_by'] !== null ? e(trim($movement['first_name'] . ' ' . $movement['last_name'])) : 'System' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
