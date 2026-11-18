<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

/**
 * Заявка поставщика из test_app.
 */
final readonly class ApplicationCard
{
    public function __construct(
        public int $id,
        public int $buyId,
        public int $supplierOrgId,
    ) {
    }
}
