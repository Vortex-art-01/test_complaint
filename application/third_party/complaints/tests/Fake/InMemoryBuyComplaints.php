<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

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
use DateTimeImmutable;
use LogicException;

/**
 * Решения в памяти с теми же правилами, что SQL-репозиторий: участники
 * маршрута отдельно от решения, исполнение лота следует из дат.
 */
final class InMemoryBuyComplaints implements BuyComplaintRepository
{
    private const ROUTE_CHANGES = ['sent_for_approval_at', 'coordinated_at', 'approved_at', 'recalled_at', 'execution_status', 'execution_period'];

    /** @var array<int, array<string, mixed>> */
    public array $decisions = [];

    /** @var array<int, array<string, mixed>> */
    public array $lots = [];

    /** @var array<int, array<string, Actor>> решение => роль => участник */
    public array $members = [];

    /** @var array<int, array<string, Actor>> решение => роль => кто назначил */
    public array $assignedBy = [];

    public ?InMemoryAppComplaints $complaints = null;

    public function get(int $id): BuyComplaint
    {
        $row = $this->decisions[$id] ?? throw new NotFound();

        return new BuyComplaint(
            $row['id'],
            $row['buy_id'],
            $row['authority_org_id'],
            $row['appeal_period'],
            $row['review_period'],
            $row['status'],
            $row['review_method'],
            $this->members[$id]['reviewer']->userId ?? null,
            $this->members[$id]['coordinator']->userId ?? null,
            $this->members[$id]['approver']->userId ?? null,
            $row['sent_for_approval_at'],
            $row['sent_by']?->userId,
            $row['coordinated_at'],
            $row['approved_at'],
            $row['recalled_at'],
            $row['recalled_by']?->userId,
            $row['execution_status'],
            $row['execution_period'],
            $row['execution_status'] === ExecutionStatus::Executed ? $this->lastExecutedAt($id) : null,
        );
    }

    public function lock(int $id): BuyComplaint
    {
        return $this->get($id);
    }

    public function create(BuyComplaintCycle $decision, DateTimeImmutable $now): ?int
    {
        if ($this->findByBuyAndAppealEnd($decision->buyId, $decision->appealPeriod->endsAt) !== null) {
            return null;
        }

        $id = count($this->decisions) + 1;
        $this->decisions[$id] = [
            'id' => $id,
            'buy_id' => $decision->buyId,
            'authority_org_id' => $decision->authorityOrgId,
            'appeal_period' => $decision->appealPeriod,
            'review_period' => $decision->reviewPeriod,
            'status' => BuyComplaintStatus::InReview,
            'review_method' => null,
            'sent_for_approval_at' => null,
            'sent_by' => null,
            'coordinated_at' => null,
            'approved_at' => null,
            'recalled_at' => null,
            'recalled_by' => null,
            'execution_status' => null,
            'execution_period' => null,
            'lots_count' => 0,
            'app_complaint_count' => 0,
        ];
        $this->members[$id] = [];

        return $id;
    }

    public function findByBuyAndAppealEnd(int $buyId, DateTimeImmutable $appealEndsAt): ?BuyComplaint
    {
        foreach ($this->decisions as $row) {
            if ($row['buy_id'] === $buyId && $row['appeal_period']->endsAt == $appealEndsAt) {
                return $this->get($row['id']);
            }
        }

        return null;
    }

    public function transition(int $id, BuyComplaintStatus $from, BuyComplaintStatus $to, DateTimeImmutable $now, array $changes = []): bool
    {
        if (($this->decisions[$id]['status'] ?? null) !== $from) {
            return false;
        }

        $this->decisions[$id]['status'] = $to;
        foreach ($changes as $key => $value) {
            if (!in_array($key, [...self::ROUTE_CHANGES, 'sent_by', 'recalled_by'], true)) {
                throw new LogicException('Неизвестное поле перехода решения: ' . $key);
            }
            $this->decisions[$id][$key] = $value;
        }

        return true;
    }

    public function claim(int $id, Actor $reviewer, DateTimeImmutable $now): bool
    {
        if (isset($this->members[$id]['reviewer'])) {
            return false;
        }
        $this->members[$id]['reviewer'] = $reviewer;
        $this->assignedBy[$id]['reviewer'] = $reviewer;

        return true;
    }

    public function release(int $id, DateTimeImmutable $now): void
    {
        unset($this->members[$id]['reviewer'], $this->assignedBy[$id]['reviewer']);
    }

    public function assignRoute(int $id, ?Actor $coordinator, Actor $approver, Actor $assignedBy, DateTimeImmutable $now): void
    {
        unset($this->members[$id]['coordinator'], $this->assignedBy[$id]['coordinator']);
        if ($coordinator !== null) {
            $this->members[$id]['coordinator'] = $coordinator;
            $this->assignedBy[$id]['coordinator'] = $assignedBy;
        }
        $this->members[$id]['approver'] = $approver;
        $this->assignedBy[$id]['approver'] = $assignedBy;
    }

    public function resetRoute(int $id, DateTimeImmutable $now): void
    {
        unset(
            $this->members[$id]['coordinator'],
            $this->members[$id]['approver'],
            $this->assignedBy[$id]['coordinator'],
            $this->assignedBy[$id]['approver'],
        );
        $this->decisions[$id]['sent_for_approval_at'] = null;
        $this->decisions[$id]['sent_by'] = null;
        $this->decisions[$id]['coordinated_at'] = null;
    }

    public function setReviewMethod(int $id, ReviewMethod $method, DateTimeImmutable $now): void
    {
        $this->decisions[$id]['review_method'] = $method;
    }

    public function markExecuted(int $id, DateTimeImmutable $now): bool
    {
        if ($this->decisions[$id]['execution_status'] !== ExecutionStatus::InProgress) {
            return false;
        }
        $this->decisions[$id]['execution_status'] = ExecutionStatus::Executed;

        return true;
    }

    public function reviewEndingBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->decisionsWhere(static fn (array $row): bool => $row['status'] !== BuyComplaintStatus::Approved
            && $row['review_period']->endsAt >= $from && $row['review_period']->endsAt < $to);
    }

    public function executionEndingBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->decisionsWhere(static fn (array $row): bool => $row['execution_status'] === ExecutionStatus::InProgress
            && $row['execution_period'] !== null
            && $row['execution_period']->endsAt >= $from && $row['execution_period']->endsAt < $to);
    }

    public function lots(int $buyComplaintId): array
    {
        $found = [];
        foreach ($this->lots as $row) {
            if ($row['buy_complaint_id'] === $buyComplaintId) {
                $found[] = $this->lot($row['id']);
            }
        }

        return $found;
    }

    public function lot(int $lotId): BuyComplaintLot
    {
        $row = $this->lots[$lotId] ?? throw new NotFound();

        return new BuyComplaintLot(
            $row['id'],
            $row['buy_complaint_id'],
            $row['buy_lot_id'],
            $row['decision_type'],
            $this->executionStatus($row),
            $row['execution_started_at'],
            $row['execution_started_by']?->userId,
            $row['executed_at'],
        );
    }

    public function ensureLot(int $buyComplaintId, int $buyLotId, DateTimeImmutable $now): int
    {
        foreach ($this->lots as $row) {
            if ($row['buy_complaint_id'] === $buyComplaintId && $row['buy_lot_id'] === $buyLotId) {
                return $row['id'];
            }
        }

        $id = count($this->lots) + 1;
        $this->lots[$id] = [
            'id' => $id,
            'buy_complaint_id' => $buyComplaintId,
            'buy_lot_id' => $buyLotId,
            'decision_type' => null,
            'app_complaint_count' => 0,
            'execution_started_at' => null,
            'execution_started_by' => null,
            'executed_at' => null,
        ];

        return $id;
    }

    public function refreshCounts(int $id): void
    {
        $complaints = $this->complaints ?? throw new LogicException('InMemoryBuyComplaints::$complaints не задан.');
        $total = 0;
        foreach ($this->lots as $lotId => $row) {
            if ($row['buy_complaint_id'] !== $id) {
                continue;
            }
            $count = count($complaints->lotsOfBuyComplaintLot($lotId));
            $this->lots[$lotId]['app_complaint_count'] = $count;
            $total += $count;
        }
        $this->decisions[$id]['lots_count'] = count($this->lots($id));
        $this->decisions[$id]['app_complaint_count'] = $total;
    }

    public function setDecisionType(int $lotId, ?DecisionType $type, DateTimeImmutable $now): void
    {
        $this->lots[$lotId]['decision_type'] = $type;
    }

    public function moveLotExecution(int $lotId, ?LotExecutionStatus $from, LotExecutionStatus $to, DateTimeImmutable $at, ?Actor $actor): bool
    {
        $row = $this->lots[$lotId];
        if ($to === LotExecutionStatus::Pending) {
            return $from === null && $row['execution_started_at'] === null;
        }
        if ($this->executionStatus($row) !== $from) {
            return false;
        }

        if ($from === LotExecutionStatus::Pending) {
            $this->lots[$lotId]['execution_started_at'] = $at;
            $this->lots[$lotId]['execution_started_by'] = $actor;
        }
        if ($to === LotExecutionStatus::Executed) {
            $this->lots[$lotId]['executed_at'] = $at;
        }

        return true;
    }

    public function lotsUnderRevision(): array
    {
        $found = [];
        foreach ($this->lots as $row) {
            if ($this->executionStatus($row) === LotExecutionStatus::Started) {
                $found[] = $this->lot($row['id']);
            }
        }

        return $found;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function executionStatus(array $row): ?LotExecutionStatus
    {
        $decision = $this->decisions[$row['buy_complaint_id']];
        if ($decision['status'] !== BuyComplaintStatus::Approved
            || $row['decision_type'] === null
            || !$row['decision_type']->requiresExecution()) {
            return null;
        }

        return match (true) {
            $row['executed_at'] !== null => LotExecutionStatus::Executed,
            $row['execution_started_at'] !== null => LotExecutionStatus::Started,
            default => LotExecutionStatus::Pending,
        };
    }

    private function lastExecutedAt(int $id): ?DateTimeImmutable
    {
        $last = null;
        foreach ($this->lots as $row) {
            if ($row['buy_complaint_id'] === $id && $row['executed_at'] !== null && ($last === null || $row['executed_at'] > $last)) {
                $last = $row['executed_at'];
            }
        }

        return $last;
    }

    /**
     * @param callable(array<string, mixed>): bool $condition
     * @return list<BuyComplaint>
     */
    private function decisionsWhere(callable $condition): array
    {
        $found = [];
        foreach ($this->decisions as $row) {
            if ($condition($row)) {
                $found[] = $this->get($row['id']);
            }
        }

        return $found;
    }
}
