<?php

defined('BASEPATH') or exit('No direct script access allowed');

use Complaints\Application\BuyComplaint\DecisionAction;

/**
 * Утверждённое решение у организатора: основание и исполнение по лотам.
 *
 * @var Complaints\Application\BuyComplaint\DecisionOverview $overview
 */
$decision = $overview->decision;
$id = $decision->id;
?>
<div class="panel panel-default complaints">
    <?php $this->load->view('complaints/partials/heading', ['heading' => complaints_t('complaints_title_decision', ['buy' => $overview->buy->number])]); ?>
    <div class="panel-body">
        <?php $this->load->view('complaints/partials/errors', ['error' => null]); ?>
        <?php $this->load->view('complaints/partials/decision_summary', ['overview' => $overview]); ?>

        <h4><?= complaints_te('complaints_lots') ?></h4>
        <table class="table table-bordered table-hover table-striped">
            <tr>
                <th><?= complaints_te('complaints_lot_number') ?></th>
                <th><?= complaints_te('complaints_lot_name') ?></th>
                <th><?= complaints_te('complaints_complaints_count') ?></th>
                <th><?= complaints_te('complaints_decision_type') ?></th>
                <th><?= complaints_te('complaints_execution') ?></th>
                <th><?= complaints_te('complaints_executed_at') ?></th>
            </tr>
            <?php foreach ($overview->lots as $row) : ?>
                <tr>
                    <td><?= complaints_e($row->buyLotNumber) ?></td>
                    <td><?= complaints_text($row->buyLotName) ?></td>
                    <td class="text-right"><?= (int) $row->complaintsCount ?></td>
                    <td><?= complaints_label('decision_type', $row->lot->decisionType) ?></td>
                    <td><?= complaints_label('lot_execution', $row->lot->executionStatus) ?></td>
                    <td><?= complaints_moment($row->lot->executedAt) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <div class="panel panel-default">
            <div class="panel-heading"><b><?= complaints_te('complaints_basis') ?></b></div>
            <div class="panel-body">
                <?php $this->load->view('complaints/partials/document', ['document' => $overview->consolidated]); ?>
                <br>
                <?php $this->load->view('complaints/partials/signatures', ['signatures' => $overview->signatures, 'downloadable' => true]); ?>
            </div>
        </div>

        <?php if ($overview->allows(DecisionAction::CancelLots)) : ?>
            <button type="button" class="btn btn-danger"
                    data-complaints-post="<?= complaints_e(complaints_url('complaint_execution/cancel_lots/' . $id)) ?>"
                    data-confirm="<?= complaints_te('complaints_confirm_cancel_lots') ?>">
                <?= complaints_te('complaints_cancel_lots') ?>
            </button>
        <?php endif; ?>
        <?php if ($overview->allows(DecisionAction::StartRevision)) : ?>
            <button type="button" class="btn btn-warning"
                    data-complaints-post="<?= complaints_e(complaints_url('complaint_execution/start_revision/' . $id)) ?>"
                    data-confirm="<?= complaints_te('complaints_confirm_start_revision') ?>">
                <?= complaints_te('complaints_start_revision') ?>
            </button>
        <?php endif; ?>
        <a href="<?= complaints_e(complaints_url('complaint_execution/index')) ?>" class="btn btn-default"><?= complaints_te('complaints_back') ?></a>
    </div>
</div>
<?php $this->load->view('complaints/partials/scripts'); ?>
