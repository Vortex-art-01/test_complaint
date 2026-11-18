<?php

defined('BASEPATH') or exit('No direct script access allowed');

use Complaints\Application\BuyComplaint\DecisionAction;
use Complaints\Domain\BuyComplaint\ReviewMethod;

/**
 * Решение по жалобам на закупку у уполномоченного органа.
 *
 * @var Complaints\Application\BuyComplaint\DecisionOverview $overview
 */
$decision = $overview->decision;
$id = $decision->id;
$consolidated = $overview->consolidated;
?>
<div class="panel panel-default complaints">
    <?php $this->load->view('complaints/partials/heading', ['heading' => complaints_t('complaints_title_decision', ['buy' => $overview->buy->number])]); ?>
    <div class="panel-body">
        <?php $this->load->view('complaints/partials/errors', ['error' => null]); ?>
        <?php $this->load->view('complaints/partials/decision_summary', ['overview' => $overview]); ?>

        <?php if ($overview->allows(DecisionAction::Release)) : ?>
            <p>
                <button type="button" class="btn btn-default btn-sm"
                        data-complaints-post="<?= complaints_e(complaints_url('complaint_review/release/' . $id)) ?>"
                        data-confirm="<?= complaints_te('complaints_confirm_release') ?>"><?= complaints_te('complaints_release') ?></button>
            </p>
        <?php endif; ?>

        <?php if ($overview->allows(DecisionAction::ChooseMethod)) : ?>
            <div class="panel panel-default">
                <div class="panel-heading"><b><?= complaints_te('complaints_review_method') ?></b></div>
                <div class="panel-body">
                    <form method="post" action="<?= complaints_e(complaints_url('complaint_review/choose_method/' . $id)) ?>" data-complaints-form
                          <?= $decision->reviewMethod === null ? '' : 'data-confirm="' . complaints_te('complaints_confirm_change_method') . '"' ?>>
                        <?php foreach (ReviewMethod::cases() as $method) : ?>
                            <div class="radio">
                                <label>
                                    <input type="radio" name="method" value="<?= complaints_e($method->value) ?>"<?= $decision->reviewMethod === $method ? ' checked' : '' ?>>
                                    <?= complaints_te('complaints_review_method_' . $method->value) ?>
                                    <small class="text-muted"><?= complaints_te('complaints_review_method_hint_' . $method->value) ?></small>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary btn-sm"><?= complaints_te('complaints_apply') ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <h4><?= complaints_te('complaints_lots') ?></h4>
        <table class="table table-bordered table-hover table-striped">
            <tr>
                <th><?= complaints_te('complaints_lot_number') ?></th>
                <th><?= complaints_te('complaints_lot_name') ?></th>
                <th><?= complaints_te('complaints_complaints_count') ?></th>
                <th><?= complaints_te('complaints_answered') ?></th>
                <th><?= complaints_te('complaints_verdict_satisfied') ?></th>
                <th><?= complaints_te('complaints_verdict_rejected') ?></th>
                <th><?= complaints_te('complaints_decision_type') ?></th>
                <th><?= complaints_te('complaints_execution') ?></th>
                <th></th>
            </tr>
            <?php foreach ($overview->lots as $row) : ?>
                <tr class="<?= $decision->reviewMethod !== null && !$row->isCovered() ? 'warning' : '' ?>">
                    <td><?= complaints_e($row->buyLotNumber) ?></td>
                    <td><?= complaints_text($row->buyLotName) ?></td>
                    <td class="text-right"><?= (int) $row->complaintsCount ?></td>
                    <td class="text-right"><?= (int) $row->answeredCount ?> / <?= (int) $row->complaintsCount ?></td>
                    <td class="text-right"><?= (int) $row->satisfiedCount ?></td>
                    <td class="text-right"><?= (int) $row->rejectedCount ?></td>
                    <td><?= complaints_label('decision_type', $row->lot->decisionType) ?></td>
                    <td><?= complaints_label('lot_execution', $row->lot->executionStatus) ?></td>
                    <td>
                        <a class="btn btn-<?= $overview->allows(DecisionAction::EditAnswers) ? 'primary' : 'default' ?> btn-xs"
                           href="<?= complaints_e(complaints_url('complaint_review/lot/' . $row->lot->id)) ?>">
                            <?= complaints_te($overview->allows(DecisionAction::EditAnswers) ? 'complaints_review_lot' : 'complaints_open') ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <?php if ($overview->gaps !== []) : ?>
            <div class="alert alert-warning">
                <b><?= complaints_te('complaints_gaps') ?></b>
                <ul>
                    <?php foreach ($overview->gaps as $gap) : ?>
                        <li>
                            <?= complaints_te('complaints_gap', ['lot' => $gap->buyLotNumber, 'covered' => $gap->covered, 'total' => $gap->total]) ?>
                            <?php if ($gap->missingDecisionType) : ?><?= complaints_te('complaints_gap_type') ?><?php endif; ?>
                            <?php if ($gap->decisionTypeMismatch) : ?><?= complaints_te('complaints_gap_mismatch') ?><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="panel panel-default">
            <div class="panel-heading"><b><?= complaints_te('complaints_consolidated') ?></b></div>
            <div class="panel-body">
                <?php $this->load->view('complaints/partials/document', ['document' => $consolidated]); ?>
                <br>
                <?php $this->load->view('complaints/partials/signatures', ['signatures' => $overview->signatures, 'downloadable' => true]); ?>

                <?php if ($overview->allows(DecisionAction::FormConsolidated)) : ?>
                    <button type="button" class="btn btn-default"
                            data-complaints-post="<?= complaints_e(complaints_url('complaint_review/form_consolidated/' . $id)) ?>">
                        <span class="glyphicon glyphicon-file"></span> <?= complaints_te($consolidated === null ? 'complaints_form_consolidated' : 'complaints_reform_consolidated') ?>
                    </button>
                <?php endif; ?>

                <?php if ($overview->allows(DecisionAction::Send) && $consolidated !== null) : ?>
                    <form id="complaints-route-form" class="form-horizontal complaints-route" onsubmit="return false;">
                        <div class="form-group">
                            <label class="col-sm-3 control-label" for="complaints-coordinator"><?= complaints_te('complaints_coordinator') ?></label>
                            <div class="col-sm-6">
                                <select class="form-control" id="complaints-coordinator" name="coordinator_user_id">
                                    <option value=""><?= complaints_te('complaints_without_coordination') ?></option>
                                    <?php foreach ($overview->coordinatorCandidates as $candidate) : ?>
                                        <option value="<?= (int) $candidate->id ?>"><?= complaints_e($candidate->fullName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label" for="complaints-approver"><?= complaints_te('complaints_approver') ?></label>
                            <div class="col-sm-6">
                                <select class="form-control" id="complaints-approver" name="approver_user_id" required>
                                    <?php foreach ($overview->approverCandidates as $candidate) : ?>
                                        <option value="<?= (int) $candidate->id ?>"><?= complaints_e($candidate->fullName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-sm-offset-3 col-sm-6">
                                <button type="button" class="btn btn-success"
                                        data-complaints-sign="<?= complaints_e(complaints_url('complaint_review/send/' . $id)) ?>"
                                        data-source="<?= complaints_e(complaints_url('complaint_files/source/' . $consolidated->document->id)) ?>"
                                        data-form="complaints-route-form"
                                        data-confirm="<?= complaints_te('complaints_confirm_send') ?>">
                                    <span class="glyphicon glyphicon-pencil"></span> <?= complaints_te('complaints_sign_and_send') ?>
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($overview->allows(DecisionAction::Coordinate) && $consolidated !== null) : ?>
                    <button type="button" class="btn btn-success"
                            data-complaints-sign="<?= complaints_e(complaints_url('complaint_review/coordinate/' . $id)) ?>"
                            data-source="<?= complaints_e(complaints_url('complaint_files/source/' . $consolidated->document->id)) ?>"
                            data-confirm="<?= complaints_te('complaints_confirm_coordinate') ?>">
                        <span class="glyphicon glyphicon-pencil"></span> <?= complaints_te('complaints_coordinate') ?>
                    </button>
                <?php endif; ?>
                <?php if ($overview->allows(DecisionAction::Approve) && $consolidated !== null) : ?>
                    <button type="button" class="btn btn-success"
                            data-complaints-sign="<?= complaints_e(complaints_url('complaint_review/approve/' . $id)) ?>"
                            data-source="<?= complaints_e(complaints_url('complaint_files/source/' . $consolidated->document->id)) ?>"
                            data-confirm="<?= complaints_te('complaints_confirm_approve') ?>">
                        <span class="glyphicon glyphicon-pencil"></span> <?= complaints_te('complaints_approve') ?>
                    </button>
                <?php endif; ?>
                <?php if ($overview->allows(DecisionAction::Recall)) : ?>
                    <button type="button" class="btn btn-warning"
                            data-complaints-post="<?= complaints_e(complaints_url('complaint_review/recall/' . $id)) ?>"
                            data-confirm="<?= complaints_te('complaints_confirm_recall') ?>"><?= complaints_te('complaints_recall') ?></button>
                <?php endif; ?>
                <?php if ($overview->allows(DecisionAction::Reopen)) : ?>
                    <button type="button" class="btn btn-default"
                            data-complaints-post="<?= complaints_e(complaints_url('complaint_review/reopen/' . $id)) ?>"><?= complaints_te('complaints_reopen') ?></button>
                <?php endif; ?>
            </div>
        </div>
        <a href="<?= complaints_e(complaints_url('complaint_review/index')) ?>" class="btn btn-default"><?= complaints_te('complaints_back') ?></a>
    </div>
</div>
<?php $this->load->view('complaints/partials/scripts'); ?>
