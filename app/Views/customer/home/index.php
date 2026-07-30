<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->e($pageTitle) ?></title>
    <meta name="description" content="Kymera Collection - a luxury destination for fashion, watches, jewelry, perfumes, bags and accessories.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --kymera-black: #0b0b0c;
            --kymera-gold: #c9a24b;
            --kymera-white: #f8f7f4;
        }
        body {
            background: var(--kymera-black);
            color: var(--kymera-white);
            font-family: "Georgia", serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .brand {
            letter-spacing: 0.35em;
            font-size: 2.5rem;
            color: var(--kymera-gold);
            text-transform: uppercase;
        }
        .tagline {
            letter-spacing: 0.15em;
            opacity: 0.75;
        }
        .divider {
            width: 60px;
            height: 1px;
            background: var(--kymera-gold);
            margin: 1.5rem auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="brand">Kymera Collection</div>
        <div class="divider"></div>
        <p class="tagline">Luxury Redefined &mdash; Scaffold Live &mdash; Full Storefront Coming in the Next Modules</p>
        <p class="small text-white-50">Skeleton served by the custom PHP MVC core. See <code>/health</code> for a JSON status check.</p>
    </div>
</body>
</html>
