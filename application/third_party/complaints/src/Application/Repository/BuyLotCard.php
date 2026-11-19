<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Shared\LocalizedText;

/**
 * Лот закупки проекта (trd_buy_lot).
 */
final readonly class BuyLotCard
{
    public function __construct(
        public int $id,
        public int $buyId,
        public string $number,
        public LocalizedText $name,
    ) {
    }
}
