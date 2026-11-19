<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Domain\Shared\Period;

/**
 * Решение по жалобам к созданию: закупка и один цикл обжалования со сроками.
 */
final readonly class BuyComplaintCycle
{
    public function __construct(
        public int $buyId,
        public int $authorityOrgId,
        public Period $appealPeriod,
        public Period $reviewPeriod,
    ) {
    }
}
