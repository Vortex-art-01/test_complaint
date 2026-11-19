<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

/**
 * Черновик жалобы к созданию: закупка, заявка, организация, автор и руководитель-подписант.
 */
final readonly class AppComplaintDraft
{
    public function __construct(
        public int $buyId,
        public int $appId,
        public int $supplierOrgId,
        public int $authorUserId,
        public int $headUserId,
    ) {
    }
}
