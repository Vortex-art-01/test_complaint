<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

/**
 * Право подать жалобу по статусу заявки и лота заявки.
 */
interface ApplicationEligibility
{
    /**
     * Даёт ли статус заявки и её лота право на жалобу по этому лоту.
     */
    public function isEligible(int $appId, int $appLotId): bool;
}
