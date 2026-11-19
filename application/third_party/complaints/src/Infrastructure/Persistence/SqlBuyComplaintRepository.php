<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Application\Access\Actor;
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

/**
 * Решения по жалобам: trd_complaint, его участники, сроки и лоты.
 */
final class SqlBuyComplaintRepository implements BuyComplaintRepository
{
    private const MOMENT_CHANGES = ['sent_for_approval_at', 'coordinated_at', 'approved_at', 'recalled_at'];

    /** Кто отправил и кто отозвал: пользователь и его организация. */
    private const ACTOR_CHANGES = ['sent_by', 'recalled_by'];

    /** Исполнение лота не начато. */
    private const LOT_PENDING = 'execution_started_at IS NULL AND executed_at IS NULL';

    /** Пересмотр итогов запущен и не завершён. */
    private const LOT_STARTED = 'execution_started_at IS NOT NULL AND executed_at IS NULL';

    /** Решение утверждено, и тип решения по лоту требует действий организатора. */
    private const LOT_UNDER_EXECUTION = 'decision_id IN (' . ReferenceIds::LOT_REQUIRES_EXECUTION . ')'
        . ' AND EXISTS (SELECT 1 FROM trd_complaint x WHERE x.id = trd_complaint_lots.trd_complaint_id'
        . ' AND x.status_id IN (' . ReferenceIds::DECISION_APPROVED . '))';

    public function __construct(
        private readonly Database $db,
        private readonly BuyComplaintMapper $mapper,
        private readonly SqlValues $values,
    ) {
    }

    public function get(int $id): BuyComplaint
    {
        return $this->one('WHERE d.id = :id AND d.deleted_at IS NULL', ['id' => $id]);
    }

    public function lock(int $id): BuyComplaint
    {
        return $this->one('WHERE d.id = :id AND d.deleted_at IS NULL FOR UPDATE OF d', ['id' => $id]);
    }

    public function create(BuyComplaintCycle $decision, DateTimeImmutable $now): ?int
    {
        // Одно решение на закупку и срок обжалования: сбор закупки идёт по очереди.
        $this->db->value(
            "SELECT pg_advisory_xact_lock(hashtext('complaints.buy'), CAST(CAST(:id AS bigint) % 2147483647 AS integer))",
            ['id' => $decision->buyId],
        );
        if ($this->findByBuyAndAppealEnd($decision->buyId, $decision->appealPeriod->endsAt) !== null) {
            return null;
        }

        $id = $this->values->int($this->db->value(
            'INSERT INTO trd_complaint (trd_buy_id, status_id, crsubject_id) VALUES (:buy, :status, :authority) RETURNING id',
            ['buy' => $decision->buyId, 'status' => ReferenceIds::DECISION_IN_REVIEW, 'authority' => $decision->authorityOrgId],
        ));
        $this->addPeriod($id, ReferenceIds::DATE_APPEAL, $decision->appealPeriod, $now);
        $this->addPeriod($id, ReferenceIds::DATE_REVIEW, $decision->reviewPeriod, $now);

        return $id;
    }

    public function findByBuyAndAppealEnd(int $buyId, DateTimeImmutable $appealEndsAt): ?BuyComplaint
    {
        $row = $this->db->row(
            'SELECT ' . BuyComplaintMapper::COLUMNS . ' FROM ' . BuyComplaintMapper::FROM
            . ' WHERE d.trd_buy_id = :buy AND ap.end_at = :appeal_end AND d.deleted_at IS NULL ORDER BY d.id LIMIT 1',
            ['buy' => $buyId, 'appeal_end' => $appealEndsAt],
        );

        return $row === null ? null : $this->mapper->decision($row);
    }

    public function transition(int $id, BuyComplaintStatus $from, BuyComplaintStatus $to, DateTimeImmutable $now, array $changes = []): bool
    {
        $sets = [];
        $params = ['id' => $id];
        $executionPeriod = null;
        foreach ($changes as $key => $value) {
            if (in_array($key, self::MOMENT_CHANGES, true)) {
                $sets[] = $key . ' = :' . $key;
                $params[$key] = $value;
            } elseif (in_array($key, self::ACTOR_CHANGES, true) && $value instanceof Actor) {
                $sets[] = $key . '_user_id = :' . $key . '_user';
                $sets[] = $key . '_subject_id = :' . $key . '_subject';
                $params[$key . '_user'] = $value->userId;
                $params[$key . '_subject'] = $value->organizationId;
            } elseif ($key === 'execution_period' && $value instanceof Period) {
                $executionPeriod = $value;
            } elseif ($key !== 'execution_status') {
                throw new LogicException('Неизвестное поле перехода решения: ' . $key);
            }
        }
        $sets[] = 'status_id = :to';
        $params['to'] = $this->statusAfter($to, $changes['execution_status'] ?? null);

        $moved = $this->db->execute(
            'UPDATE trd_complaint SET ' . implode(', ', $sets)
            . ' WHERE id = :id AND deleted_at IS NULL AND status_id IN (' . implode(', ', ReferenceIds::decisionStatuses($from)) . ')',
            $params,
        ) === 1;
        if ($moved && $executionPeriod !== null) {
            $this->addPeriod($id, ReferenceIds::DATE_EXECUTION, $executionPeriod, $now);
        }

        return $moved;
    }

    public function claim(int $id, Actor $reviewer, DateTimeImmutable $now): bool
    {
        // Рассматривающий закрепляет решение за собой сам.
        return $this->addMember($id, ReferenceIds::ROLE_REVIEWER, $reviewer, $reviewer, $now, true);
    }

    public function release(int $id, DateTimeImmutable $now): void
    {
        $this->removeMembers($id, [ReferenceIds::ROLE_REVIEWER]);
    }

    public function assignRoute(int $id, ?Actor $coordinator, Actor $approver, Actor $assignedBy, DateTimeImmutable $now): void
    {
        $this->removeMembers($id, [ReferenceIds::ROLE_COORDINATOR, ReferenceIds::ROLE_APPROVER]);
        if ($coordinator !== null) {
            $this->addMember($id, ReferenceIds::ROLE_COORDINATOR, $coordinator, $assignedBy, $now, false);
        }
        $this->addMember($id, ReferenceIds::ROLE_APPROVER, $approver, $assignedBy, $now, false);
    }

    public function resetRoute(int $id, DateTimeImmutable $now): void
    {
        $this->removeMembers($id, [ReferenceIds::ROLE_COORDINATOR, ReferenceIds::ROLE_APPROVER]);
        $this->db->execute(
            'UPDATE trd_complaint SET sent_for_approval_at = NULL, sent_by_user_id = NULL, sent_by_subject_id = NULL,'
            . ' coordinated_at = NULL WHERE id = :id',
            ['id' => $id],
        );
    }

    public function setReviewMethod(int $id, ReviewMethod $method, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE trd_complaint SET method_id = :method WHERE id = :id',
            ['method' => ReferenceIds::reviewMethod($method), 'id' => $id],
        );
    }

    public function markExecuted(int $id, DateTimeImmutable $now): bool
    {
        return $this->db->execute(
            'UPDATE trd_complaint SET status_id = :executed WHERE id = :id AND status_id = :in_progress',
            [
                'executed' => ReferenceIds::DECISION_APPROVED_EXECUTED,
                'id' => $id,
                'in_progress' => ReferenceIds::DECISION_APPROVED_IN_PROGRESS,
            ],
        ) === 1;
    }

    public function reviewEndingBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->many(
            'WHERE d.deleted_at IS NULL AND d.status_id NOT IN (' . ReferenceIds::DECISION_APPROVED . ')'
            . ' AND rv.end_at >= :from AND rv.end_at < :to ORDER BY d.id',
            ['from' => $from, 'to' => $to],
        );
    }

    public function executionEndingBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->many(
            'WHERE d.deleted_at IS NULL AND d.status_id = :in_progress AND ex.end_at >= :from AND ex.end_at < :to ORDER BY d.id',
            ['in_progress' => ReferenceIds::DECISION_APPROVED_IN_PROGRESS, 'from' => $from, 'to' => $to],
        );
    }

    public function lots(int $buyComplaintId): array
    {
        return $this->manyLots('WHERE tl.trd_complaint_id = :decision ORDER BY tl.id', ['decision' => $buyComplaintId]);
    }

    public function lot(int $lotId): BuyComplaintLot
    {
        $row = $this->db->row(
            'SELECT ' . BuyComplaintMapper::LOT_COLUMNS . ' FROM ' . BuyComplaintMapper::LOT_FROM . ' WHERE tl.id = :id',
            ['id' => $lotId],
        );

        return $row === null ? throw new NotFound() : $this->mapper->lot($row);
    }

    public function ensureLot(int $buyComplaintId, int $buyLotId, DateTimeImmutable $now): int
    {
        $created = $this->db->value(
            'INSERT INTO trd_complaint_lots (trd_complaint_id, trd_buy_lot_id, created_at) VALUES (:decision, :buy_lot, :created)'
            . ' ON CONFLICT ON CONSTRAINT uq_trd_complaint_lots_buy_lot DO NOTHING RETURNING id',
            ['decision' => $buyComplaintId, 'buy_lot' => $buyLotId, 'created' => $now],
        );
        if ($created !== null) {
            return $this->values->int($created);
        }

        return $this->values->int($this->db->value(
            'SELECT id FROM trd_complaint_lots WHERE trd_complaint_id = :decision AND trd_buy_lot_id = :buy_lot',
            ['decision' => $buyComplaintId, 'buy_lot' => $buyLotId],
        ));
    }

    public function refreshCounts(int $id): void
    {
        $this->db->execute(
            'UPDATE trd_complaint_lots tl SET app_complaint_count = ('
            . ' SELECT count(*) FROM app_complaint_lots l JOIN app_complaint c ON c.id = l.app_complaint_id'
            . ' WHERE l.trd_complaint_lots_id = tl.id AND c.deleted_at IS NULL)'
            . ' WHERE tl.trd_complaint_id = :decision',
            ['decision' => $id],
        );
        $this->db->execute(
            'UPDATE trd_complaint d SET'
            . ' lots_count = (SELECT count(*) FROM trd_complaint_lots tl WHERE tl.trd_complaint_id = d.id),'
            . ' app_complaint_count = (SELECT COALESCE(sum(tl.app_complaint_count), 0) FROM trd_complaint_lots tl'
            . ' WHERE tl.trd_complaint_id = d.id)'
            . ' WHERE d.id = :id',
            ['id' => $id],
        );
    }

    public function setDecisionType(int $lotId, ?DecisionType $type, DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE trd_complaint_lots SET decision_id = :type WHERE id = :id',
            ['type' => $type === null ? null : ReferenceIds::decisionType($type), 'id' => $lotId],
        );
    }

    public function moveLotExecution(int $lotId, ?LotExecutionStatus $from, LotExecutionStatus $to, DateTimeImmutable $at, ?Actor $actor): bool
    {
        $params = ['id' => $lotId];
        $sql = match (true) {
            // При утверждении писать нечего: «ждёт исполнения» следует из статуса решения и типа.
            $from === null && $to === LotExecutionStatus::Pending => 'SELECT count(*) FROM trd_complaint_lots'
                . ' WHERE id = :id AND ' . self::LOT_PENDING . ' AND ' . self::LOT_UNDER_EXECUTION,
            $from === LotExecutionStatus::Pending && $to === LotExecutionStatus::Started => 'UPDATE trd_complaint_lots'
                . ' SET execution_started_at = :started, execution_started_by_user_id = :user, execution_started_by_subject_id = :subject'
                . ' WHERE id = :id AND ' . self::LOT_PENDING . ' AND ' . self::LOT_UNDER_EXECUTION,
            $from === LotExecutionStatus::Pending && $to === LotExecutionStatus::Executed => 'UPDATE trd_complaint_lots'
                . ' SET execution_started_at = :started, execution_started_by_user_id = :user, execution_started_by_subject_id = :subject,'
                . ' executed_at = :executed'
                . ' WHERE id = :id AND ' . self::LOT_PENDING . ' AND ' . self::LOT_UNDER_EXECUTION,
            $from === LotExecutionStatus::Started && $to === LotExecutionStatus::Executed => 'UPDATE trd_complaint_lots'
                . ' SET executed_at = :executed WHERE id = :id AND ' . self::LOT_STARTED . ' AND ' . self::LOT_UNDER_EXECUTION,
            default => throw new LogicException(sprintf(
                'Нет перехода исполнения лота %s -> %s.',
                $from === null ? 'null' : $from->value,
                $to->value,
            )),
        };
        if ($from === LotExecutionStatus::Pending) {
            $params += ['started' => $at, 'user' => $actor?->userId, 'subject' => $actor?->organizationId];
        }
        if ($to === LotExecutionStatus::Executed) {
            $params['executed'] = $at;
        }

        if ($from === null) {
            return $this->values->int($this->db->value($sql, $params)) === 1;
        }

        return $this->db->execute($sql, $params) === 1;
    }

    public function lotsUnderRevision(): array
    {
        return $this->manyLots(
            'WHERE tl.execution_started_at IS NOT NULL AND tl.executed_at IS NULL'
            . ' AND tl.decision_id IN (' . ReferenceIds::LOT_REQUIRES_EXECUTION . ')'
            . ' AND tld.status_id IN (' . ReferenceIds::DECISION_APPROVED . ') ORDER BY tl.id',
            [],
        );
    }

    /**
     * Статус решения в справочнике: у утверждённого он зависит от исполнения.
     */
    private function statusAfter(BuyComplaintStatus $to, mixed $execution): int
    {
        if ($to !== BuyComplaintStatus::Approved) {
            return ReferenceIds::decisionStatus($to);
        }
        if (!$execution instanceof ExecutionStatus) {
            throw new LogicException('Утверждение решения требует статуса исполнения (execution_status).');
        }

        return ReferenceIds::approvedStatus($execution);
    }

    private function addPeriod(int $id, int $dateType, Period $period, DateTimeImmutable $now): void
    {
        // Название срока копируется из справочника видов сроков.
        $this->db->execute(
            'INSERT INTO trd_complaint_date (date_type_id, trd_complaint_id, name_ru, name_kz, start_at, end_at, created_at)'
            . ' SELECT t.id, :decision, t.name_ru, t.name_kz, :starts, :ends, :created FROM ref_trd_complaint_date_type t WHERE t.id = :type',
            ['decision' => $id, 'starts' => $period->startsAt, 'ends' => $period->endsAt, 'created' => $now, 'type' => $dateType],
        );
    }

    /**
     * @param bool $onlyIfFree true — роль уже занята: строка не добавляется
     */
    private function addMember(int $id, int $role, Actor $member, Actor $assignedBy, DateTimeImmutable $now, bool $onlyIfFree): bool
    {
        $added = $this->db->value(
            'INSERT INTO trd_complaint_member (trd_complaint_id, ref_trd_complaint_role_id, member_id, member_subject_id,'
            . ' assigned_by_user_id, assigned_by_subject_id, created_at)'
            . ' VALUES (:decision, :role, :member, :member_subject, :assigned_by, :assigned_by_subject, :created)'
            . ($onlyIfFree ? ' ON CONFLICT ON CONSTRAINT uq_trd_complaint_member_role DO NOTHING' : '')
            . ' RETURNING id',
            [
                'decision' => $id,
                'role' => $role,
                'member' => $member->userId,
                'member_subject' => $member->organizationId,
                'assigned_by' => $assignedBy->userId,
                'assigned_by_subject' => $assignedBy->organizationId,
                'created' => $now,
            ],
        );

        return $added !== null;
    }

    /**
     * @param list<int> $roles
     */
    private function removeMembers(int $id, array $roles): void
    {
        $this->db->execute(
            'DELETE FROM trd_complaint_member WHERE trd_complaint_id = :decision'
            . ' AND ref_trd_complaint_role_id = ANY (CAST(:roles AS bigint[]))',
            ['decision' => $id, 'roles' => Database::intArray($roles)],
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws NotFound
     */
    private function one(string $condition, array $params): BuyComplaint
    {
        $row = $this->db->row('SELECT ' . BuyComplaintMapper::COLUMNS . ' FROM ' . BuyComplaintMapper::FROM . ' ' . $condition, $params);

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
            $this->db->rows('SELECT ' . BuyComplaintMapper::COLUMNS . ' FROM ' . BuyComplaintMapper::FROM . ' ' . $condition, $params),
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
            $this->db->rows('SELECT ' . BuyComplaintMapper::LOT_COLUMNS . ' FROM ' . BuyComplaintMapper::LOT_FROM . ' ' . $condition, $params),
        );
    }
}
