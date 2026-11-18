<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Вложения с загрузкой и удалением.
 *
 * @var list<Complaints\Application\View\AttachmentView> $attachments
 * @var string|null                                      $removePath адрес удаления без id вложения; null — только просмотр
 */
if ($attachments === []) {
    echo '<p class="text-muted">' . complaints_te('complaints_no_files') . '</p>';

    return;
}
?>
<ul class="list-unstyled complaints-files">
    <?php foreach ($attachments as $view) : ?>
        <li>
            <a href="<?= complaints_e(complaints_url('complaint_files/download/attachment/' . $view->attachment->id)) ?>">
                <span class="glyphicon glyphicon-paperclip"></span> <?= complaints_e($view->file->fileName) ?>
            </a>
            <span class="text-muted">(<?= complaints_e(complaints_size($view->file->size)) ?>)</span>
            <?php if (!empty($removePath)) : ?>
                <button type="button" class="btn btn-link btn-xs text-danger"
                        data-complaints-post="<?= complaints_e(complaints_url($removePath . $view->attachment->id)) ?>"
                        data-confirm="<?= complaints_te('complaints_confirm_remove_file') ?>">
                    <span class="glyphicon glyphicon-remove"></span> <?= complaints_te('complaints_remove') ?>
                </button>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
