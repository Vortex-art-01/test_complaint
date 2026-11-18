<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Реквизиты жалобы: закупка, лот, организатор, заявитель и представитель.
 *
 * @var Complaints\Application\AppComplaint\Requisites $requisites
 * @var Complaints\Domain\AppComplaint\AppComplaint    $complaint
 */
?>
<div class="row">
    <div class="col-md-6">
        <table class="table table-bordered table-condensed complaints-requisites">
            <tr><th><?= complaints_te('complaints_buy_number') ?></th><td><?= complaints_e($requisites->buyNumber) ?></td></tr>
            <tr><th><?= complaints_te('complaints_buy_name') ?></th><td><?= complaints_text($requisites->buyName) ?></td></tr>
            <tr><th><?= complaints_te('complaints_buy_published_at') ?></th><td><?= complaints_moment($requisites->buyPublishedAt) ?></td></tr>
            <tr><th><?= complaints_te('complaints_lot_number') ?></th><td><?= complaints_e($requisites->lotNumber) ?></td></tr>
            <tr><th><?= complaints_te('complaints_lot_name') ?></th><td><?= complaints_text($requisites->lotName) ?></td></tr>
            <tr><th><?= complaints_te('complaints_protocol_published_at') ?></th><td><?= complaints_moment($complaint->protocolPublishedAt) ?></td></tr>
            <tr>
                <th><?= complaints_te('complaints_appeal_period') ?></th>
                <td><?= complaints_moment($complaint->appealPeriod->startsAt) ?> — <?= complaints_moment($complaint->appealPeriod->displayEndsAt()) ?></td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <table class="table table-bordered table-condensed complaints-requisites">
            <tr><th><?= complaints_te('complaints_organizer') ?></th><td><?= complaints_text($requisites->organizerName) ?>, <?= complaints_te('complaints_bin') ?> <?= complaints_e($requisites->organizerBin) ?></td></tr>
            <tr><th><?= complaints_te('complaints_supplier') ?></th><td><?= complaints_text($requisites->supplierName) ?>, <?= complaints_te('complaints_bin') ?> <?= complaints_e($requisites->supplierBin) ?></td></tr>
            <tr><th><?= complaints_te('complaints_legal_address') ?></th><td><?= complaints_text($requisites->supplierAddress) ?></td></tr>
            <tr><th><?= complaints_te('complaints_representative') ?></th><td><?= complaints_e($requisites->representativeName) ?>, <?= complaints_te('complaints_iin') ?> <?= complaints_e($requisites->representativeIin) ?></td></tr>
            <tr><th><?= complaints_te('complaints_email') ?></th><td><?= complaints_e($requisites->representativeEmail) ?></td></tr>
            <tr><th><?= complaints_te('complaints_head') ?></th><td><?= complaints_e($requisites->headName) ?>, <?= complaints_e($requisites->headPosition) ?></td></tr>
        </table>
    </div>
</div>
