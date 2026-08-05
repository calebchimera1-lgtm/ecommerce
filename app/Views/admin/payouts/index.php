<?php /** @var array $balances */ ?>
<h4 class="mb-4" style="color:#f8f7f4;">Vendor Payouts</h4>

<p class="text-white-50 small mb-4">
    Commission is calculated automatically per order; payouts themselves are recorded manually here after
    you've sent a vendor their money outside the app. This overview shows every vendor with at least one
    order, sorted by how much they're currently owed.
</p>

<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>Vendor</th>
                <th>Orders</th>
                <th class="text-end">Unpaid Balance</th>
                <th class="text-end">Paid To Date</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($balances)): ?>
                <tr><td colspan="5" class="text-white-50">No vendor orders yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($balances as $balance): ?>
                <tr>
                    <td><?= e($balance['store_name']) ?></td>
                    <td><?= (int) $balance['order_count'] ?></td>
                    <td class="text-end <?= (float) $balance['unpaid_balance'] > 0 ? 'text-warning' : 'text-white-50' ?>"><?= money($balance['unpaid_balance']) ?></td>
                    <td class="text-end text-white-50"><?= money($balance['paid_to_date']) ?></td>
                    <td class="text-end"><a href="/admin/payouts/<?= (int) $balance['vendor_id'] ?>" class="btn btn-sm btn-outline-light">View Ledger</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
