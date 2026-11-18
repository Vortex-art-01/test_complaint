<?php

declare(strict_types=1);

namespace Complaints\Domain\BuyComplaint;

use DateTimeImmutable;

/**
 * Решение по лоту закупки и его исполнение (снимок строки test_buy_complaint_lots).
 */
final readonly class BuyComplaintLot
{
    public function __construct(
        public int $id,
        public int $buyComplaintId,
        public int $buyLotId,
        public ?DecisionType $decisionType,
        public ?LotExecutionStatus $executionStatus,
        public ?DateTimeImmutable $executionStartedAt,
        public ?DateTimeImmutable $executedAt,
        public ?int $executedByUserId,
    ) {
    }

    public function isAwaitingExecution(DecisionType $type): bool
    {
        return $this->decisionType === $type && $this->executionStatus === LotExecutionStatus::Pending;
    }
}
