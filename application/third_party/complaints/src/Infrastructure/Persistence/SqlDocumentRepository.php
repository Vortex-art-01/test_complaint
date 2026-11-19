<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Application\Access\Actor;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Domain\Document\Document;
use Complaints\Domain\Document\DocumentType;
use DateTimeImmutable;
use LogicException;

/**
 * Сформированные PDF в app_complaint_docs и trd_complaint_docs. id документа —
 * id файла (fls_stg_entity_id); действующая версия — не удалённая строка.
 *
 *   жалоба               app_complaint_docs, вид 1
 *   ответ по жалобе      app_complaint_docs, вид 3, лот жалобы с ответом
 *   ответ по лоту        trd_complaint_docs, вид 2, лот решения
 *   сводный документ     trd_complaint_docs, вид 1
 */
final class SqlDocumentRepository implements DocumentRepository
{
    private const APP_TYPES = [ReferenceIds::APP_DOC_COMPLAINT, ReferenceIds::APP_DOC_ANSWER];

    private const TRD_TYPES = [ReferenceIds::TRD_DOC_CONSOLIDATED, ReferenceIds::TRD_DOC_LOT_ANSWER];

    public function __construct(
        private readonly DocsTables $tables,
        private readonly SqlValues $values,
    ) {
    }

    public function current(DocumentType $type, int $ownerId): ?Document
    {
        $rows = match ($type) {
            DocumentType::AppComplaint => $this->tables->appRows(
                'WHERE d.app_complaint_id = :owner AND d.app_complaint_lots_id IS NULL AND d.ref_app_complaint_docs_type_id = :type'
                . ' AND d.deleted_at IS NULL',
                ['owner' => $ownerId, 'type' => ReferenceIds::APP_DOC_COMPLAINT],
            ),
            DocumentType::ConsolidatedAnswer => $this->tables->trdRows(
                'WHERE d.trd_complaint_id = :owner AND d.trd_complaint_lots_id IS NULL AND d.ref_trd_complaint_docs_type_id = :type'
                . ' AND d.deleted_at IS NULL',
                ['owner' => $ownerId, 'type' => ReferenceIds::TRD_DOC_CONSOLIDATED],
            ),
            DocumentType::Answer => $this->currentAnswerRows($ownerId),
        };

        return $rows === [] ? null : $this->document($rows[0]);
    }

    public function get(int $documentId): Document
    {
        $row = $this->tables->fileRow($documentId, self::APP_TYPES, self::TRD_TYPES);
        if ($row === null || $row['owner_id'] === null) {
            throw new NotFound();
        }

        return $this->document($row);
    }

    public function create(DocumentType $type, int $ownerId, int $fileId, Actor $generatedBy, DateTimeImmutable $now): Document
    {
        if ($type === DocumentType::AppComplaint) {
            $this->tables->addToComplaint($ownerId, null, ReferenceIds::APP_DOC_COMPLAINT, $fileId, $generatedBy, $now);
        } elseif ($type === DocumentType::ConsolidatedAnswer) {
            $this->tables->addToDecision($ownerId, null, ReferenceIds::TRD_DOC_CONSOLIDATED, $fileId, $generatedBy, $now);
        } else {
            $this->addAnswer($ownerId, $fileId, $generatedBy, $now);
        }

        return $this->get($fileId);
    }

    public function retire(int $documentId, DateTimeImmutable $now): void
    {
        $this->tables->retireFile($documentId, $now);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function currentAnswerRows(int $answerId): array
    {
        $place = $this->tables->answerPlace($answerId);
        if ($place === null) {
            return [];
        }
        if (!$place->isPerComplaint) {
            return $this->tables->trdRows(
                'WHERE d.trd_complaint_lots_id = :lot AND d.ref_trd_complaint_docs_type_id = :type AND d.deleted_at IS NULL',
                ['lot' => $place->decisionLotId, 'type' => ReferenceIds::TRD_DOC_LOT_ANSWER],
            );
        }

        return $place->complaintLotId === null ? [] : $this->tables->appRows(
            'WHERE d.app_complaint_lots_id = :lot AND d.ref_app_complaint_docs_type_id = :type AND d.deleted_at IS NULL',
            ['lot' => $place->complaintLotId, 'type' => ReferenceIds::APP_DOC_ANSWER],
        );
    }

    private function addAnswer(int $answerId, int $fileId, Actor $generatedBy, DateTimeImmutable $now): void
    {
        $place = $this->tables->answerPlace($answerId) ?? throw new NotFound();
        if (!$place->isPerComplaint) {
            $this->tables->addToDecision($place->decisionId, $place->decisionLotId, ReferenceIds::TRD_DOC_LOT_ANSWER, $fileId, $generatedBy, $now);

            return;
        }
        if ($place->complaintId === null || $place->complaintLotId === null) {
            throw new LogicException(sprintf('Ответ %d не привязан к лоту жалобы: PDF ответа хранится у лота.', $answerId));
        }
        $this->tables->addToComplaint($place->complaintId, $place->complaintLotId, ReferenceIds::APP_DOC_ANSWER, $fileId, $generatedBy, $now);
    }

    /**
     * @param array<string, mixed> $row строка DocsTables
     */
    private function document(array $row): Document
    {
        $fileId = $this->values->int($row['fls_stg_entity_id']);
        $typeId = $this->values->int($row['type_id']);
        $type = $row['source_table'] === DocsTables::COMPLAINT_FILES
            ? ($typeId === ReferenceIds::APP_DOC_ANSWER ? DocumentType::Answer : DocumentType::AppComplaint)
            : ($typeId === ReferenceIds::TRD_DOC_LOT_ANSWER ? DocumentType::Answer : DocumentType::ConsolidatedAnswer);

        return new Document(
            $fileId,
            $type,
            $this->values->int($row['owner_id']),
            $this->values->int($row['version']),
            $row['deleted_at'] === null,
            $fileId,
            $this->values->int($row['cruser_id']),
            $this->values->moment($row['created_at']),
        );
    }
}
