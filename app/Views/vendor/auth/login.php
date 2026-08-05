<h5 class="text-center mb-4" style="color:rgba(248,247,244,.75);">Vendor Sign In</h5>
<form method="POST" action="/vendor/login" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="email">Email address</label>
        <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>" required>
    </div>
    <div class="mb-4">
        <label class="form-label" for="password">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-gold w-100 py-2">Sign In</button>
</form>
<p class="text-center mt-4 mb-0 divider-text">Want to sell with us? <a href="/vendor/register">Apply to become a vendor</a></p>
<p class="text-center mt-2 divider-text"><a href="/">&larr; Back to storefront</a></p>
