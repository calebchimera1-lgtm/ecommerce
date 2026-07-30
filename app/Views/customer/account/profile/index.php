<?php /** @var array $user @var array $addresses */ ?>
<section class="section">
    <div class="container" style="max-width:800px;">
        <?php require dirname(__DIR__, 3) . '/partials/account-nav.php'; ?>

        <h4 class="mb-4">Profile Information</h4>
        <form method="POST" action="/account/profile" class="row g-3 mb-5">
            <?= csrf_field() ?>
            <div class="col-md-6">
                <label class="form-label sans small">First name</label>
                <input type="text" class="form-control" name="first_name" value="<?= e($user['first_name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label sans small">Last name</label>
                <input type="text" class="form-control" name="last_name" value="<?= e($user['last_name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label sans small">Email address</label>
                <input type="email" class="form-control" name="email" value="<?= e($user['email']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label sans small">Phone</label>
                <input type="text" class="form-control" name="phone" value="<?= e($user['phone'] ?? '') ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-gold">Save Changes</button>
            </div>
        </form>

        <h4 class="mb-4">Change Password</h4>
        <form method="POST" action="/account/password" class="row g-3 mb-5">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label sans small">Current password</label>
                <input type="password" class="form-control" name="current_password" required>
            </div>
            <div class="col-md-4">
                <label class="form-label sans small">New password</label>
                <input type="password" class="form-control" name="password" minlength="8" required>
            </div>
            <div class="col-md-4">
                <label class="form-label sans small">Confirm new password</label>
                <input type="password" class="form-control" name="password_confirmation" minlength="8" required>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-gold">Update Password</button>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Saved Addresses</h4>
            <button class="btn btn-outline-gold btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#newAddressForm">+ Add Address</button>
        </div>

        <?php foreach ($addresses as $address): ?>
            <div class="p-3 rounded mb-3" style="background:var(--kymera-black-soft);border:1px solid rgba(255,255,255,0.08);">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="sans small">
                        <?php if ((int) $address['is_default'] === 1): ?><span class="badge bg-warning text-dark mb-1">Default</span><br><?php endif; ?>
                        <strong><?= e($address['full_name']) ?></strong> (<?= e(ucfirst($address['type'])) ?>)<br>
                        <?= e($address['address_line1']) ?><?php if (!empty($address['address_line2'])): ?>, <?= e($address['address_line2']) ?><?php endif; ?><br>
                        <?= e($address['city']) ?><?php if (!empty($address['state'])): ?>, <?= e($address['state']) ?><?php endif; ?> <?= e($address['postal_code']) ?><br>
                        <?= e($address['country']) ?> &middot; <?= e($address['phone']) ?>
                    </div>
                    <div class="sans small text-end">
                        <a href="#" data-bs-toggle="collapse" data-bs-target="#editAddress<?= (int) $address['id'] ?>">Edit</a>
                        <form method="POST" action="/account/addresses/<?= (int) $address['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Remove this address?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-link btn-sm text-danger p-0 ms-2">Delete</button>
                        </form>
                    </div>
                </div>
                <div class="collapse mt-3" id="editAddress<?= (int) $address['id'] ?>">
                    <?php $formAction = '/account/addresses/' . (int) $address['id']; $addr = $address; ?>
                    <?php require __DIR__ . '/_address_form.php'; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="collapse" id="newAddressForm">
            <div class="p-3 rounded mb-3" style="background:var(--kymera-black-soft);border:1px solid rgba(255,255,255,0.08);">
                <?php $formAction = '/account/addresses'; $addr = null; ?>
                <?php require __DIR__ . '/_address_form.php'; ?>
            </div>
        </div>
    </div>
</section>
