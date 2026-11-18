<?php

declare(strict_types=1);

/**
 * PDF жалобы поставщика (DocumentType::AppComplaint), см. ComplaintPdf.
 *
 * @var Complaints\Infrastructure\Pdf\DocumentTemplate $doc
 * @var array<string, mixed>                           $v
 */

$requisites = $v['requisites'];
?>
<h1><?= $doc->label('doc.app_complaint') ?></h1>
<p class="number"><?= $doc->label('field.number') ?>: <strong><?= $doc->e($v['number']) ?></strong>,
    <?= $doc->label('field.generated_at') ?>: <?= $doc->e($v['generated_at']) ?></p>

<h2><?= $doc->label('section.buy') ?></h2>
<table>
    <tr><th><?= $doc->label('field.buy_number') ?></th><td><?= $doc->e($requisites['buy']['number']) ?></td></tr>
    <tr><th><?= $doc->label('field.buy_name') ?></th><td><?= $doc->text($requisites['buy']['name']) ?></td></tr>
    <tr><th><?= $doc->label('field.buy_published_at') ?></th><td><?= $doc->e($v['buy_published_at']) ?></td></tr>
    <tr><th><?= $doc->label('field.lot_number') ?></th><td><?= $doc->e($requisites['lot']['number']) ?></td></tr>
    <tr><th><?= $doc->label('field.lot_name') ?></th><td><?= $doc->text($requisites['lot']['name']) ?></td></tr>
</table>

<h2><?= $doc->label('section.organizer') ?></h2>
<table>
    <tr><th><?= $doc->label('field.bin') ?></th><td><?= $doc->e($requisites['organizer']['bin']) ?></td></tr>
    <tr><th><?= $doc->label('field.name') ?></th><td><?= $doc->text($requisites['organizer']['name']) ?></td></tr>
    <tr><th><?= $doc->label('field.address') ?></th><td><?= $doc->text($requisites['organizer']['address']) ?></td></tr>
</table>

<h2><?= $doc->label('section.supplier') ?></h2>
<table>
    <tr><th><?= $doc->label('field.bin') ?></th><td><?= $doc->e($requisites['supplier']['bin']) ?></td></tr>
    <tr><th><?= $doc->label('field.name') ?></th><td><?= $doc->text($requisites['supplier']['name']) ?></td></tr>
    <tr><th><?= $doc->label('field.address') ?></th><td><?= $doc->text($requisites['supplier']['address']) ?></td></tr>
</table>

<h2><?= $doc->label('section.representative') ?></h2>
<table>
    <tr><th><?= $doc->label('field.full_name') ?></th><td><?= $doc->e($requisites['representative']['full_name']) ?></td></tr>
    <tr><th><?= $doc->label('field.iin') ?></th><td><?= $doc->e($requisites['representative']['iin']) ?></td></tr>
    <tr><th><?= $doc->label('field.email') ?></th><td><?= $doc->e($requisites['representative']['email']) ?></td></tr>
    <tr><th><?= $doc->label('field.phone') ?></th><td><?= $doc->e($v['contact_phone']) ?></td></tr>
</table>

<h2><?= $doc->label('section.head') ?></h2>
<table>
    <tr><th><?= $doc->label('field.full_name') ?></th><td><?= $doc->e($requisites['head']['full_name']) ?></td></tr>
    <tr><th><?= $doc->label('field.position') ?></th><td><?= $doc->e($requisites['head']['position']) ?></td></tr>
</table>

<h2><?= $doc->label('section.appeal') ?></h2>
<table>
    <tr><th><?= $doc->label('field.protocol_published_at') ?></th><td><?= $doc->e($v['protocol_published_at']) ?></td></tr>
    <tr><th><?= $doc->label('field.appeal_period') ?></th><td><?= $doc->e($v['appeal_period']['from']) ?> — <?= $doc->e($v['appeal_period']['to']) ?></td></tr>
</table>

<h2><?= $doc->label('section.description') ?></h2>
<div class="text"><?= $doc->multiline($v['description']) ?></div>

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

<p class="note"><?= $doc->label('note.signature') ?></p>
