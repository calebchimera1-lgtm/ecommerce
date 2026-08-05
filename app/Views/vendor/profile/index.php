<?php /** @var array $vendor */ ?>
<h4 class="mb-4" style="color:#f8f7f4;">Store Profile</h4>

<form method="POST" action="/vendor/profile" enctype="multipart/form-data" class="row g-3" style="max-width:640px;">
    <?= csrf_field() ?>
    <div class="col-12">
        <label class="form-label" for="store_name">Store name</label>
        <input type="text" class="form-control" id="store_name" name="store_name" required maxlength="150"
               value="<?= e((string) ($vendor['store_name'] ?? old('store_name'))) ?>">
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Store description</label>
        <textarea class="form-control" id="description" name="description" rows="3"><?= e((string) ($vendor['description'] ?? old('description'))) ?></textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">Phone</label>
        <input type="text" class="form-control" id="phone" name="phone" value="<?= e((string) ($vendor['phone'] ?? old('phone'))) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="business_registration_number">Business registration number</label>
        <input type="text" class="form-control" id="business_registration_number" name="business_registration_number"
               value="<?= e((string) ($vendor['business_registration_number'] ?? old('business_registration_number'))) ?>">
    </div>

    <?php if (!empty($vendor['logo'])): ?>
        <div class="col-12">
            <img src="<?= e($vendor['logo']) ?>" alt="" class="rounded" style="max-height:120px;">
        </div>
    <?php endif; ?>
    <div class="col-12">
        <label class="form-label" for="logo">Store logo (JPG, PNG, or WEBP)</label>
        <input type="file" class="form-control" id="logo" name="logo" accept="image/jpeg,image/png,image/webp">
    </div>

    <div class="col-12">
        <label class="form-label" for="payout_details">Payout details</label>
        <textarea class="form-control" id="payout_details" name="payout_details" rows="2"
                  placeholder="Bank name, account number, and any other details we should use to pay you"><?= e((string) ($vendor['payout_details'] ?? old('payout_details'))) ?></textarea>
        <div class="form-text text-white-50">Payouts are recorded and paid manually by our team, not processed automatically.</div>
    </div>

    <div class="col-12 mt-3">
        <button type="submit" class="btn btn-gold">Save Changes</button>
    </div>
</form>
