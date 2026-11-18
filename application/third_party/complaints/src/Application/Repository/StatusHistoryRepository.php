<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Domain\History\HistoryEntityType;
use DateTimeImmutable;

/**
 * Журнал переходов статусов: test_complaint_status_history, только INSERT.
 */
interface StatusHistoryRepository
{
    /**
     * @param int|null $actorUserId null — действие системы
     */
    public function append(
        HistoryEntityType $type,
        int $entityId,
        ?string $fromStatus,
        string $toStatus,
        ?int $actorUserId,
        DateTimeImmutable $now,
        ?string $comment = null,
    ): void;
}
