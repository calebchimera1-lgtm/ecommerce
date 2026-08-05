<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Kymera Collection Vendor Portal') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --kymera-black: #0b0b0c;
            --kymera-gold: #c9a24b;
            --kymera-white: #f8f7f4;
        }
        body {
            background: radial-gradient(circle at top, #1a1a1c 0%, var(--kymera-black) 70%);
            color: var(--kymera-white);
            font-family: "Georgia", serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .auth-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(201, 162, 75, 0.35);
            border-radius: 1rem;
            backdrop-filter: blur(10px);
            padding: 2.5rem;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        .brand {
            text-align: center;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            color: var(--kymera-gold);
            font-size: 1.15rem;
            margin-bottom: 0.25rem;
        }
        .brand-sub {
            text-align: center;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: rgba(248, 247, 244, 0.4);
            font-size: 0.7rem;
            margin-bottom: 1.5rem;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.15);
            color: var(--kymera-white);
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.09);
            color: var(--kymera-white);
            border-color: var(--kymera-gold);
            box-shadow: 0 0 0 0.2rem rgba(201, 162, 75, 0.25);
        }
        label { color: rgba(248, 247, 244, 0.75); }
        .btn-gold {
            background: var(--kymera-gold);
            border-color: var(--kymera-gold);
            color: #0b0b0c;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        .btn-gold:hover { background: #b8913e; border-color: #b8913e; color: #0b0b0c; }
        a { color: var(--kymera-gold); }
        .divider-text { color: rgba(248, 247, 244, 0.55); font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="brand">Kymera Collection</div>
        <div class="brand-sub">Vendor Portal</div>
        <?php require dirname(__DIR__, 2) . '/partials/alerts.php'; ?>
        <?php $content(); ?>
    </div>
</body>
</html>
