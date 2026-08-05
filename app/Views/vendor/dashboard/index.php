<?php /** @var array $vendor @var array $productCounts @var float $unpaidBalance @var int $orderCount */ ?>
<h4 class="mb-1" style="color:#f8f7f4;">Welcome back, <?= e($vendor['store_name']) ?>.</h4>
<p class="text-white-50 mb-4">Your vendor account has been active since <?= e(date('F j, Y', strtotime($vendor['approved_at'] ?? $vendor['created_at']))) ?>.</p>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Live Products</div>
            <div class="stat-value text-success"><?= (int) $productCounts['approved'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Pending Review</div>
            <div class="stat-value text-warning"><?= (int) $productCounts['pending'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Orders To Date</div>
            <div class="stat-value"><?= (int) $orderCount ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Unpaid Balance</div>
            <div class="stat-value text-warning"><?= money($unpaidBalance) ?></div>
        </div>
    </div>
</div>

<div class="chart-card">
    <p class="text-white-50 mb-2">
        Manage your listings from <a href="/vendor/products" class="text-warning">My Products</a> - new
        listings and edits are reviewed by our team before they go live on the storefront.
    </p>
    <p class="text-white-50 mb-2">
        Track fulfillment from <a href="/vendor/orders" class="text-warning">My Orders</a> and see what
        you're owed on the <a href="/vendor/payouts" class="text-warning">Payouts</a> page - payouts are
        recorded and paid manually by our team, not processed automatically.
    </p>
    <p class="text-white-50 mb-0">
        Keep your <a href="/vendor/profile" class="text-warning">Store Profile</a> up to date - your store
        name, description, logo, and payout details are what customers and our team will see.
    </p>
</div>

<style>
    .stat-tile { background: rgba(201,162,75,0.06); border: 1px solid rgba(201,162,75,0.2); border-radius: 0.6rem; padding: 1rem; height: 100%; }
    .stat-label { font-family: Arial, sans-serif; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(248,247,244,0.5); }
    .stat-value { font-size: 1.4rem; font-weight: 600; color: #f8f7f4; margin-top: 0.25rem; }
    .chart-card { background: #141414; border: 1px solid rgba(255,255,255,0.08); border-radius: 0.6rem; padding: 1.25rem; }
</style>
