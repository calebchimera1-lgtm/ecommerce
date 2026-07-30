<?php

use App\Core\Session;

/** @var array|null $__alertSuccess */
$__alertSuccess = Session::getFlash('success');
/** @var array<string,array<int,string>> $__alertErrors */
$__alertErrors = Session::getFlash('errors', []);
?>
<?php if ($__alertSuccess): ?>
<div class="alert alert-success" role="alert"><?= e($__alertSuccess) ?></div>
<?php endif; ?>
<?php if (!empty($__alertErrors)): ?>
<div class="alert alert-danger" role="alert">
    <ul class="mb-0 ps-3">
        <?php foreach ($__alertErrors as $__field => $__messages): ?>
            <?php foreach ((array) $__messages as $__message): ?>
                <li><?= e($__message) ?></li>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
