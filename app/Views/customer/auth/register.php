<h5 class="text-center mb-4" style="color:rgba(248,247,244,.75);">Create your account</h5>
<form method="POST" action="/register" novalidate>
    <?= csrf_field() ?>
    <div class="row g-2 mb-3">
        <div class="col-6">
            <label class="form-label" for="first_name">First name</label>
            <input type="text" class="form-control" id="first_name" name="first_name" value="<?= e(old('first_name')) ?>" required>
        </div>
        <div class="col-6">
            <label class="form-label" for="last_name">Last name</label>
            <input type="text" class="form-control" id="last_name" name="last_name" value="<?= e(old('last_name')) ?>" required>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="email">Email address</label>
        <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input type="password" class="form-control" id="password" name="password" minlength="8" required>
    </div>
    <div class="mb-4">
        <label class="form-label" for="password_confirmation">Confirm password</label>
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" required>
    </div>
    <button type="submit" class="btn btn-gold w-100 py-2">Create Account</button>
</form>
<p class="text-center mt-4 divider-text">Already have an account? <a href="/login">Sign in</a></p>
