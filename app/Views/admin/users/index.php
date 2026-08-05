<?php /** @var array $users @var int $page @var int $perPage @var int $total */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0" style="color:#f8f7f4;">Staff Users</h4>
    <a href="/admin/users/create" class="btn btn-gold btn-sm">+ New Staff User</a>
</div>

<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="5" class="text-white-50">No staff users yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= e($user['first_name'] . ' ' . $user['last_name']) ?></td>
                    <td class="text-white-50"><?= e($user['email']) ?></td>
                    <td><?= e($user['role_name']) ?></td>
                    <td>
                        <?php
                        $badge = match ($user['status']) {
                            'active' => 'bg-success',
                            'banned' => 'bg-danger',
                            default => 'bg-secondary',
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= e(ucfirst($user['status'])) ?></span>
                    </td>
                    <td class="text-end">
                        <a href="/admin/users/<?= (int) $user['id'] ?>/edit" class="btn btn-sm btn-outline-light">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
