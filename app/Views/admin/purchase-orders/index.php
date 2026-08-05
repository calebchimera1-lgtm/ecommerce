<?php /** @var array $purchaseOrders @var array $suppliers @var array $statuses @var array $filters @var int $page @var int $perPage @var int $total */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0" style="color:#f8f7f4;">Purchase Orders</h4>
    <a href="/admin/purchase-orders/create" class="btn btn-gold btn-sm">+ New Purchase Order</a>
</div>

<form method="GET" action="/admin/purchase-orders" class="row g-2 mb-4">
    <div class="col-md-3">
        <select class="form-select" name="status">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $status))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-select" name="supplier_id">
            <option value="">All suppliers</option>
            <?php foreach ($suppliers as $supplier): ?>
                <option value="<?= (int) $supplier['id'] ?>" <?= $filters['supplier_id'] === (string) $supplier['id'] ? 'selected' : '' ?>><?= e($supplier['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-light w-100">Filter</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>PO #</th>
                <th>Supplier</th>
                <th>Status</th>
                <th class="text-end">Total</th>
                <th>Expected</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($purchaseOrders)): ?>
                <tr><td colspan="6" class="text-white-50">No purchase orders yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($purchaseOrders as $po): ?>
                <tr>
                    <td><a href="/admin/purchase-orders/<?= (int) $po['id'] ?>" class="text-white">#<?= (int) $po['id'] ?></a></td>
                    <td><?= e($po['supplier_name']) ?></td>
                    <td>
                        <?php
                        $badge = match ($po['status']) {
                            'received' => 'bg-success',
                            'partially_received' => 'bg-warning text-dark',
                            'cancelled' => 'bg-danger',
                            'ordered' => 'bg-info text-dark',
                            default => 'bg-secondary',
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= e(ucfirst(str_replace('_', ' ', $po['status']))) ?></span>
                    </td>
                    <td class="text-end"><?= money($po['total_amount']) ?></td>
                    <td class="text-white-50"><?= $po['expected_at'] !== null ? e(date('M j, Y', strtotime($po['expected_at']))) : '&mdash;' ?></td>
                    <td class="text-white-50"><?= e(date('M j, Y', strtotime($po['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
