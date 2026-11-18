<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Shared\LocalizedText;
use DateTimeImmutable;

/**
 * Закупка (объявление) из test_buy.
 */
final readonly class BuyCard
{
    public function __construct(
        public int $id,
        public string $number,
        public LocalizedText $name,
        public int $methodId,
        public int $organizerOrgId,
        public DateTimeImmutable $publishedAt,
    ) {
    }
}
