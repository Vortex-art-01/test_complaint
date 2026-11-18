<?php

defined('BASEPATH') or exit('No direct script access allowed');

use Complaints\Domain\BuyComplaint\ReviewMethod;
use Complaints\Domain\BuyComplaint\Verdict;

/**
 * Лот решения: жалобы, вердикты, ответы и решение по лоту.
 *
 * @var Complaints\Application\BuyComplaint\DecisionLotDetails $details
 */
$decision = $details->decision;
$lot = $details->lot;
$editable = $details->editable;
$perLot = $decision->reviewMethod === ReviewMethod::PerLot;
$formAttribute = $editable ? ' form="complaints-lot-form"' : ' disabled';
$lotLocked = $perLot && $details->lotAnswer !== null && $details->lotAnswer->isFormed();
?>
<div class="panel panel-default complaints">
    <?php $this->load->view('complaints/partials/heading', ['heading' => complaints_t('complaints_title_decision_lot', ['lot' => $details->buyLot->number])]); ?>
    <div class="panel-body">
        <?php $this->load->view('complaints/partials/errors', ['error' => null]); ?>
        <p>
            <b><?= complaints_e($details->buyLot->number) ?></b> — <?= complaints_text($details->buyLot->name) ?>
            <br><?= complaints_label('decision_status', $decision->status) ?> <?= complaints_label('review_method', $decision->reviewMethod) ?>
        </p>

        <?php if ($editable) : ?>
            <form method="post" id="complaints-lot-form" action="<?= complaints_e(complaints_url('complaint_review/save_lot/' . $lot->id)) ?>" data-complaints-form></form>
        <?php endif; ?>

        <?php foreach ($details->complaints as $index => $view) : ?>
            <?php
            $complaint = $view->complaint;
            $complaintLotId = $view->complaintLot->id;
            $locked = $perLot ? $lotLocked : ($view->answer !== null && $view->answer->isFormed());
            ?>
            <div class="panel panel-default complaints-lot-complaint">
                <div class="panel-heading">
                    <b><?= complaints_te('complaints_complaint_number') ?> <?= complaints_e($complaint->number->value) ?></b>
                    — <?= complaints_text($view->supplier->name) ?>, <?= complaints_te('complaints_bin') ?> <?= complaints_e($view->supplier->bin) ?>
                    <span class="pull-right"><?= complaints_te('complaints_submitted_at', ['date' => complaints_moment($complaint->submittedAt)]) ?></span>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="complaints-text"><?= nl2br(complaints_e((string) $complaint->description)) ?></p>
                            <p><small><?= complaints_te('complaints_contact_phone') ?>: <?= complaints_e((string) $complaint->contactPhone) ?></small></p>
                            <?php $this->load->view('complaints/partials/document', ['document' => $view->complaintDocument]); ?>
                            <h5><?= complaints_te('complaints_attachments') ?></h5>
                            <?php $this->load->view('complaints/partials/attachments', ['attachments' => $view->attachments, 'removePath' => null]); ?>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= complaints_te('complaints_verdict') ?></label>
                                <?php foreach (Verdict::cases() as $verdict) : ?>
                                    <label class="radio-inline">
                                        <input type="radio" name="verdicts[<?= (int) $complaintLotId ?>]" value="<?= complaints_e($verdict->value) ?>"
                                            <?= $view->complaintLot->verdict === $verdict ? 'checked' : '' ?><?= $locked ? ' disabled' : $formAttribute ?>>
                                        <?= complaints_te('complaints_verdict_' . $verdict->value) ?>
                                    </label>
                                <?php endforeach; ?>
                                <?php if ($locked && $view->complaintLot->verdict !== null) : ?>
                                    <input type="hidden" name="verdicts[<?= (int) $complaintLotId ?>]" value="<?= complaints_e($view->complaintLot->verdict->value) ?>"<?= $formAttribute ?>>
                                <?php endif; ?>
                            </div>
                            <?php if (!$perLot) : ?>
                                <div class="form-group">
                                    <label for="complaints-answer-<?= (int) $complaintLotId ?>"><?= complaints_te('complaints_answer') ?></label>
                                    <textarea class="form-control" rows="6" id="complaints-answer-<?= (int) $complaintLotId ?>"
                                              name="answers[<?= (int) $complaintLotId ?>]" maxlength="20000"<?= $locked ? ' readonly' : '' ?><?= $formAttribute ?>><?= complaints_e($view->answer?->answer->text ?? '') ?></textarea>
                                </div>
                                <?php $this->load->view('complaints/partials/answer_document', [
                                    'answer' => $view->answer,
                                    'editable' => $editable,
                                    'uploadAction' => complaints_url('complaint_review/attach_answer/' . $lot->id),
                                    'uploadFields' => ['complaint_lot_id' => $complaintLotId],
                                ]); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($perLot) : ?>
            <div class="panel panel-default">
                <div class="panel-heading"><b><?= complaints_te('complaints_lot_answer') ?></b></div>
                <div class="panel-body">
                    <div class="form-group">
                        <textarea class="form-control" rows="8" name="lot_answer" maxlength="20000"
                            <?= $lotLocked ? 'readonly' : '' ?><?= $formAttribute ?>><?= complaints_e($details->lotAnswer?->answer->text ?? '') ?></textarea>
                    </div>
                    <?php $this->load->view('complaints/partials/answer_document', [
                        'answer' => $details->lotAnswer,
                        'editable' => $editable,
                        'uploadAction' => complaints_url('complaint_review/attach_answer/' . $lot->id),
                        'uploadFields' => [],
                    ]); ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="panel panel-default">
            <div class="panel-heading"><b><?= complaints_te('complaints_decision_type') ?></b></div>
            <div class="panel-body">
                <select class="form-control" name="decision_type"<?= $formAttribute ?>>
                    <option value=""><?= complaints_te('complaints_not_selected') ?></option>
                    <?php foreach ($details->allowedDecisionTypes as $type) : ?>
                        <option value="<?= complaints_e($type->value) ?>"<?= $lot->decisionType === $type ? ' selected' : '' ?>><?= complaints_te('complaints_decision_type_' . $type->value) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="help-block"><?= complaints_te('complaints_decision_type_hint') ?></p>
            </div>
        </div>

        <?php if ($editable) : ?>
            <button type="submit" form="complaints-lot-form" class="btn btn-primary"><?= complaints_te('complaints_save') ?></button>
        <?php endif; ?>
        <a href="<?= complaints_e(complaints_url('complaint_review/show/' . $decision->id)) ?>" class="btn btn-default"><?= complaints_te('complaints_back_to_decision') ?></a>
    </div>
</div>
<?php $this->load->view('complaints/partials/scripts'); ?>
