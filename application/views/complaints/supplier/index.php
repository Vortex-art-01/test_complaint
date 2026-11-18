<?php

defined('BASEPATH') or exit('No direct script access allowed');

use Complaints\Application\AppComplaint\ComplaintAction;

/**
 * Реестр жалоб организации-поставщика.
 *
 * @var Complaints\Application\Shared\Page $page элементы — ComplaintRegistryItem
 */
?>
<div class="panel panel-default complaints">
    <?php $this->load->view('complaints/partials/heading', ['heading' => complaints_t('complaints_title_supplier_registry')]); ?>
    <div class="panel-body">
        <?php $this->load->view('complaints/partials/errors', ['error' => null]); ?>
        <p>
            <a class="btn btn-success" href="<?= complaints_e(complaints_url('complaint_supplier/create')) ?>">
                <span class="glyphicon glyphicon-plus"></span> <?= complaints_te('complaints_create') ?>
            </a>
        </p>
        <?php if ($page->items === []) : ?>
            <p class="text-muted"><?= complaints_te('complaints_registry_empty') ?></p>
        <?php else : ?>
            <table class="table table-bordered table-hover table-striped">
                <tr>
                    <th><?= complaints_te('complaints_complaint_number') ?></th>
                    <th><?= complaints_te('complaints_buy') ?></th>
                    <th><?= complaints_te('complaints_lot') ?></th>
                    <th><?= complaints_te('complaints_status') ?></th>
                    <th><?= complaints_te('complaints_head_signed_at') ?></th>
                    <th><?= complaints_te('complaints_result') ?></th>
                    <th><?= complaints_te('complaints_actions') ?></th>
                </tr>
                <?php foreach ($page->items as $item) : ?>
                    <?php $complaint = $item->row->complaint; ?>
                    <tr>
                        <td>
                            <a href="<?= complaints_e(complaints_url('complaint_supplier/show/' . $complaint->id)) ?>"><?= complaints_e($complaint->number->value) ?></a>
                        </td>
                        <td><?= complaints_e($item->row->buyNumber) ?><br><small><?= complaints_text($item->row->buyName) ?></small></td>
                        <td><?= complaints_e($item->row->lotNumber) ?></td>
                        <td>
                            <?= complaints_label('complaint_status', $complaint->status) ?>
                            <?php if ($item->appealOpen) : ?>
                                <div><small class="text-muted"><?= complaints_te('complaints_appeal_until', ['date' => complaints_moment($complaint->appealPeriod->displayEndsAt())]) ?></small></div>
                            <?php endif; ?>
                        </td>
                        <td><?= complaints_moment($item->row->headSignedAt) ?></td>
                        <td><?= complaints_label('verdict', $item->row->verdict) ?></td>
                        <td class="text-nowrap">
                            <?php if ($item->allows(ComplaintAction::Edit)) : ?>
                                <a class="btn btn-primary btn-xs" href="<?= complaints_e(complaints_url('complaint_supplier/edit/' . $complaint->id)) ?>"><?= complaints_te('complaints_edit') ?></a>
                            <?php endif; ?>
                            <?php if ($item->allows(ComplaintAction::Submit)) : ?>
                                <button type="button" class="btn btn-success btn-xs"
                                        data-complaints-post="<?= complaints_e(complaints_url('complaint_supplier/submit/' . $complaint->id)) ?>"
                                        data-confirm="<?= complaints_te('complaints_confirm_submit') ?>"><?= complaints_te('complaints_submit') ?></button>
                            <?php endif; ?>
                            <?php if ($item->allows(ComplaintAction::Withdraw)) : ?>
                                <button type="button" class="btn btn-warning btn-xs"
                                        data-complaints-post="<?= complaints_e(complaints_url('complaint_supplier/withdraw/' . $complaint->id)) ?>"
                                        data-confirm="<?= complaints_te('complaints_confirm_withdraw') ?>"><?= complaints_te('complaints_withdraw') ?></button>
                            <?php endif; ?>
                            <?php if ($item->allows(ComplaintAction::Delete)) : ?>
                                <button type="button" class="btn btn-danger btn-xs"
                                        data-complaints-post="<?= complaints_e(complaints_url('complaint_supplier/delete/' . $complaint->id)) ?>"
                                        data-confirm="<?= complaints_te('complaints_confirm_delete') ?>"><?= complaints_te('complaints_delete') ?></button>
                            <?php endif; ?>
                            <a class="btn btn-default btn-xs" href="<?= complaints_e(complaints_url('complaint_supplier/show/' . $complaint->id)) ?>"><?= complaints_te('complaints_open') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php $this->load->view('complaints/partials/pagination', ['page' => $page, 'path' => 'complaint_supplier/index', 'query' => []]); ?>
        <?php endif; ?>
    </div>
</div>
<?php $this->load->view('complaints/partials/scripts'); ?>
