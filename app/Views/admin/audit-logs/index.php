<?php /** @var array $logs @var int $page @var int $perPage @var int $total */ ?>
<h4 class="mb-4" style="color:#f8f7f4;">Audit Log</h4>

<div class="table-responsive">
    <table class="table table-dark table-sm align-middle">
        <thead>
            <tr>
                <th>When</th>
                <th>User</th>
                <th>Action</th>
                <th>Model</th>
                <th>Changes</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="6" class="text-white-50">No audit entries yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="text-white-50 small"><?= e(date('M j, Y g:ia', strtotime($log['created_at']))) ?></td>
                    <td class="small"><?= $log['user_id'] !== null ? e(trim($log['first_name'] . ' ' . $log['last_name'])) : 'System' ?></td>
                    <td class="small"><span class="badge bg-secondary"><?= e($log['action']) ?></span></td>
                    <td class="small text-white-50"><?= $log['model'] !== null ? e($log['model']) . ' #' . (int) $log['model_id'] : '&mdash;' ?></td>
                    <td class="small text-white-50" style="max-width:320px;">
                        <?php if ($log['old_values'] !== null): ?>
                            <div>Before: <code class="text-white-50"><?= e(mb_strimwidth((string) $log['old_values'], 0, 100, '...')) ?></code></div>
                        <?php endif; ?>
                        <?php if ($log['new_values'] !== null): ?>
                            <div>After: <code class="text-white-50"><?= e(mb_strimwidth((string) $log['new_values'], 0, 100, '...')) ?></code></div>
                        <?php endif; ?>
                    </td>
                    <td class="small text-white-50"><?= e($log['ip_address'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
