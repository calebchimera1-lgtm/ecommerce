<?php /** @var array $customers @var array $filters @var array $statuses @var int $page @var int $perPage @var int $total */ ?>
<h4 class="mb-4" style="color:#f8f7f4;">Customers</h4>

<form method="GET" action="/admin/customers" class="row g-2 mb-4">
    <div class="col-md-5">
        <input type="text" class="form-control" name="search" placeholder="Search by name or email"
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
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Joined</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($customers)): ?>
                <tr><td colspan="5" class="text-white-50">No customers found.</td></tr>
            <?php endif; ?>
            <?php foreach ($customers as $customer): ?>
                <tr>
                    <td><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></td>
                    <td class="text-white-50"><?= e($customer['email']) ?></td>
                    <td>
                        <?php
                        $badge = match ($customer['status']) {
                            'active' => 'bg-success',
                            'banned' => 'bg-danger',
                            default => 'bg-secondary',
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= e(ucfirst($customer['status'])) ?></span>
                    </td>
                    <td class="text-white-50"><?= e(date('M j, Y', strtotime($customer['created_at']))) ?></td>
                    <td class="text-end">
                        <a href="/admin/customers/<?= (int) $customer['id'] ?>" class="btn btn-sm btn-outline-light">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
