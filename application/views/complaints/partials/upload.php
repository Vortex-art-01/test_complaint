<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Загрузка файла: форма отправляется complaints.js (FormData с CSRF-токеном).
 *
 * @var string               $action адрес загрузки
 * @var array<string, mixed> $fields дополнительные скрытые поля
 */
$extensions = (array) complaints_config('attachment_extensions');
?>
<form class="form-inline complaints-upload" method="post" enctype="multipart/form-data"
      action="<?= complaints_e($action) ?>" data-complaints-form>
    <?php foreach ($fields ?? [] as $name => $value) : ?>
        <input type="hidden" name="<?= complaints_e($name) ?>" value="<?= complaints_e($value) ?>">
    <?php endforeach; ?>
    <div class="form-group">
        <input type="file" name="file" required accept="<?= complaints_e(implode(',', array_map(static fn ($ext): string => '.' . $ext, $extensions))) ?>">
    </div>
    <button type="submit" class="btn btn-default btn-sm">
        <span class="glyphicon glyphicon-upload"></span> <?= complaints_te('complaints_upload') ?>
    </button>
    <p class="help-block">
        <?= complaints_te('complaints_upload_hint', [
            'types' => implode(', ', $extensions),
            'size' => (int) ((int) complaints_config('attachment_max_bytes') / 1024 / 1024),
            'count' => (int) complaints_config('attachment_max_count'),
        ]) ?>
    </p>
</form>
