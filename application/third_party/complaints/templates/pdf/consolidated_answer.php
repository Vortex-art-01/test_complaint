<?php

declare(strict_types=1);

/**
 * Сводный документ ответов по закупке (DocumentType::ConsolidatedAnswer), см. DecisionPdf::consolidated.
 * Подписывается рассматривающим, согласующим и утверждающим; реестр
 * с контрольными суммами связывает подписи с PDF ответов.
 *
 * @var Complaints\Infrastructure\Pdf\DocumentTemplate $doc
 * @var array<string, mixed>                           $v
 */
?>
<h1><?= $doc->label('doc.consolidated_answer') ?></h1>
<p class="number"><?= $doc->label('field.generated_at') ?>: <?= $doc->e($v['generated_at']) ?></p>

<table>
    <tr><th><?= $doc->label('field.buy_number') ?></th><td><?= $doc->e($v['buy']['number']) ?></td></tr>
    <tr><th><?= $doc->label('field.buy_name') ?></th><td><?= $doc->text($v['buy']['name']) ?></td></tr>
    <tr><th><?= $doc->label('section.authority') ?></th><td><?= $doc->text($v['authority']['name']) ?> (<?= $doc->label('field.bin') ?> <?= $doc->e($v['authority']['bin']) ?>)</td></tr>
    <tr><th><?= $doc->label('field.reviewer') ?></th><td><?= $doc->e($v['reviewer']) ?></td></tr>
    <tr><th><?= $doc->label('field.review_method') ?></th><td><?= $doc->option('review_method', $v['review_method']) ?></td></tr>
    <tr><th><?= $doc->label('field.review_period') ?></th><td><?= $doc->e($v['review_period']['from']) ?> — <?= $doc->e($v['review_period']['to']) ?></td></tr>
</table>

<h2><?= $doc->label('section.lots') ?></h2>
<?php foreach ($v['lots'] as $lot) : ?>
    <table>
        <tr><th><?= $doc->label('field.lot_number') ?></th><td><?= $doc->e($lot['number']) ?></td></tr>
        <tr><th><?= $doc->label('field.lot_name') ?></th><td><?= $doc->text($lot['name']) ?></td></tr>
        <tr><th><?= $doc->label('field.decision_type') ?></th><td><strong><?= $doc->option('decision_type', $lot['decision_type']) ?></strong></td></tr>
    </table>
    <table class="list">
        <tr>
            <th><?= $doc->label('field.complaint_number') ?></th>
            <th><?= $doc->label('field.bin') ?></th>
            <th><?= $doc->label('field.supplier') ?></th>
            <th><?= $doc->label('field.verdict') ?></th>
        </tr>
        <?php foreach ($lot['complaints'] as $complaint) : ?>
            <tr>
                <td><?= $doc->e($complaint['number']) ?></td>
                <td><?= $doc->e($complaint['supplier_bin']) ?></td>
                <td><?= $doc->text($complaint['supplier_name']) ?></td>
                <td><?= $doc->option('verdict', $complaint['verdict']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php foreach ($lot['answers'] as $answer) : ?>
        <p><?= $doc->label('section.answer') ?> (<?= $doc->e(implode(', ', $answer['complaints'])) ?>):</p>
        <div class="text"><?= $doc->multiline($answer['text']) ?></div>
    <?php endforeach; ?>
<?php endforeach; ?>

<h2><?= $doc->label('section.registry') ?></h2>
<table class="list">
    <tr>
        <th>№</th>
        <th><?= $doc->label('field.file') ?></th>
        <th><?= $doc->label('field.sha256') ?></th>
    </tr>
    <?php foreach ($v['registry'] as $index => $entry) : ?>
        <tr>
            <td><?= $index + 1 ?></td>
            <td><?= $doc->e($entry['file']) ?></td>
            <td class="hash"><?= $doc->e($entry['sha256']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<p class="note"><?= $doc->label('note.signature') ?></p>
