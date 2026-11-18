<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Черновик жалобы: суть, телефон, лот, руководитель-подписант и файлы.
 *
 * @var Complaints\Application\AppComplaint\ComplaintCard        $card
 * @var Complaints\Application\AppComplaint\ComplainableBuy|null $buy  лоты, на которые можно перенести жалобу
 * @var list<Complaints\Application\Port\UserCard>               $heads
 * @var int                                                      $descriptionMaxLength
 */
$complaint = $card->complaint;
$id = $complaint->id;
?>
<div class="panel panel-default complaints">
    <?php $this->load->view('complaints/partials/heading', ['heading' => complaints_t('complaints_title_edit', ['number' => $complaint->number->value])]); ?>
    <div class="panel-body">
        <?php $this->load->view('complaints/partials/errors', ['error' => $card->appealOpen ? null : complaints_t('complaints_error_appeal_closed')]); ?>
        <p><?= complaints_label('complaint_status', $complaint->status) ?></p>

        <?php $this->load->view('complaints/partials/requisites', ['requisites' => $card->requisites, 'complaint' => $complaint]); ?>

        <div class="row">
            <?php if ($buy !== null && count($buy->lots) > 1) : ?>
                <div class="col-md-6">
                    <form method="post" class="form-inline" action="<?= complaints_e(complaints_url('complaint_supplier/change_lot/' . $id)) ?>" data-complaints-form>
                        <div class="form-group">
                            <label for="complaints-lot"><?= complaints_te('complaints_change_lot') ?></label>
                            <select class="form-control" id="complaints-lot" name="app_lot_id">
                                <?php foreach ($buy->lots as $lot) : ?>
                                    <option value="<?= (int) $lot->appLot->id ?>"<?= $lot->appLot->id === $card->lot->appLotId ? ' selected' : '' ?>>
                                        <?= complaints_e($lot->buyLot->number) ?> — <?= complaints_text($lot->buyLot->name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-default btn-sm"><?= complaints_te('complaints_apply') ?></button>
                    </form>
                </div>
            <?php endif; ?>
            <?php if (count($heads) > 1) : ?>
                <div class="col-md-6">
                    <form method="post" class="form-inline" action="<?= complaints_e(complaints_url('complaint_supplier/change_head/' . $id)) ?>" data-complaints-form>
                        <div class="form-group">
                            <label for="complaints-head"><?= complaints_te('complaints_head') ?></label>
                            <select class="form-control" id="complaints-head" name="head_user_id">
                                <?php foreach ($heads as $head) : ?>
                                    <option value="<?= (int) $head->id ?>"<?= $head->id === $complaint->headUserId ? ' selected' : '' ?>><?= complaints_e($head->fullName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-default btn-sm"><?= complaints_te('complaints_apply') ?></button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <br>

        <form method="post" id="complaints-draft-form" action="<?= complaints_e(complaints_url('complaint_supplier/save/' . $id)) ?>" data-complaints-form>
            <div class="form-group">
                <label for="complaints-phone"><?= complaints_te('complaints_contact_phone') ?></label>
                <input type="tel" class="form-control" id="complaints-phone" name="contact_phone" maxlength="30"
                       value="<?= complaints_e((string) $complaint->contactPhone) ?>" placeholder="+7 700 000 00 00">
            </div>
            <div class="form-group">
                <label for="complaints-description"><?= complaints_te('complaints_description') ?></label>
                <textarea class="form-control" id="complaints-description" name="description" rows="6"
                          data-maxlength="<?= (int) $descriptionMaxLength ?>"><?= complaints_e((string) $complaint->description) ?></textarea>
                <p class="help-block complaints-counter" data-for="complaints-description"></p>
            </div>
            <button type="submit" class="btn btn-primary"><?= complaints_te('complaints_save') ?></button>
        </form>

        <h4><?= complaints_te('complaints_attachments') ?></h4>
        <?php $this->load->view('complaints/partials/attachments', ['attachments' => $card->attachments, 'removePath' => 'complaint_supplier/detach/' . $id . '/']); ?>
        <?php $this->load->view('complaints/partials/upload', ['action' => complaints_url('complaint_supplier/attach/' . $id), 'fields' => []]); ?>

        <hr>
        <button type="button" class="btn btn-success"
                data-complaints-post="<?= complaints_e(complaints_url('complaint_supplier/send_for_signature/' . $id)) ?>"
                data-save-form="complaints-draft-form"
                data-confirm="<?= complaints_te('complaints_confirm_send_for_signature') ?>">
            <span class="glyphicon glyphicon-send"></span> <?= complaints_te('complaints_send_for_signature') ?>
        </button>
        <button type="button" class="btn btn-danger"
                data-complaints-post="<?= complaints_e(complaints_url('complaint_supplier/delete/' . $id)) ?>"
                data-confirm="<?= complaints_te('complaints_confirm_delete') ?>"><?= complaints_te('complaints_delete') ?></button>
        <a href="<?= complaints_e(complaints_url('complaint_supplier/index')) ?>" class="btn btn-default"><?= complaints_te('complaints_back') ?></a>
    </div>
</div>
<?php $this->load->view('complaints/partials/scripts'); ?>
