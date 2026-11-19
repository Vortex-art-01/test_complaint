<?php

defined('BASEPATH') or exit('No direct script access allowed');

use Complaints\Application\AppComplaint\ComplaintAction;

/**
 * Карточка жалобы: документ, подпись руководителя, подача, отзыв и ответ органа.
 *
 * @var Complaints\Application\AppComplaint\ComplaintCard $card
 */
$complaint = $card->complaint;
$id = $complaint->id;
$answer = $card->answer;
?>
<div class="panel panel-default complaints">
    <?php $this->load->view('complaints/partials/heading', ['heading' => complaints_t('complaints_title_complaint', ['number' => $complaint->number->value])]); ?>
    <div class="panel-body">
        <?php $this->load->view('complaints/partials/errors', ['error' => null]); ?>
        <p>
            <?= complaints_label('complaint_status', $complaint->status) ?>
            <?php if ($complaint->submittedAt !== null) : ?>
                <span class="text-muted"><?= complaints_te('complaints_submitted_at', ['date' => complaints_moment($complaint->submittedAt)]) ?></span>
            <?php endif; ?>
        </p>

        <?php $this->load->view('complaints/partials/requisites', ['requisites' => $card->requisites, 'complaint' => $complaint]); ?>

        <table class="table table-bordered">
            <tr><th class="col-md-3"><?= complaints_te('complaints_contact_phone') ?></th><td><?= complaints_e((string) $complaint->contactPhone) ?></td></tr>
            <tr><th><?= complaints_te('complaints_description') ?></th><td class="complaints-text"><?= nl2br(complaints_e((string) $complaint->description)) ?></td></tr>
            <tr>
                <th><?= complaints_te('complaints_attachments') ?></th>
                <td><?php $this->load->view('complaints/partials/attachments', ['attachments' => $card->attachments, 'removePath' => null]); ?></td>
            </tr>
            <tr><th><?= complaints_te('complaints_document') ?></th><td><?php $this->load->view('complaints/partials/document', ['document' => $card->document]); ?></td></tr>
        </table>

        <h4><?= complaints_te('complaints_signatures') ?></h4>
        <?php $this->load->view('complaints/partials/signatures', ['signatures' => $card->signatures]); ?>

        <div class="complaints-actions">
            <?php if ($card->allows(ComplaintAction::Sign) && $card->document !== null) : ?>
                <button type="button" class="btn btn-success"
                        data-complaints-sign="<?= complaints_e(complaints_url('complaint_supplier/sign/' . $id)) ?>"
                        data-source="<?= complaints_e(complaints_url('complaint_files/source/' . $card->document->document->id)) ?>">
                    <span class="glyphicon glyphicon-pencil"></span> <?= complaints_te('complaints_sign') ?>
                </button>
            <?php endif; ?>
            <?php if ($card->allows(ComplaintAction::Submit)) : ?>
                <button type="button" class="btn btn-success"
                        data-complaints-post="<?= complaints_e(complaints_url('complaint_supplier/submit/' . $id)) ?>"
                        data-confirm="<?= complaints_te('complaints_confirm_submit') ?>"><?= complaints_te('complaints_submit') ?></button>
            <?php endif; ?>
            <?php if ($card->allows(ComplaintAction::ReturnToDraft)) : ?>
                <button type="button" class="btn btn-default"
                        data-complaints-post="<?= complaints_e(complaints_url('complaint_supplier/return_to_draft/' . $id)) ?>"
                        data-confirm="<?= complaints_te('complaints_confirm_return_to_draft') ?>"><?= complaints_te('complaints_return_to_draft') ?></button>
            <?php endif; ?>
            <?php if ($card->allows(ComplaintAction::Edit)) : ?>
                <a class="btn btn-primary" href="<?= complaints_e(complaints_url('complaint_supplier/edit/' . $id)) ?>"><?= complaints_te('complaints_edit') ?></a>
            <?php endif; ?>
            <?php if ($card->allows(ComplaintAction::Withdraw)) : ?>
                <button type="button" class="btn btn-warning"
                        data-complaints-post="<?= complaints_e(complaints_url('complaint_supplier/withdraw/' . $id)) ?>"
                        data-confirm="<?= complaints_te('complaints_confirm_withdraw') ?>"><?= complaints_te('complaints_withdraw') ?></button>
            <?php endif; ?>
            <?php if ($card->allows(ComplaintAction::Delete)) : ?>
                <button type="button" class="btn btn-danger"
                        data-complaints-post="<?= complaints_e(complaints_url('complaint_supplier/delete/' . $id)) ?>"
                        data-confirm="<?= complaints_te('complaints_confirm_delete') ?>"><?= complaints_te('complaints_delete') ?></button>
            <?php endif; ?>
            <a href="<?= complaints_e(complaints_url('complaint_supplier/index')) ?>" class="btn btn-default"><?= complaints_te('complaints_back') ?></a>
        </div>

        <?php if ($answer !== null) : ?>
            <div class="panel panel-<?= $answer->verdict->value === 'satisfied' ? 'success' : 'danger' ?> complaints-answer">
                <div class="panel-heading"><b><?= complaints_te('complaints_answer_of_authority') ?></b></div>
                <div class="panel-body">
                    <table class="table table-bordered table-condensed">
                        <tr><th class="col-md-3"><?= complaints_te('complaints_verdict') ?></th><td><?= complaints_label('verdict', $answer->verdict) ?></td></tr>
                        <tr><th><?= complaints_te('complaints_decision_type') ?></th><td><?= complaints_label('decision_type', $answer->decisionType) ?></td></tr>
                        <tr>
                            <th><?= complaints_te('complaints_decision') ?></th>
                            <td><?= complaints_te('complaints_decision_number_date', ['number' => $answer->decisionId, 'date' => complaints_moment($answer->approvedAt)]) ?></td>
                        </tr>
                        <tr><th><?= complaints_te('complaints_authority') ?></th><td><?= complaints_text($answer->authority->name) ?></td></tr>
                        <tr><th><?= complaints_te('complaints_reviewer') ?></th><td><?= complaints_e($answer->reviewerName) ?></td></tr>
                        <tr><th><?= complaints_te('complaints_answer') ?></th><td class="complaints-text"><?= nl2br(complaints_e($answer->answer->answer->text)) ?></td></tr>
                        <tr><th><?= complaints_te('complaints_document') ?></th><td><?php $this->load->view('complaints/partials/document', ['document' => $answer->answer->document]); ?></td></tr>
                        <tr>
                            <th><?= complaints_te('complaints_attachments') ?></th>
                            <td><?php $this->load->view('complaints/partials/attachments', ['attachments' => $answer->answer->attachments, 'removePath' => null]); ?></td>
                        </tr>
                        <tr>
                            <th><?= complaints_te('complaints_signed_by') ?></th>
                            <td>
                                <?php foreach ($answer->signers as $signer) : ?>
                                    <div><?= complaints_te('complaints_signer_role_' . $signer->signature->role->value) ?>: <?= complaints_e($signer->signerName) ?>, <?= complaints_moment($signer->signature->signedAt) ?></div>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $this->load->view('complaints/partials/scripts'); ?>
