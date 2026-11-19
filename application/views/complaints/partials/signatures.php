<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Подписи ЭЦП действующей версии документа: поставленные и ожидаемые по маршруту.
 * Сами подписи хранятся у файла в хранилище проекта.
 *
 * @var list<Complaints\Application\View\SignatureView> $signatures
 */
if ($signatures === []) {
    echo '<p class="text-muted">' . complaints_te('complaints_no_signatures') . '</p>';

    return;
}
?>
<table class="table table-bordered table-condensed">
    <tr>
        <th><?= complaints_te('complaints_signer_role') ?></th>
        <th><?= complaints_te('complaints_signer') ?></th>
        <th><?= complaints_te('complaints_signature_status') ?></th>
        <th><?= complaints_te('complaints_signed_at') ?></th>
    </tr>
    <?php foreach ($signatures as $view) : ?>
        <tr>
            <td><?= complaints_te('complaints_signer_role_' . $view->signature->role->value) ?></td>
            <td>
                <?= complaints_e($view->signerName) ?>
                <?php if ($view->signature->certificateSubject !== null) : ?>
                    <div class="complaints-hash"><?= complaints_e($view->signature->certificateSubject) ?></div>
                <?php endif; ?>
            </td>
            <td><?= complaints_label('signature_status', $view->signature->status) ?></td>
            <td><?= complaints_moment($view->signature->signedAt) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
