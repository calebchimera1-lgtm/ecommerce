<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Invoice | Kymera Collection') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #fff; color: #111; font-family: Arial, Helvetica, sans-serif; }
        .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; padding: 2rem 0 1rem; border-bottom: 3px solid #c9a24b; }
        .brand { letter-spacing: 0.2em; text-transform: uppercase; color: #0b0b0c; font-weight: bold; font-size: 1.25rem; }
        .brand span { display: block; color: #c9a24b; font-size: 0.7rem; letter-spacing: 0.15em; margin-top: 0.25rem; }
        table.invoice-table th { background: #f5f5f5; }
        .print-toolbar { padding: 1rem 0; }
        @media print {
            .print-toolbar { display: none; }
            body { background: #fff; }
        }
    </style>
</head>
<body>
    <div class="container" style="max-width:800px;">
        <div class="print-toolbar text-end">
            <button onclick="window.print()" class="btn btn-dark btn-sm">Print / Save as PDF</button>
        </div>
        <?php $content(); ?>
    </div>
</body>
</html>
