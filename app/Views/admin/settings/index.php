<?php /** @var array<int,array{setting_key:string,value:?string,group:string}> $settings */ ?>
<h4 class="mb-1" style="color:#f8f7f4;">Settings</h4>
<p class="text-white-50 mb-4">Read-only for now &mdash; an editing form arrives in the Settings module.</p>
<div class="table-responsive">
    <table class="table table-dark table-hover align-middle">
        <thead>
            <tr>
                <th>Group</th>
                <th>Key</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($settings as $setting): ?>
                <tr>
                    <td class="text-white-50"><?= e($setting['group']) ?></td>
                    <td><?= e($setting['setting_key']) ?></td>
                    <td><?= e($setting['value']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
