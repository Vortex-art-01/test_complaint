<?php

defined('BASEPATH') or exit('No direct script access allowed');

use Complaints\Domain\BuyComplaint\ExecutionStatus;

/**
 * Реестр утверждённых решений по закупкам организатора.
 *
 * @var Complaints\Application\Shared\Page              $page элементы — DecisionRegistryItem
 * @var Complaints\Domain\BuyComplaint\ExecutionStatus|null $status
 * @var string                                          $number
 */
?>
<div class="panel panel-default complaints">
    <?php $this->load->view('complaints/partials/heading', ['heading' => complaints_t('complaints_title_execution_registry')]); ?>
    <div class="panel-body">
        <?php $this->load->view('complaints/partials/errors', ['error' => null]); ?>
        <div class="panel panel-default">
            <div class="panel-heading"><b><?= complaints_te('complaints_search') ?></b></div>
            <div class="panel-body">
                <form method="get" action="<?= complaints_e(complaints_url('complaint_execution/index')) ?>" class="form-inline">
                    <div class="form-group">
                        <label for="complaints-filter-number"><?= complaints_te('complaints_buy_number') ?></label>
                        <input type="text" class="form-control" id="complaints-filter-number" name="number" value="<?= complaints_e($number) ?>">
                    </div>
                    <div class="form-group">
                        <label for="complaints-filter-status"><?= complaints_te('complaints_execution') ?></label>
                        <select class="form-control" id="complaints-filter-status" name="status">
                            <option value=""><?= complaints_te('complaints_all') ?></option>
                            <?php foreach (ExecutionStatus::cases() as $case) : ?>
                                <option value="<?= complaints_e($case->value) ?>"<?= $case === $status ? ' selected' : '' ?>><?= complaints_te('complaints_execution_status_' . $case->value) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-success" type="submit"><?= complaints_te('complaints_find') ?></button>
                </form>
            </div>
        </div>

        <?php if ($page->items === []) : ?>
            <p class="text-muted"><?= complaints_te('complaints_registry_empty') ?></p>
        <?php else : ?>
            <table class="table table-bordered table-hover table-striped">
                <tr>
                    <th><?= complaints_te('complaints_buy') ?></th>
                    <th><?= complaints_te('complaints_buy_status') ?></th>
                    <th><?= complaints_te('complaints_decision') ?></th>
                    <th><?= complaints_te('complaints_authority') ?></th>
                    <th><?= complaints_te('complaints_decision_types') ?></th>
                    <th><?= complaints_te('complaints_execution') ?></th>
                    <th></th>
                </tr>
                <?php foreach ($page->items as $item) : ?>
                    <?php $decision = $item->row->decision; ?>
                    <tr>
                        <td><?= complaints_e($item->row->buyNumber) ?><br><small><?= complaints_text($item->row->buyName) ?></small></td>
                        <td><?= complaints_e($item->procurementStatusName) ?></td>
                        <td><?= complaints_te('complaints_decision_number_date', ['number' => $decision->id, 'date' => complaints_moment($decision->approvedAt)]) ?></td>
                        <td><?= complaints_text($item->authority->name) ?></td>
                        <td>
                            <?php foreach ($item->row->decisionTypes as $type) : ?>
                                <?= complaints_label('decision_type', $type) ?>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?= complaints_label('execution_status', $decision->executionStatus) ?>
                            <?php if ($decision->executionPeriod !== null) : ?>
                                <div><small><?= complaints_te('complaints_execution_until', ['date' => complaints_moment($decision->executionPeriod->displayEndsAt())]) ?></small></div>
                            <?php endif; ?>
                        </td>
                        <td><a class="btn btn-default btn-xs" href="<?= complaints_e(complaints_url('complaint_execution/show/' . $decision->id)) ?>"><?= complaints_te('complaints_open') ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php $this->load->view('complaints/partials/pagination', [
                'page' => $page,
                'path' => 'complaint_execution/index',
                'query' => ['number' => $number, 'status' => $status?->value],
            ]); ?>
        <?php endif; ?>
    </div>
</div>
<?php $this->load->view('complaints/partials/scripts'); ?>
