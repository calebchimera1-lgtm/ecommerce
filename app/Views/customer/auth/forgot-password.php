<h5 class="text-center mb-4" style="color:rgba(248,247,244,.75);">Reset your password</h5>
<p class="divider-text text-center mb-4">
    Enter the email associated with your account and we'll send a link to reset your password.
</p>
<form method="POST" action="/forgot-password" novalidate>
    <?= csrf_field() ?>
    <div class="mb-4">
        <label class="form-label" for="email">Email address</label>
        <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>" required>
    </div>
    <button type="submit" class="btn btn-gold w-100 py-2">Send Reset Link</button>
</form>
<p class="text-center mt-4 divider-text"><a href="/login">Back to sign in</a></p>
