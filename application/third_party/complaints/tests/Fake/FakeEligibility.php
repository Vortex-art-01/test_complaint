<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Port\ApplicationEligibility;

final class FakeEligibility implements ApplicationEligibility
{
    /** @var list<int> лоты заявок без права на жалобу */
    public array $denied = [];

    public function isEligible(int $appId, int $appLotId): bool
    {
        return !in_array($appLotId, $this->denied, true);
    }
}
