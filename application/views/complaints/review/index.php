<?php

defined('BASEPATH') or exit('No direct script access allowed');

use Complaints\Domain\BuyComplaint\BuyComplaintStatus;

/**
 * Реестр решений уполномоченного органа.
 *
 * @var Complaints\Application\Shared\Page                $page элементы — DecisionRegistryItem
 * @var Complaints\Domain\BuyComplaint\BuyComplaintStatus|null $status
 * @var string                                            $number
 */
?>
<div class="panel panel-default complaints">
    <?php $this->load->view('complaints/partials/heading', ['heading' => complaints_t('complaints_title_review_registry')]); ?>
    <div class="panel-body">
        <?php $this->load->view('complaints/partials/errors', ['error' => null]); ?>
        <div class="panel panel-default">
            <div class="panel-heading"><b><?= complaints_te('complaints_search') ?></b></div>
            <div class="panel-body">
                <form method="get" action="<?= complaints_e(complaints_url('complaint_review/index')) ?>" class="form-inline">
                    <div class="form-group">
                        <label for="complaints-filter-number"><?= complaints_te('complaints_buy_number') ?></label>
                        <input type="text" class="form-control" id="complaints-filter-number" name="number" value="<?= complaints_e($number) ?>">
                    </div>
                    <div class="form-group">
                        <label for="complaints-filter-status"><?= complaints_te('complaints_status') ?></label>
                        <select class="form-control" id="complaints-filter-status" name="status">
                            <option value=""><?= complaints_te('complaints_all') ?></option>
                            <?php foreach (BuyComplaintStatus::cases() as $case) : ?>
                                <option value="<?= complaints_e($case->value) ?>"<?= $case === $status ? ' selected' : '' ?>><?= complaints_te('complaints_decision_status_' . $case->value) ?></option>
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
                    <th><?= complaints_te('complaints_organizer') ?></th>
                    <th><?= complaints_te('complaints_complaints_count') ?></th>
                    <th><?= complaints_te('complaints_review_period') ?></th>
                    <th><?= complaints_te('complaints_status') ?></th>
                    <th><?= complaints_te('complaints_reviewer') ?></th>
                    <th><?= complaints_te('complaints_decision_types') ?></th>
                    <th></th>
                </tr>
                <?php foreach ($page->items as $item) : ?>
                    <?php $decision = $item->row->decision; ?>
                    <tr>
                        <td><?= complaints_e($item->row->buyNumber) ?><br><small><?= complaints_text($item->row->buyName) ?></small></td>
                        <td><?= complaints_text($item->organizer->name) ?></td>
                        <td class="text-right"><?= (int) $item->row->complaintsCount ?></td>
                        <td><?= complaints_moment($decision->reviewPeriod->startsAt) ?> — <?= complaints_moment($decision->reviewPeriod->displayEndsAt()) ?></td>
                        <td>
                            <?= complaints_label('decision_status', $decision->status) ?>
                            <?php if ($decision->decisionNumber !== null) : ?>
                                <div><small><?= complaints_te('complaints_decision_number_date', ['number' => $decision->decisionNumber, 'date' => complaints_moment($decision->approvedAt)]) ?></small></div>
                            <?php endif; ?>
                        </td>
                        <td><?= $item->reviewerName === '' ? '<span class="text-muted">' . complaints_te('complaints_not_assigned') . '</span>' : complaints_e($item->reviewerName) ?></td>
                        <td>
                            <?php foreach ($item->row->decisionTypes as $type) : ?>
                                <?= complaints_label('decision_type', $type) ?>
                            <?php endforeach; ?>
                        </td>
                        <td><a class="btn btn-default btn-xs" href="<?= complaints_e(complaints_url('complaint_review/show/' . $decision->id)) ?>"><?= complaints_te('complaints_open') ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php $this->load->view('complaints/partials/pagination', [
                'page' => $page,
                'path' => 'complaint_review/index',
                'query' => ['number' => $number, 'status' => $status?->value],
            ]); ?>
        <?php endif; ?>
    </div>
</div>
<?php $this->load->view('complaints/partials/scripts'); ?>
