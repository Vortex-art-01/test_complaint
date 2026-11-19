<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\AppComplaintDraft;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\ApplicationLotCard;
use Complaints\Application\Repository\CollectionBatch;
use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\AppComplaint\AppComplaintLot;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\BuyComplaint\Verdict;
use DateTimeImmutable;
use LogicException;
use UnexpectedValueException;

/**
 * Жалобы поставщиков: app_complaint и app_complaint_lots.
 */
final class SqlAppComplaintRepository implements AppComplaintRepository
{
    private const TRANSITION_COLUMNS = [
        'submitted_at' => 'date_apply',
        'submitted_by_user_id' => 'applyed_by_user_id',
        'buy_complaint_id' => 'trd_complaint_id',
    ];

    public function __construct(
        private readonly Database $db,
        private readonly AppComplaintMapper $mapper,
        private readonly ProjectSchema $schema,
        private readonly SqlValues $values,
    ) {
    }

    public function get(int $complaintId): AppComplaint
    {
        return $this->one('WHERE c.id = :id AND c.deleted_at IS NULL', ['id' => $complaintId]);
    }

    public function lock(int $complaintId): AppComplaint
    {
        return $this->one('WHERE c.id = :id AND c.deleted_at IS NULL FOR UPDATE OF c', ['id' => $complaintId]);
    }

    public function create(AppComplaintDraft $complaint, DateTimeImmutable $now): int
    {
        return $this->values->int($this->db->value(
            'INSERT INTO app_complaint (trd_buy_id, trd_app_id, director_id, status_id, cruser_id, crsubject_id, created_at)'
            . ' VALUES (:buy, :app, :head, :status, :author, :supplier, :created) RETURNING id',
            [
                'buy' => $complaint->buyId,
                'app' => $complaint->appId,
                'head' => $complaint->headUserId,
                'status' => ReferenceIds::COMPLAINT_DRAFT,
                'author' => $complaint->authorUserId,
                'supplier' => $complaint->supplierOrgId,
                'created' => $now,
            ],
        ));
    }

    public function updateContent(int $complaintId, ?string $contactPhone, ?string $description, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE app_complaint SET phone_number = :phone, description = :description WHERE id = :id',
            ['phone' => $contactPhone, 'description' => $description, 'id' => $complaintId],
        );
    }

    public function changeHead(int $complaintId, int $headUserId, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE app_complaint SET director_id = :head WHERE id = :id',
            ['head' => $headUserId, 'id' => $complaintId],
        );
    }

    public function transition(int $complaintId, AppComplaintStatus $from, AppComplaintStatus $to, DateTimeImmutable $now, array $changes = []): bool
    {
        $sets = ['status_id = :to'];
        $params = [
            'to' => ReferenceIds::complaintStatus($to),
            'id' => $complaintId,
            'from' => ReferenceIds::complaintStatus($from),
        ];
        foreach ($changes as $key => $value) {
            $column = self::TRANSITION_COLUMNS[$key] ?? throw new LogicException('Неизвестное поле перехода жалобы: ' . $key);
            $sets[] = $column . ' = :change_' . $key;
            $params['change_' . $key] = $value;
        }

        return $this->db->execute(
            'UPDATE app_complaint SET ' . implode(', ', $sets) . ' WHERE id = :id AND status_id = :from AND deleted_at IS NULL',
            $params,
        ) === 1;
    }

    public function softDelete(int $complaintId, DateTimeImmutable $now): bool
    {
        return $this->db->execute(
            'UPDATE app_complaint SET deleted_at = :now'
            . ' WHERE id = :id AND deleted_at IS NULL AND status_id IN (' . ReferenceIds::COMPLAINT_DELETABLE . ')',
            ['now' => $now, 'id' => $complaintId],
        ) === 1;
    }

    public function lockApplicationLot(int $appLotId): void
    {
        $this->db->value(
            "SELECT pg_advisory_xact_lock(hashtext('complaints.app_lot'), CAST(CAST(:id AS bigint) % 2147483647 AS integer))",
            ['id' => $appLotId],
        );
    }

    public function hasActiveComplaint(int $appLotId, ?int $exceptComplaintId): bool
    {
        $params = ['app_lot' => $appLotId];
        $except = '';
        if ($exceptComplaintId !== null) {
            $except = ' AND c.id <> :except';
            $params['except'] = $exceptComplaintId;
        }

        return $this->values->bool($this->db->value(
            'SELECT EXISTS (SELECT 1 FROM app_complaint_lots l JOIN app_complaint c ON c.id = l.app_complaint_id'
            . ' WHERE l.trd_app_lot_id = :app_lot AND c.deleted_at IS NULL AND c.trd_complaint_id IS NULL' . $except . ')',
            $params,
        ));
    }

    public function collectionBatches(DateTimeImmutable $appealEndedBy): array
    {
        // Срок закончился не позже момента: последний день срока раньше дня момента.
        $rows = $this->db->rows(
            sprintf(
                'SELECT b.id AS buy_id, CAST(CAST(b.%1$s AS date) AS text) AS appeal_start,'
                . ' CAST(CAST(b.%2$s AS date) AS text) AS appeal_end'
                . ' FROM %3$s b'
                . ' WHERE b.%1$s IS NOT NULL AND CAST(b.%2$s AS date) < CAST(:ended_day AS date)'
                . ' AND EXISTS (SELECT 1 FROM app_complaint c WHERE c.trd_buy_id = b.id AND c.status_id = %4$d'
                . ' AND c.trd_complaint_id IS NULL AND c.deleted_at IS NULL)'
                . ' ORDER BY b.%2$s, b.id',
                $this->schema->buyAppealStart(),
                $this->schema->buyAppealEnd(),
                $this->schema->buyTable(),
                ReferenceIds::COMPLAINT_SUBMITTED,
            ),
            ['ended_day' => $this->values->dayOf($appealEndedBy)],
        );

        return array_map(
            fn (array $row): CollectionBatch => new CollectionBatch(
                $this->values->int($row['buy_id']),
                $this->values->daysOrNull($row['appeal_start'], $row['appeal_end'])
                    ?? throw new UnexpectedValueException('Нет срока обжалования закупки ' . $row['buy_id']),
            ),
            $rows,
        );
    }

    public function lockSubmittedOfBuy(int $buyId): array
    {
        return $this->many(
            'WHERE c.trd_buy_id = :buy AND c.status_id = :status AND c.trd_complaint_id IS NULL AND c.deleted_at IS NULL'
            . ' ORDER BY c.id FOR UPDATE OF c',
            ['buy' => $buyId, 'status' => ReferenceIds::COMPLAINT_SUBMITTED],
        );
    }

    public function ofBuyComplaint(int $buyComplaintId): array
    {
        return $this->many(
            'WHERE c.trd_complaint_id = :decision AND c.deleted_at IS NULL ORDER BY c.id',
            ['decision' => $buyComplaintId],
        );
    }

    public function lotOf(int $complaintId): AppComplaintLot
    {
        return $this->oneLot('WHERE l.app_complaint_id = :complaint', ['complaint' => $complaintId]);
    }

    public function lot(int $lotId): AppComplaintLot
    {
        return $this->oneLot('WHERE l.id = :id', ['id' => $lotId]);
    }

    public function addLot(int $complaintId, ApplicationLotCard $appLot, DateTimeImmutable $now): int
    {
        // Лот создаёт автор жалобы от имени своей организации.
        return $this->values->int($this->db->value(
            'INSERT INTO app_complaint_lots (app_complaint_id, trd_buy_lot_id, trd_app_lot_id, cruser_id, crsubject_id, created_at)'
            . ' SELECT c.id, :buy_lot, :app_lot, c.cruser_id, c.crsubject_id, :created FROM app_complaint c WHERE c.id = :complaint'
            . ' RETURNING id',
            ['buy_lot' => $appLot->buyLotId, 'app_lot' => $appLot->id, 'created' => $now, 'complaint' => $complaintId],
        ) ?? throw new NotFound());
    }

    public function changeLot(int $lotId, ApplicationLotCard $appLot, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE app_complaint_lots SET trd_app_lot_id = :app_lot, trd_buy_lot_id = :buy_lot WHERE id = :id',
            ['app_lot' => $appLot->id, 'buy_lot' => $appLot->buyLotId, 'id' => $lotId],
        );
    }

    public function lotsOfBuyComplaintLot(int $buyComplaintLotId): array
    {
        return $this->manyLots(
            'WHERE l.trd_complaint_lots_id = :decision_lot AND lc.deleted_at IS NULL ORDER BY l.id',
            ['decision_lot' => $buyComplaintLotId],
        );
    }

    public function lotsOfBuyComplaint(int $buyComplaintId): array
    {
        return $this->manyLots(
            'WHERE lc.trd_complaint_id = :decision AND lc.deleted_at IS NULL ORDER BY l.id',
            ['decision' => $buyComplaintId],
        );
    }

    public function linkToBuyComplaintLot(int $lotId, int $buyComplaintLotId, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE app_complaint_lots SET trd_complaint_lots_id = :decision_lot WHERE id = :id',
            ['decision_lot' => $buyComplaintLotId, 'id' => $lotId],
        );
    }

    public function setVerdict(int $lotId, ?Verdict $verdict, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE app_complaint_lots SET verdict_id = :verdict WHERE id = :id',
            ['verdict' => $verdict === null ? null : ReferenceIds::verdict($verdict), 'id' => $lotId],
        );
    }

    public function setAnswer(int $lotId, ?int $answerId, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE app_complaint_lots SET answer_id = :answer WHERE id = :id',
            ['answer' => $answerId, 'id' => $lotId],
        );
    }

    public function clearAnswers(int $buyComplaintId, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE app_complaint_lots l SET answer_id = NULL FROM app_complaint c'
            . ' WHERE c.id = l.app_complaint_id AND c.trd_complaint_id = :decision AND l.answer_id IS NOT NULL',
            ['decision' => $buyComplaintId],
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws NotFound
     */
    private function one(string $condition, array $params): AppComplaint
    {
        $row = $this->db->row('SELECT ' . $this->mapper->columns() . ' FROM ' . $this->mapper->from() . ' ' . $condition, $params);

        return $row === null ? throw new NotFound() : $this->mapper->complaint($row);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<AppComplaint>
     */
    private function many(string $condition, array $params): array
    {
        return array_map(
            fn (array $row): AppComplaint => $this->mapper->complaint($row),
            $this->db->rows('SELECT ' . $this->mapper->columns() . ' FROM ' . $this->mapper->from() . ' ' . $condition, $params),
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws NotFound
     */
    private function oneLot(string $condition, array $params): AppComplaintLot
    {
        $row = $this->db->row('SELECT ' . AppComplaintMapper::LOT_COLUMNS . ' FROM ' . AppComplaintMapper::LOT_FROM . ' ' . $condition, $params);

        return $row === null ? throw new NotFound() : $this->mapper->lot($row);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<AppComplaintLot>
     */
    private function manyLots(string $condition, array $params): array
    {
        return array_map(
            fn (array $row): AppComplaintLot => $this->mapper->lot($row),
            $this->db->rows('SELECT ' . AppComplaintMapper::LOT_COLUMNS . ' FROM ' . AppComplaintMapper::LOT_FROM . ' ' . $condition, $params),
        );
    }
}
