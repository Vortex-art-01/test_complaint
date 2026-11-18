<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Domain\Shared\Period;
use DateTimeImmutable;

/**
 * Данные нового решения по жалобам (цикла рассмотрения).
 */
final readonly class BuyComplaintCycle
{
    public function __construct(
        public int $buyId,
        public int $authorityOrgId,
        public DateTimeImmutable $protocolPublishedAt,
        public Period $appealPeriod,
        public Period $reviewPeriod,
    ) {
    }
}
