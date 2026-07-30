<?php /** @var string $token @var string $email */ ?>
<h5 class="text-center mb-4" style="color:rgba(248,247,244,.75);">Choose a new password</h5>
<form method="POST" action="/reset-password" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <input type="hidden" name="email" value="<?= e($email) ?>">
    <div class="mb-3">
        <label class="form-label" for="password">New password</label>
        <input type="password" class="form-control" id="password" name="password" minlength="8" required>
    </div>
    <div class="mb-4">
        <label class="form-label" for="password_confirmation">Confirm new password</label>
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" required>
    </div>
    <button type="submit" class="btn btn-gold w-100 py-2">Reset Password</button>
</form>
