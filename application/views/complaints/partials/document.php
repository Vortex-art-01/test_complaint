<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Ссылка на PDF документа модуля с номером версии и датой формирования.
 *
 * @var Complaints\Application\View\DocumentView|null $document
 */
if ($document === null) {
    echo '<span class="text-muted">' . complaints_te('complaints_document_not_formed') . '</span>';

    return;
}
?>
<a href="<?= complaints_e(complaints_url('complaint_files/download/document/' . $document->document->id)) ?>">
    <span class="glyphicon glyphicon-file"></span> <?= complaints_e($document->file->fileName) ?>
</a>
<span class="text-muted">
    (<?= complaints_te('complaints_document_version', ['version' => $document->document->version]) ?>,
    <?= complaints_moment($document->document->generatedAt) ?>)
</span>
