<?php /** @var array $vendors @var array $filters @var array $statuses @var int $page @var int $perPage @var int $total */ ?>
<h4 class="mb-4" style="color:#f8f7f4;">Vendors</h4>

<form method="GET" action="/admin/vendors" class="row g-2 mb-4">
    <div class="col-md-4">
        <input type="text" class="form-control" name="search" placeholder="Search by store name or email"
               value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-md-3">
        <select class="form-select" name="status">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
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
                <th>Store</th>
                <th>Owner</th>
                <th>Email</th>
                <th>Status</th>
                <th>Applied</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($vendors)): ?>
                <tr><td colspan="6" class="text-white-50">No vendors found.</td></tr>
            <?php endif; ?>
            <?php foreach ($vendors as $vendor): ?>
                <tr>
                    <td><?= e($vendor['store_name']) ?></td>
                    <td><?= e($vendor['first_name'] . ' ' . $vendor['last_name']) ?></td>
                    <td class="text-white-50"><?= e($vendor['email']) ?></td>
                    <td>
                        <?php
                        $badge = match ($vendor['status']) {
                            'approved' => 'bg-success',
                            'rejected' => 'bg-danger',
                            'suspended' => 'bg-danger',
                            default => 'bg-secondary',
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= e(ucfirst($vendor['status'])) ?></span>
                    </td>
                    <td class="text-white-50"><?= e(date('M j, Y', strtotime($vendor['created_at']))) ?></td>
                    <td class="text-end">
                        <a href="/admin/vendors/<?= (int) $vendor['id'] ?>" class="btn btn-sm btn-outline-light">Review</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
