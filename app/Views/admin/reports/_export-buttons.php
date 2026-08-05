<?php
/** @var string $exportBase e.g. '/admin/reports/sales?start_date=...&end_date=...' (no format param) */
$sep = str_contains($exportBase, '?') ? '&' : '?';
?>
<div class="btn-group btn-group-sm">
    <a href="<?= e($exportBase . $sep . 'format=csv') ?>" class="btn btn-outline-light">Export CSV</a>
    <a href="<?= e($exportBase . $sep . 'format=excel') ?>" class="btn btn-outline-light">Export Excel</a>
    <a href="<?= e($exportBase . $sep . 'format=pdf') ?>" class="btn btn-outline-light">Export PDF</a>
</div>
