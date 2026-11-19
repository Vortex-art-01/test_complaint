<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Application\Access\Actor;
use DateTimeImmutable;

/**
 * Таблицы файлов модуля: app_complaint_docs (псевдоним d, жалоба) и
 * trd_complaint_docs (псевдоним d, решение). Документы и вложения различаются видом.
 *
 * Владелец строки вычисляется: без лота — жалоба или решение, с лотом — ответ
 * (по способу ответа решения). Если способ сменили, у прежних строк лота владельца
 * нет (owner_id пуст). Номер версии — порядковый среди строк того же вида у того
 * же владельца в таблице.
 */
final class DocsTables
{
    /** Значения столбца source_table в строках appRows() и trdRows(). */
    public const COMPLAINT_FILES = 'app_complaint_docs';
    public const DECISION_FILES = 'trd_complaint_docs';

    private const APP_SELECT = "SELECT '" . self::COMPLAINT_FILES . "' AS source_table, d.fls_stg_entity_id,"
        . ' d.ref_app_complaint_docs_type_id AS type_id,'
        . ' CASE WHEN d.app_complaint_lots_id IS NULL THEN d.app_complaint_id'
        . ' WHEN t.method_id = ' . ReferenceIds::METHOD_PER_COMPLAINT . ' THEN l.answer_id END AS owner_id,'
        . ' (SELECT count(*) FROM app_complaint_docs v WHERE v.app_complaint_id = d.app_complaint_id'
        . ' AND v.ref_app_complaint_docs_type_id = d.ref_app_complaint_docs_type_id'
        . ' AND v.app_complaint_lots_id IS NOT DISTINCT FROM d.app_complaint_lots_id AND v.id <= d.id) AS version,'
        . ' d.cruser_id, d.created_at, d.deleted_at'
        . ' FROM app_complaint_docs d'
        . ' LEFT JOIN app_complaint_lots l ON l.id = d.app_complaint_lots_id'
        . ' LEFT JOIN trd_complaint_lots tl ON tl.id = l.trd_complaint_lots_id'
        . ' LEFT JOIN trd_complaint t ON t.id = tl.trd_complaint_id ';

    private const TRD_SELECT = "SELECT '" . self::DECISION_FILES . "' AS source_table, d.fls_stg_entity_id,"
        . ' d.ref_trd_complaint_docs_type_id AS type_id,'
        . ' CASE WHEN d.trd_complaint_lots_id IS NULL THEN d.trd_complaint_id'
        . ' WHEN t.method_id = ' . ReferenceIds::METHOD_PER_LOT . ' THEN (SELECT min(a.id) FROM app_complaint_answer a'
        . ' WHERE a.trd_complaint_lots_id = d.trd_complaint_lots_id) END AS owner_id,'
        . ' (SELECT count(*) FROM trd_complaint_docs v WHERE v.trd_complaint_id = d.trd_complaint_id'
        . ' AND v.ref_trd_complaint_docs_type_id = d.ref_trd_complaint_docs_type_id'
        . ' AND v.trd_complaint_lots_id IS NOT DISTINCT FROM d.trd_complaint_lots_id AND v.id <= d.id) AS version,'
        . ' d.cruser_id, d.created_at, d.deleted_at'
        . ' FROM trd_complaint_docs d'
        . ' JOIN trd_complaint t ON t.id = d.trd_complaint_id ';

    public function __construct(
        private readonly Database $db,
        private readonly SqlValues $values,
    ) {
    }

    /**
     * Строки app_complaint_docs: source_table, fls_stg_entity_id, type_id, owner_id, version,
     * cruser_id, created_at, deleted_at.
     *
     * @param string               $condition WHERE … по псевдониму d
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function appRows(string $condition, array $params): array
    {
        return $this->db->rows(self::APP_SELECT . $condition, $params);
    }

    /**
     * Строки trd_complaint_docs, те же столбцы, что у appRows().
     *
     * @param string               $condition WHERE … по псевдониму d
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function trdRows(string $condition, array $params): array
    {
        return $this->db->rows(self::TRD_SELECT . $condition, $params);
    }

    /**
     * Строка файла с id из любой таблицы; null — файла нет среди видов.
     *
     * @param list<int> $appTypes виды app_complaint_docs
     * @param list<int> $trdTypes виды trd_complaint_docs
     * @return array<string, mixed>|null
     */
    public function fileRow(int $fileId, array $appTypes, array $trdTypes): ?array
    {
        $rows = $this->appRows(
            'WHERE d.fls_stg_entity_id = :file AND d.ref_app_complaint_docs_type_id = ANY (CAST(:types AS bigint[]))',
            ['file' => $fileId, 'types' => Database::intArray($appTypes)],
        );
        if ($rows === []) {
            $rows = $this->trdRows(
                'WHERE d.fls_stg_entity_id = :file AND d.ref_trd_complaint_docs_type_id = ANY (CAST(:types AS bigint[]))',
                ['file' => $fileId, 'types' => Database::intArray($trdTypes)],
            );
        }

        return $rows[0] ?? null;
    }

    public function addToComplaint(int $complaintId, ?int $complaintLotId, int $typeId, int $fileId, Actor $actor, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'INSERT INTO app_complaint_docs (app_complaint_id, app_complaint_lots_id, ref_app_complaint_docs_type_id,'
            . ' fls_stg_entity_id, cruser_id, crsubject_id, created_at)'
            . ' VALUES (:complaint, :lot, :type, :file, :user, :subject, :created)',
            [
                'complaint' => $complaintId,
                'lot' => $complaintLotId,
                'type' => $typeId,
                'file' => $fileId,
                'user' => $actor->userId,
                'subject' => $actor->organizationId,
                'created' => $now,
            ],
        );
    }

    public function addToDecision(int $decisionId, ?int $decisionLotId, int $typeId, int $fileId, Actor $actor, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'INSERT INTO trd_complaint_docs (trd_complaint_id, trd_complaint_lots_id, ref_trd_complaint_docs_type_id,'
            . ' fls_stg_entity_id, cruser_id, crsubject_id, created_at)'
            . ' VALUES (:decision, :lot, :type, :file, :user, :subject, :created)',
            [
                'decision' => $decisionId,
                'lot' => $decisionLotId,
                'type' => $typeId,
                'file' => $fileId,
                'user' => $actor->userId,
                'subject' => $actor->organizationId,
                'created' => $now,
            ],
        );
    }

    /**
     * Файл перестаёт действовать: версия документа снята или вложение удалено.
     */
    public function retireFile(int $fileId, DateTimeImmutable $now): void
    {
        $params = ['now' => $now, 'file' => $fileId];
        $this->db->execute('UPDATE app_complaint_docs SET deleted_at = :now WHERE fls_stg_entity_id = :file AND deleted_at IS NULL', $params);
        $this->db->execute('UPDATE trd_complaint_docs SET deleted_at = :now WHERE fls_stg_entity_id = :file AND deleted_at IS NULL', $params);
    }

    /**
     * Где хранятся файлы ответа; null — ответа нет или способ ответа не выбран.
     */
    public function answerPlace(int $answerId): ?AnswerPlace
    {
        $row = $this->db->row(
            'SELECT t.method_id, tl.trd_complaint_id, tl.id AS decision_lot_id, l.app_complaint_id, l.id AS complaint_lot_id'
            . ' FROM app_complaint_answer a'
            . ' JOIN trd_complaint_lots tl ON tl.id = a.trd_complaint_lots_id'
            . ' JOIN trd_complaint t ON t.id = tl.trd_complaint_id'
            . ' LEFT JOIN app_complaint_lots l ON l.answer_id = a.id AND t.method_id = ' . ReferenceIds::METHOD_PER_COMPLAINT
            . ' WHERE a.id = :answer AND t.method_id IS NOT NULL'
            . ' ORDER BY l.id LIMIT 1',
            ['answer' => $answerId],
        );
        if ($row === null) {
            return null;
        }

        return new AnswerPlace(
            $this->values->int($row['method_id']) === ReferenceIds::METHOD_PER_COMPLAINT,
            $this->values->int($row['trd_complaint_id']),
            $this->values->int($row['decision_lot_id']),
            $this->values->intOrNull($row['app_complaint_id']),
            $this->values->intOrNull($row['complaint_lot_id']),
        );
    }
}
