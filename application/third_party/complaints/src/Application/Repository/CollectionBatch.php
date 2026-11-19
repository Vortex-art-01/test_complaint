<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Domain\Shared\Period;

/**
 * Закупка с поданными жалобами, срок обжалования которой закончился.
 */
final readonly class CollectionBatch
{
    public function __construct(
        public int $buyId,
        public Period $appealPeriod,
    ) {
    }
}
