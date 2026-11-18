<?php

declare(strict_types=1);

/**
 * PDF ответа на жалобу или на все жалобы лота (DocumentType::Answer), см. DecisionPdf::answer.
 *
 * @var Complaints\Infrastructure\Pdf\DocumentTemplate $doc
 * @var array<string, mixed>                           $v
 */
?>
<h1><?= $doc->label($v['per_lot'] ? 'doc.answer_lot' : 'doc.answer') ?></h1>
<p class="number"><?= $doc->label('field.generated_at') ?>: <?= $doc->e($v['generated_at']) ?></p>

<h2><?= $doc->label('section.buy') ?></h2>
<table>
    <tr><th><?= $doc->label('field.buy_number') ?></th><td><?= $doc->e($v['buy']['number']) ?></td></tr>
    <tr><th><?= $doc->label('field.buy_name') ?></th><td><?= $doc->text($v['buy']['name']) ?></td></tr>
    <tr><th><?= $doc->label('field.lot_number') ?></th><td><?= $doc->e($v['lot']['number']) ?></td></tr>
    <tr><th><?= $doc->label('field.lot_name') ?></th><td><?= $doc->text($v['lot']['name']) ?></td></tr>
</table>

<h2><?= $doc->label('section.complaints') ?></h2>
<table class="list">
    <tr>
        <th><?= $doc->label('field.complaint_number') ?></th>
        <th><?= $doc->label('field.bin') ?></th>
        <th><?= $doc->label('field.supplier') ?></th>
        <th><?= $doc->label('field.verdict') ?></th>
    </tr>
    <?php foreach ($v['complaints'] as $complaint) : ?>
        <tr>
            <td><?= $doc->e($complaint['number']) ?></td>
            <td><?= $doc->e($complaint['supplier_bin']) ?></td>
            <td><?= $doc->text($complaint['supplier_name']) ?></td>
            <td><?= $doc->option('verdict', $complaint['verdict']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<h2><?= $doc->label('section.answer') ?></h2>
<div class="text"><?= $doc->multiline($v['answer_text']) ?></div>

<h2><?= $doc->label('section.attachments') ?></h2>
<?php if ($v['attachments'] === []) : ?>
    <p><?= $doc->label('value.none') ?></p>
<?php else : ?>
    <ol>
        <?php foreach ($v['attachments'] as $fileName) : ?>
            <li><?= $doc->e($fileName) ?></li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<h2><?= $doc->label('section.authority') ?></h2>
<table>
    <tr><th><?= $doc->label('field.bin') ?></th><td><?= $doc->e($v['authority']['bin']) ?></td></tr>
    <tr><th><?= $doc->label('field.name') ?></th><td><?= $doc->text($v['authority']['name']) ?></td></tr>
    <tr><th><?= $doc->label('field.reviewer') ?></th><td><?= $doc->e($v['reviewer']) ?></td></tr>
</table>
