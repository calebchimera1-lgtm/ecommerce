<?php /** @var array $customer @var array $orders @var array $reviews @var array $statuses */ ?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1" style="color:#f8f7f4;"><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></h4>
        <p class="text-white-50 small mb-0"><?= e($customer['email']) ?><?php if (!empty($customer['phone'])): ?> &middot; <?= e($customer['phone']) ?><?php endif; ?></p>
        <p class="text-white-50 small mb-0">Joined <?= e(date('F j, Y', strtotime($customer['created_at']))) ?></p>
    </div>
    <a href="/admin/customers" class="btn btn-outline-light btn-sm">&larr; Back to Customers</a>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <h6 class="text-white-50 small text-uppercase">Account Status</h6>
        <form method="POST" action="/admin/customers/<?= (int) $customer['id'] ?>/status" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-8">
                <select class="form-select" name="status">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= $customer['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4">
                <button type="submit" class="btn btn-gold btn-sm w-100">Save</button>
            </div>
        </form>
    </div>
</div>

<h6 class="mb-3" style="color:#f8f7f4;">Orders (<?= count($orders) ?>)</h6>
<div class="table-responsive mb-4">
    <table class="table table-dark table-sm align-middle">
        <thead><tr><th>Order</th><th>Status</th><th>Payment</th><th class="text-end">Total</th><th>Placed</th></tr></thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="5" class="text-white-50">No orders yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><a href="/admin/orders/<?= (int) $order['id'] ?>" class="text-white"><?= e($order['order_number']) ?></a></td>
                    <td><span class="badge bg-secondary"><?= e(ucfirst($order['status'])) ?></span></td>
                    <td class="text-white-50"><?= e(ucfirst($order['payment_status'])) ?></td>
                    <td class="text-end"><?= money($order['total']) ?></td>
                    <td class="text-white-50"><?= e(date('M j, Y', strtotime($order['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<h6 class="mb-3" style="color:#f8f7f4;">Reviews (<?= count($reviews) ?>)</h6>
<div class="table-responsive">
    <table class="table table-dark table-sm align-middle">
        <thead><tr><th>Product</th><th>Rating</th><th>Status</th><th>Posted</th></tr></thead>
        <tbody>
            <?php if (empty($reviews)): ?>
                <tr><td colspan="4" class="text-white-50">No reviews yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($reviews as $review): ?>
                <tr>
                    <td><?= e($review['product_name']) ?></td>
                    <td><?= str_repeat('&#9733;', (int) $review['rating']) . str_repeat('&#9734;', 5 - (int) $review['rating']) ?></td>
                    <td>
                        <?php if ((int) $review['is_approved'] === 1): ?>
                            <span class="badge bg-success">Approved</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-white-50"><?= e(date('M j, Y', strtotime($review['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
