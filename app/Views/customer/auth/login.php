<h5 class="text-center mb-4" style="color:rgba(248,247,244,.75);">Sign in</h5>
<form method="POST" action="/login" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="email">Email address</label>
        <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
    </div>
    <div class="form-check mb-4">
        <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
        <label class="form-check-label" for="remember">Remember me</label>
    </div>
    <button type="submit" class="btn btn-gold w-100 py-2">Sign In</button>
</form>
<p class="text-center mt-3 divider-text"><a href="/forgot-password">Forgot your password?</a></p>
<p class="text-center mt-2 divider-text">New to Kymera Collection? <a href="/register">Create an account</a></p>

<details class="mt-4">
    <summary class="divider-text" style="cursor:pointer;">Didn't get a verification email?</summary>
    <form method="POST" action="/resend-verification" class="mt-3">
        <?= csrf_field() ?>
        <div class="input-group">
            <input type="email" class="form-control" name="email" placeholder="you@example.com" required>
            <button type="submit" class="btn btn-outline-light">Resend</button>
        </div>
    </form>
</details>
