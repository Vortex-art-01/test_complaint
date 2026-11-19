<?php

declare(strict_types=1);

namespace Complaints\Domain\BuyComplaint;

use DateTimeImmutable;

/**
 * Решение по лоту закупки и его исполнение (снимок строки trd_complaint_lots).
 *
 * Статус исполнения следует из дат: организатор начал исполнение
 * (отмена или запуск пересмотра) — executionStartedAt, лот исполнен — executedAt.
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
        public ?int $executionStartedByUserId,
        public ?DateTimeImmutable $executedAt,
    ) {
    }

    public function isAwaitingExecution(DecisionType $type): bool
    {
        return $this->decisionType === $type && $this->executionStatus === LotExecutionStatus::Pending;
    }
}
