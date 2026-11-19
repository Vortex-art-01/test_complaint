<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

/**
 * Лот заявки проекта (trd_app_lot).
 */
final readonly class ApplicationLotCard
{
    public function __construct(
        public int $id,
        public int $appId,
        public int $buyLotId,
    ) {
    }
}
