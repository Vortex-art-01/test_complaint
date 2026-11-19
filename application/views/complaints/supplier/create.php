<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Подача жалобы: шаг 1 — закупка и лот своей заявки, шаг 2 — руководитель-подписант.
 *
 * @var string                                              $number
 * @var Complaints\Application\AppComplaint\ComplainableBuy|null $buy
 * @var string|null                                         $error
 * @var list<Complaints\Application\Port\UserCard>          $heads
 */
?>
<div class="panel panel-default complaints">
    <?php $this->load->view('complaints/partials/heading', ['heading' => complaints_t('complaints_title_create')]); ?>
    <div class="panel-body">
        <?php $this->load->view('complaints/partials/errors', ['error' => $error]); ?>

        <div class="panel panel-default">
            <div class="panel-heading"><b><?= complaints_te('complaints_step_buy') ?></b></div>
            <div class="panel-body">
                <form method="get" action="<?= complaints_e(complaints_url('complaint_supplier/create')) ?>" class="form-inline">
                    <div class="form-group">
                        <label for="complaints-buy-number"><?= complaints_te('complaints_buy_number') ?></label>
                        <input type="text" class="form-control" id="complaints-buy-number" name="number" maxlength="50"
                               value="<?= complaints_e($number) ?>" placeholder="125407-1" required>
                    </div>
                    <button type="submit" class="btn btn-success"><?= complaints_te('complaints_find') ?></button>
                </form>
            </div>
        </div>

        <?php if ($buy !== null) : ?>
            <form method="post" action="<?= complaints_e(complaints_url('complaint_supplier/create_draft')) ?>" data-complaints-form>
                <input type="hidden" name="buy_id" value="<?= (int) $buy->buy->id ?>">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <b><?= complaints_te('complaints_step_lot') ?></b>:
                        <?= complaints_e($buy->buy->number) ?> — <?= complaints_text($buy->buy->name) ?>
                    </div>
                    <?php if ($buy->lots === []) : ?>
                        <div class="panel-body text-muted"><?= complaints_te('complaints_no_open_lots') ?></div>
                    <?php else : ?>
                        <table class="table table-bordered table-hover">
                            <tr>
                                <th></th>
                                <th><?= complaints_te('complaints_lot_number') ?></th>
                                <th><?= complaints_te('complaints_lot_name') ?></th>
                                <th><?= complaints_te('complaints_appeal_period') ?></th>
                            </tr>
                            <?php foreach ($buy->lots as $index => $lot) : ?>
                                <tr>
                                    <td><input type="radio" name="app_lot_id" value="<?= (int) $lot->appLot->id ?>" id="complaints-lot-<?= (int) $lot->appLot->id ?>"<?= $index === 0 ? ' checked' : '' ?>></td>
                                    <td><label for="complaints-lot-<?= (int) $lot->appLot->id ?>"><?= complaints_e($lot->buyLot->number) ?></label></td>
                                    <td><?= complaints_text($lot->buyLot->name) ?></td>
                                    <td><?= complaints_moment($lot->appealPeriod->startsAt) ?> — <?= complaints_moment($lot->appealPeriod->displayEndsAt()) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>

                <?php if ($buy->lots !== []) : ?>
                    <div class="panel panel-default">
                        <div class="panel-heading"><b><?= complaints_te('complaints_step_head') ?></b></div>
                        <div class="panel-body">
                            <?php if ($heads === []) : ?>
                                <p class="text-danger"><?= complaints_te('complaints_error_head_not_found') ?></p>
                            <?php else : ?>
                                <div class="form-group">
                                    <label for="complaints-head"><?= complaints_te('complaints_head') ?></label>
                                    <select class="form-control" id="complaints-head" name="head_user_id" required>
                                        <?php foreach ($heads as $head) : ?>
                                            <option value="<?= (int) $head->id ?>"><?= complaints_e($head->fullName) ?>, <?= complaints_e($head->position) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success"><?= complaints_te('complaints_create_draft') ?></button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
        <a href="<?= complaints_e(complaints_url('complaint_supplier/index')) ?>" class="btn btn-default"><?= complaints_te('complaints_back') ?></a>
    </div>
</div>
<?php $this->load->view('complaints/partials/scripts'); ?>
