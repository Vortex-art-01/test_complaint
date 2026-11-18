<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

/**
 * Лот заявки из test_app_lots.
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
