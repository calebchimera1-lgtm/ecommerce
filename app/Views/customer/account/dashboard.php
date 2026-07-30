<?php /** @var array|null $user */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --kymera-black:#0b0b0c; --kymera-gold:#c9a24b; --kymera-white:#f8f7f4; }
        body { background: var(--kymera-black); color: var(--kymera-white); font-family: Georgia, serif; min-height:100vh; display:flex; align-items:center; justify-content:center; text-align:center; }
        .brand { letter-spacing:.3em; text-transform:uppercase; color:var(--kymera-gold); font-size:1.5rem; }
        .divider { width:60px; height:1px; background:var(--kymera-gold); margin:1.25rem auto; }
        .btn-outline-light:hover { color:#0b0b0c; }
    </style>
</head>
<body>
    <div>
        <div class="brand">Kymera Collection</div>
        <div class="divider"></div>
        <p>Welcome back, <?= e($user['first_name'] ?? 'Guest') ?>.</p>
        <p class="small text-white-50"><?= e($user['email'] ?? '') ?></p>
        <p class="small text-white-50">Full order history, wishlist, tracking, and profile management arrive in the Customer Dashboard module.</p>
        <form method="POST" action="/logout" class="mt-4">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-light">Log out</button>
        </form>
    </div>
</body>
</html>
