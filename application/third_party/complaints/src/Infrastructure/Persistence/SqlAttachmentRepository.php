<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Application\Access\Actor;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\AttachmentRepository;
use Complaints\Domain\Document\Attachment;
use Complaints\Domain\Document\AttachmentOwner;
use DateTimeImmutable;
use LogicException;

/**
 * Файлы, приложенные к жалобе и к ответу, в app_complaint_docs и trd_complaint_docs.
 * id вложения — id файла (fls_stg_entity_id); удалённое вложение — строка с deleted_at.
 *
 *   приложение к жалобе    app_complaint_docs, вид 2
 *   файл к ответу          app_complaint_docs, вид 4 (ответ по каждой жалобе)
 *                          trd_complaint_docs, вид 3 (ответ одним по лоту)
 */
final class SqlAttachmentRepository implements AttachmentRepository
{
    private const APP_TYPES = [ReferenceIds::APP_DOC_COMPLAINT_FILE, ReferenceIds::APP_DOC_ANSWER_FILE];

    private const TRD_TYPES = [ReferenceIds::TRD_DOC_LOT_ANSWER_FILE];

    public function __construct(
        private readonly DocsTables $tables,
        private readonly SqlValues $values,
    ) {
    }

    public function of(AttachmentOwner $owner, int $ownerId): array
    {
        if ($owner === AttachmentOwner::AppComplaint) {
            $rows = $this->tables->appRows(
                'WHERE d.app_complaint_id = :owner AND d.ref_app_complaint_docs_type_id = :type AND d.deleted_at IS NULL ORDER BY d.id',
                ['owner' => $ownerId, 'type' => ReferenceIds::APP_DOC_COMPLAINT_FILE],
            );
        } else {
            $rows = $this->answerRows($ownerId);
        }

        return array_map(fn (array $row): Attachment => $this->attachment($row), $rows);
    }

    public function get(int $attachmentId): Attachment
    {
        $row = $this->tables->fileRow($attachmentId, self::APP_TYPES, self::TRD_TYPES);
        if ($row === null || $row['owner_id'] === null || $row['deleted_at'] !== null) {
            throw new NotFound();
        }

        return $this->attachment($row);
    }

    public function add(AttachmentOwner $owner, int $ownerId, int $fileId, Actor $uploadedBy, DateTimeImmutable $now): int
    {
        if ($owner === AttachmentOwner::AppComplaint) {
            $this->tables->addToComplaint($ownerId, null, ReferenceIds::APP_DOC_COMPLAINT_FILE, $fileId, $uploadedBy, $now);

            return $fileId;
        }

        $place = $this->tables->answerPlace($ownerId) ?? throw new NotFound();
        if (!$place->isPerComplaint) {
            $this->tables->addToDecision(
                $place->decisionId,
                $place->decisionLotId,
                ReferenceIds::TRD_DOC_LOT_ANSWER_FILE,
                $fileId,
                $uploadedBy,
                $now,
            );

            return $fileId;
        }
        if ($place->complaintId === null || $place->complaintLotId === null) {
            throw new LogicException(sprintf('Ответ %d не привязан к лоту жалобы: файлы ответа хранятся у лота.', $ownerId));
        }
        $this->tables->addToComplaint($place->complaintId, $place->complaintLotId, ReferenceIds::APP_DOC_ANSWER_FILE, $fileId, $uploadedBy, $now);

        return $fileId;
    }

    public function remove(int $attachmentId, DateTimeImmutable $now): void
    {
        $this->tables->retireFile($attachmentId, $now);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function answerRows(int $answerId): array
    {
        $place = $this->tables->answerPlace($answerId);
        if ($place === null) {
            return [];
        }
        if (!$place->isPerComplaint) {
            return $this->tables->trdRows(
                'WHERE d.trd_complaint_lots_id = :lot AND d.ref_trd_complaint_docs_type_id = :type AND d.deleted_at IS NULL ORDER BY d.id',
                ['lot' => $place->decisionLotId, 'type' => ReferenceIds::TRD_DOC_LOT_ANSWER_FILE],
            );
        }

        return $place->complaintLotId === null ? [] : $this->tables->appRows(
            'WHERE d.app_complaint_lots_id = :lot AND d.ref_app_complaint_docs_type_id = :type AND d.deleted_at IS NULL ORDER BY d.id',
            ['lot' => $place->complaintLotId, 'type' => ReferenceIds::APP_DOC_ANSWER_FILE],
        );
    }

    /**
     * @param array<string, mixed> $row строка DocsTables
     */
    private function attachment(array $row): Attachment
    {
        $fileId = $this->values->int($row['fls_stg_entity_id']);
        $ofComplaint = $row['source_table'] === DocsTables::COMPLAINT_FILES
            && $this->values->int($row['type_id']) === ReferenceIds::APP_DOC_COMPLAINT_FILE;

        return new Attachment(
            $fileId,
            $ofComplaint ? AttachmentOwner::AppComplaint : AttachmentOwner::Answer,
            $this->values->int($row['owner_id']),
            $fileId,
            $this->values->int($row['cruser_id']),
            $this->values->moment($row['created_at']),
            $this->values->momentOrNull($row['deleted_at']),
        );
    }
}
