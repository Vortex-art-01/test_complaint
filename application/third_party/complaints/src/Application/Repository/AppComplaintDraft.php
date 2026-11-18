<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Domain\Shared\Period;
use DateTimeImmutable;

/**
 * Данные нового черновика жалобы.
 */
final readonly class AppComplaintDraft
{
    public function __construct(
        public int $buyId,
        public int $appId,
        public int $supplierOrgId,
        public int $authorUserId,
        public int $headUserId,
        public DateTimeImmutable $protocolPublishedAt,
        public Period $appealPeriod,
    ) {
    }
}
