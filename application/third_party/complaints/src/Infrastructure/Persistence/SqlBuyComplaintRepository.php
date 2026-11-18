<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\BuyComplaintCycle;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;
use Complaints\Domain\BuyComplaint\ReviewMethod;
use Complaints\Domain\Shared\Period;
use DateTimeImmutable;
use LogicException;

final class SqlBuyComplaintRepository implements BuyComplaintRepository
{
    private const TRANSITION_COLUMNS = [
        'coordinator_user_id',
        'approver_user_id',
        'sent_for_approval_at',
        'decision_number',
        'approved_at',
        'execution_status',
    ];

    public function __construct(
        private readonly Database $db,
        private readonly BuyComplaintMapper $mapper,
        private readonly SqlValues $values,
    ) {
    }

    public function get(int $id): BuyComplaint
    {
        return $this->one('WHERE d.id = :id', ['id' => $id]);
    }

    public function lock(int $id): BuyComplaint
    {
        return $this->one('WHERE d.id = :id FOR UPDATE', ['id' => $id]);
    }

    public function create(BuyComplaintCycle $decision, DateTimeImmutable $now): ?int
    {
        return $this->values->intOrNull($this->db->value(
            'INSERT INTO test_buy_complaint (buy_id, authority_org_id, protocol_published_at, appeal_starts_at, appeal_ends_at,'
            . ' review_starts_at, review_ends_at, status, created_at, updated_at)'
            . ' VALUES (:buy, :authority, :protocol, :appeal_starts, :appeal_ends, :review_starts, :review_ends, :status, :created, :updated)'
            . ' ON CONFLICT ON CONSTRAINT uq_buy_complaint_buy_period DO NOTHING'
            . ' RETURNING id',
            [
                'buy' => $decision->buyId,
                'authority' => $decision->authorityOrgId,
                'protocol' => $decision->protocolPublishedAt,
                'appeal_starts' => $decision->appealPeriod->startsAt,
                'appeal_ends' => $decision->appealPeriod->endsAt,
                'review_starts' => $decision->reviewPeriod->startsAt,
                'review_ends' => $decision->reviewPeriod->endsAt,
                'status' => BuyComplaintStatus::InReview,
                'created' => $now,
                'updated' => $now,
            ],
        ));
    }

    public function findByBuyAndAppealEnd(int $buyId, DateTimeImmutable $appealEndsAt): ?BuyComplaint
    {
        $row = $this->db->row(
            'SELECT ' . BuyComplaintMapper::COLUMNS . ' FROM test_buy_complaint d WHERE d.buy_id = :buy AND d.appeal_ends_at = :appeal_ends',
            ['buy' => $buyId, 'appeal_ends' => $appealEndsAt],
        );

        return $row === null ? null : $this->mapper->decision($row);
    }

    public function transition(int $id, BuyComplaintStatus $from, BuyComplaintStatus $to, DateTimeImmutable $now, array $changes = []): bool
    {
        $sets = ['status = :to', 'updated_at = :now'];
        $params = ['to' => $to, 'now' => $now, 'id' => $id, 'from' => $from];
        foreach ($changes as $key => $value) {
            if ($key === 'execution_period') {
                if (!$value instanceof Period) {
                    throw new LogicException('execution_period: ожидался Period.');
                }
                $sets[] = 'execution_starts_at = :execution_starts';
                $sets[] = 'execution_ends_at = :execution_ends';
                $params['execution_starts'] = $value->startsAt;
                $params['execution_ends'] = $value->endsAt;
                continue;
            }
            if (!in_array($key, self::TRANSITION_COLUMNS, true)) {
                throw new LogicException('Неизвестное поле перехода решения: ' . $key);
            }
            $sets[] = $key . ' = :change_' . $key;
            $params['change_' . $key] = $value;
        }

        return $this->db->execute(
            'UPDATE test_buy_complaint SET ' . implode(', ', $sets) . ' WHERE id = :id AND status = :from',
            $params,
        ) === 1;
    }

    public function claim(int $id, int $reviewerUserId, DateTimeImmutable $now): bool
    {
        return $this->db->execute(
            'UPDATE test_buy_complaint SET reviewer_user_id = :reviewer, updated_at = :now WHERE id = :id AND reviewer_user_id IS NULL',
            ['reviewer' => $reviewerUserId, 'now' => $now, 'id' => $id],
        ) === 1;
    }

    public function release(int $id, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_buy_complaint SET reviewer_user_id = NULL, updated_at = :now WHERE id = :id',
            ['now' => $now, 'id' => $id],
        );
    }

    public function setReviewMethod(int $id, ReviewMethod $method, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_buy_complaint SET review_method = :method, updated_at = :now WHERE id = :id',
            ['method' => $method, 'now' => $now, 'id' => $id],
        );
    }

    public function markExecuted(int $id, DateTimeImmutable $now): bool
    {
        return $this->db->execute(
            'UPDATE test_buy_complaint SET execution_status = :executed, executed_at = :executed_at, updated_at = :updated'
            . ' WHERE id = :id AND execution_status = :in_progress',
            [
                'executed' => ExecutionStatus::Executed,
                'executed_at' => $now,
                'updated' => $now,
                'id' => $id,
                'in_progress' => ExecutionStatus::InProgress,
            ],
        ) === 1;
    }

    public function lockBuy(int $buyId): void
    {
        $this->db->value(
            "SELECT pg_advisory_xact_lock(hashtext('complaints.buy'), (CAST(:id AS bigint) % 2147483647)::int)",
            ['id' => $buyId],
        );
    }

    public function countNumberedForBuy(int $buyId): int
    {
        return $this->values->int($this->db->value(
            'SELECT count(*) FROM test_buy_complaint WHERE buy_id = :buy AND decision_number IS NOT NULL',
            ['buy' => $buyId],
        ));
    }

    public function reviewEndingBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->many(
            'WHERE d.status <> :approved AND d.review_ends_at >= :from AND d.review_ends_at < :to ORDER BY d.id',
            ['approved' => BuyComplaintStatus::Approved, 'from' => $from, 'to' => $to],
        );
    }

    public function executionEndingBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->many(
            'WHERE d.execution_status = :in_progress AND d.execution_ends_at >= :from AND d.execution_ends_at < :to ORDER BY d.id',
            ['in_progress' => ExecutionStatus::InProgress, 'from' => $from, 'to' => $to],
        );
    }

    public function lots(int $buyComplaintId): array
    {
        return $this->manyLots('WHERE bl.test_buy_complaint_id = :decision ORDER BY bl.id', ['decision' => $buyComplaintId]);
    }

    public function lot(int $lotId): BuyComplaintLot
    {
        $row = $this->db->row('SELECT ' . BuyComplaintMapper::LOT_COLUMNS . ' FROM test_buy_complaint_lots bl WHERE bl.id = :id', ['id' => $lotId]);

        return $row === null ? throw new NotFound() : $this->mapper->lot($row);
    }

    public function ensureLot(int $buyComplaintId, int $buyLotId, DateTimeImmutable $now): int
    {
        $created = $this->db->value(
            'INSERT INTO test_buy_complaint_lots (test_buy_complaint_id, test_buy_lots_id, created_at, updated_at)'
            . ' VALUES (:decision, :buy_lot, :created, :updated)'
            . ' ON CONFLICT ON CONSTRAINT uq_buy_complaint_lots_complaint_buy_lot DO NOTHING RETURNING id',
            ['decision' => $buyComplaintId, 'buy_lot' => $buyLotId, 'created' => $now, 'updated' => $now],
        );
        if ($created !== null) {
            return $this->values->int($created);
        }

        return $this->values->int($this->db->value(
            'SELECT id FROM test_buy_complaint_lots WHERE test_buy_complaint_id = :decision AND test_buy_lots_id = :buy_lot',
            ['decision' => $buyComplaintId, 'buy_lot' => $buyLotId],
        ));
    }

    public function setDecisionType(int $lotId, ?DecisionType $type, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE test_buy_complaint_lots SET decision_type = :type, updated_at = :now WHERE id = :id',
            ['type' => $type, 'now' => $now, 'id' => $lotId],
        );
    }

    public function moveLotExecution(int $lotId, ?LotExecutionStatus $from, LotExecutionStatus $to, DateTimeImmutable $at, ?int $actorUserId): bool
    {
        $sets = ['execution_status = :to', 'updated_at = :updated'];
        $params = ['to' => $to, 'updated' => $at, 'id' => $lotId];
        if ($to === LotExecutionStatus::Started) {
            $sets[] = 'execution_started_at = :started';
            $params['started'] = $at;
        }
        if ($to === LotExecutionStatus::Executed) {
            $sets[] = 'execution_started_at = COALESCE(execution_started_at, :started)';
            $sets[] = 'executed_at = :executed';
            $sets[] = 'executed_by_user_id = :actor';
            $params['started'] = $at;
            $params['executed'] = $at;
            $params['actor'] = $actorUserId;
        }

        $condition = 'execution_status IS NULL';
        if ($from !== null) {
            $condition = 'execution_status = :from';
            $params['from'] = $from;
        }

        return $this->db->execute(
            'UPDATE test_buy_complaint_lots SET ' . implode(', ', $sets) . ' WHERE id = :id AND ' . $condition,
            $params,
        ) === 1;
    }

    public function lotsUnderRevision(): array
    {
        return $this->manyLots('WHERE bl.execution_status = :started ORDER BY bl.id', ['started' => LotExecutionStatus::Started]);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws NotFound
     */
    private function one(string $condition, array $params): BuyComplaint
    {
        $row = $this->db->row('SELECT ' . BuyComplaintMapper::COLUMNS . ' FROM test_buy_complaint d ' . $condition, $params);

        return $row === null ? throw new NotFound() : $this->mapper->decision($row);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<BuyComplaint>
     */
    private function many(string $condition, array $params): array
    {
        return array_map(
            fn (array $row): BuyComplaint => $this->mapper->decision($row),
            $this->db->rows('SELECT ' . BuyComplaintMapper::COLUMNS . ' FROM test_buy_complaint d ' . $condition, $params),
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return list<BuyComplaintLot>
     */
    private function manyLots(string $condition, array $params): array
    {
        return array_map(
            fn (array $row): BuyComplaintLot => $this->mapper->lot($row),
            $this->db->rows('SELECT ' . BuyComplaintMapper::LOT_COLUMNS . ' FROM test_buy_complaint_lots bl ' . $condition, $params),
        );
    }
}
