<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * PDF ответа: «Сформировать» / «Сбросить» и файлы ответа.
 *
 * @var Complaints\Application\View\AnswerView|null $answer
 * @var bool                                        $editable
 * @var string                                      $uploadAction адрес загрузки файла к ответу
 * @var array<string, scalar>                       $uploadFields
 */
?>
<div class="complaints-answer-document">
    <?php if ($answer === null) : ?>
        <span class="text-muted"><?= complaints_te('complaints_answer_not_saved') ?></span>
    <?php else : ?>
        <?php $this->load->view('complaints/partials/document', ['document' => $answer->document]); ?>
        <?php if ($editable) : ?>
            <div>
                <?php if ($answer->isFormed()) : ?>
                    <button type="button" class="btn btn-warning btn-xs"
                            data-complaints-post="<?= complaints_e(complaints_url('complaint_review/reset_answer/' . $answer->answer->id)) ?>"
                            data-confirm="<?= complaints_te('complaints_confirm_reset_answer') ?>"><?= complaints_te('complaints_reset_answer') ?></button>
                <?php else : ?>
                    <button type="button" class="btn btn-default btn-xs"
                            data-complaints-post="<?= complaints_e(complaints_url('complaint_review/form_answer/' . $answer->answer->id)) ?>"
                            data-save-form="complaints-lot-form"><?= complaints_te('complaints_form_answer') ?></button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <h5><?= complaints_te('complaints_answer_files') ?></h5>
        <?php $this->load->view('complaints/partials/attachments', [
            'attachments' => $answer->attachments,
            'removePath' => $editable && !$answer->isFormed() ? 'complaint_review/detach_answer/' : null,
        ]); ?>
    <?php endif; ?>
    <?php if ($editable && ($answer === null || !$answer->isFormed())) : ?>
        <?php $this->load->view('complaints/partials/upload', ['action' => $uploadAction, 'fields' => $uploadFields]); ?>
    <?php endif; ?>
</div>
