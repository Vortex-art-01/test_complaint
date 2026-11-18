<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Сведения о решении: закупка, орган, сроки, маршрут, номер и исполнение.
 *
 * @var Complaints\Application\BuyComplaint\DecisionOverview $overview
 */
$decision = $overview->decision;
?>
<div class="row">
    <div class="col-md-6">
        <table class="table table-bordered table-condensed">
            <tr><th class="col-md-5"><?= complaints_te('complaints_buy_number') ?></th><td><?= complaints_e($overview->buy->number) ?></td></tr>
            <tr><th><?= complaints_te('complaints_buy_name') ?></th><td><?= complaints_text($overview->buy->name) ?></td></tr>
            <tr><th><?= complaints_te('complaints_organizer') ?></th><td><?= complaints_text($overview->organizer->name) ?></td></tr>
            <tr><th><?= complaints_te('complaints_authority') ?></th><td><?= complaints_text($overview->authority->name) ?></td></tr>
            <tr><th><?= complaints_te('complaints_complaints_count') ?></th><td><?= (int) $overview->complaintsCount() ?></td></tr>
        </table>
    </div>
    <div class="col-md-6">
        <table class="table table-bordered table-condensed">
            <tr><th class="col-md-5"><?= complaints_te('complaints_status') ?></th><td><?= complaints_label('decision_status', $decision->status) ?></td></tr>
            <tr>
                <th><?= complaints_te('complaints_review_period') ?></th>
                <td><?= complaints_moment($decision->reviewPeriod->startsAt) ?> — <?= complaints_moment($decision->reviewPeriod->displayEndsAt()) ?></td>
            </tr>
            <tr><th><?= complaints_te('complaints_review_method') ?></th><td><?= complaints_label('review_method', $decision->reviewMethod) ?></td></tr>
            <tr><th><?= complaints_te('complaints_reviewer') ?></th><td><?= $overview->reviewer === null ? complaints_te('complaints_not_assigned') : complaints_e($overview->reviewer->fullName) ?></td></tr>
            <?php if ($overview->coordinator !== null) : ?>
                <tr><th><?= complaints_te('complaints_coordinator') ?></th><td><?= complaints_e($overview->coordinator->fullName) ?></td></tr>
            <?php endif; ?>
            <?php if ($overview->approver !== null) : ?>
                <tr><th><?= complaints_te('complaints_approver') ?></th><td><?= complaints_e($overview->approver->fullName) ?></td></tr>
            <?php endif; ?>
            <?php if ($decision->decisionNumber !== null) : ?>
                <tr>
                    <th><?= complaints_te('complaints_decision') ?></th>
                    <td><?= complaints_te('complaints_decision_number_date', ['number' => $decision->decisionNumber, 'date' => complaints_moment($decision->approvedAt)]) ?></td>
                </tr>
                <tr>
                    <th><?= complaints_te('complaints_execution') ?></th>
                    <td>
                        <?= complaints_label('execution_status', $decision->executionStatus) ?>
                        <?php if ($decision->executionPeriod !== null) : ?>
                            <div><small><?= complaints_te('complaints_execution_until', ['date' => complaints_moment($decision->executionPeriod->displayEndsAt())]) ?></small></div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</div>
