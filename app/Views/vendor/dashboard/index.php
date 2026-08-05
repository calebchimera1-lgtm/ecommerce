<?php /** @var array $vendor */ ?>
<h4 class="mb-1" style="color:#f8f7f4;">Welcome back, <?= e($vendor['store_name']) ?>.</h4>
<p class="text-white-50 mb-4">Your vendor account has been active since <?= e(date('F j, Y', strtotime($vendor['approved_at'] ?? $vendor['created_at']))) ?>.</p>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Account Status</div>
            <div class="stat-value text-success">Approved</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-label">Store Slug</div>
            <div class="stat-value" style="font-size:1rem;"><?= e($vendor['slug']) ?></div>
        </div>
    </div>
</div>

<div class="chart-card">
    <p class="text-white-50 mb-2">
        Product management, order fulfillment, and payout tracking for your store are on the way in the
        next release.
    </p>
    <p class="text-white-50 mb-0">
        In the meantime, keep your <a href="/vendor/profile" class="text-warning">Store Profile</a> up to
        date - your store name, description, logo, and payout details are what customers and our team will
        see once product listings go live.
    </p>
</div>

<style>
    .stat-tile { background: rgba(201,162,75,0.06); border: 1px solid rgba(201,162,75,0.2); border-radius: 0.6rem; padding: 1rem; height: 100%; }
    .stat-label { font-family: Arial, sans-serif; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(248,247,244,0.5); }
    .stat-value { font-size: 1.4rem; font-weight: 600; color: #f8f7f4; margin-top: 0.25rem; }
    .chart-card { background: #141414; border: 1px solid rgba(255,255,255,0.08); border-radius: 0.6rem; padding: 1.25rem; }
</style>
