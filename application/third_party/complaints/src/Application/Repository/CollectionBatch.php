<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Domain\Shared\Period;
use DateTimeImmutable;

/**
 * Поданные жалобы одной закупки с одним окончанием срока обжалования —
 * будущий цикл рассмотрения.
 */
final readonly class CollectionBatch
{
    /**
     * @param DateTimeImmutable $protocolPublishedAt наиболее поздняя публикация протокола итогов в цикле
     */
    public function __construct(
        public int $buyId,
        public Period $appealPeriod,
        public DateTimeImmutable $protocolPublishedAt,
    ) {
    }
}
