<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\NewBuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\LotExecutionStatus;
use Complaints\Domain\BuyComplaint\ReviewMethod;
use DateTimeImmutable;

/**
 * Решения в памяти: повторяет условные UPDATE и уникальные ключи SQL-репозитория.
 */
final class InMemoryBuyComplaints implements BuyComplaintRepository
{
    /** @var array<int, array<string, mixed>> */
    public array $decisions = [];

    /** @var array<int, array<string, mixed>> */
    public array $lots = [];

    public function get(int $id): BuyComplaint
    {
        $row = $this->decisions[$id] ?? throw new NotFound();

        return new BuyComplaint(
            $row['id'],
            $row['buy_id'],
            $row['authority_org_id'],
            $row['protocol_published_at'],
            $row['appeal_period'],
            $row['review_period'],
            $row['status'],
            $row['review_method'],
            $row['reviewer_user_id'],
            $row['coordinator_user_id'],
            $row['approver_user_id'],
            $row['sent_for_approval_at'],
            $row['decision_number'],
            $row['approved_at'],
            $row['execution_status'],
            $row['execution_period'],
            $row['executed_at'],
        );
    }

    public function lock(int $id): BuyComplaint
    {
        return $this->get($id);
    }

    public function create(NewBuyComplaint $decision, DateTimeImmutable $now): ?int
    {
        if ($this->findByBuyAndAppealEnd($decision->buyId, $decision->appealPeriod->endsAt) !== null) {
            return null;
        }

        $id = count($this->decisions) + 1;
        $this->decisions[$id] = [
            'id' => $id,
            'buy_id' => $decision->buyId,
            'authority_org_id' => $decision->authorityOrgId,
            'protocol_published_at' => $decision->protocolPublishedAt,
            'appeal_period' => $decision->appealPeriod,
            'review_period' => $decision->reviewPeriod,
            'status' => BuyComplaintStatus::InReview,
            'review_method' => null,
            'reviewer_user_id' => null,
            'coordinator_user_id' => null,
            'approver_user_id' => null,
            'sent_for_approval_at' => null,
            'decision_number' => null,
            'approved_at' => null,
            'execution_status' => null,
            'execution_period' => null,
            'executed_at' => null,
        ];

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
            $this->decisions[$id][$key] = $value;
        }

        return true;
    }

    public function claim(int $id, int $reviewerUserId, DateTimeImmutable $now): bool
    {
        if ($this->decisions[$id]['reviewer_user_id'] !== null) {
            return false;
        }
        $this->decisions[$id]['reviewer_user_id'] = $reviewerUserId;

        return true;
    }

    public function release(int $id, DateTimeImmutable $now): void
    {
        $this->decisions[$id]['reviewer_user_id'] = null;
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
        $this->decisions[$id]['executed_at'] = $now;

        return true;
    }

    public function lockBuy(int $buyId): void
    {
    }

    public function countNumberedForBuy(int $buyId): int
    {
        return count(array_filter(
            $this->decisions,
            static fn (array $row): bool => $row['buy_id'] === $buyId && $row['decision_number'] !== null,
        ));
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
            $row['execution_status'],
            $row['execution_started_at'],
            $row['executed_at'],
            $row['executed_by_user_id'],
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
            'execution_status' => null,
            'execution_started_at' => null,
            'executed_at' => null,
            'executed_by_user_id' => null,
        ];

        return $id;
    }

    public function setDecisionType(int $lotId, ?DecisionType $type, DateTimeImmutable $now): void
    {
        $this->lots[$lotId]['decision_type'] = $type;
    }

    public function moveLotExecution(int $lotId, ?LotExecutionStatus $from, LotExecutionStatus $to, DateTimeImmutable $at, ?int $actorUserId): bool
    {
        if ($this->lots[$lotId]['execution_status'] !== $from) {
            return false;
        }

        $this->lots[$lotId]['execution_status'] = $to;
        if ($to !== LotExecutionStatus::Pending) {
            $this->lots[$lotId]['execution_started_at'] ??= $at;
        }
        if ($to === LotExecutionStatus::Executed) {
            $this->lots[$lotId]['executed_at'] = $at;
            $this->lots[$lotId]['executed_by_user_id'] = $actorUserId;
        }

        return true;
    }

    public function lotsUnderRevision(): array
    {
        $found = [];
        foreach ($this->lots as $row) {
            if ($row['execution_status'] === LotExecutionStatus::Started) {
                $found[] = $this->lot($row['id']);
            }
        }

        return $found;
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
