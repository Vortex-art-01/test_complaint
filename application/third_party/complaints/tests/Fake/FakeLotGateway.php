<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Port\LotDecisionAction;
use Complaints\Application\Port\ProcurementLotGateway;
use DateTimeImmutable;

final class FakeLotGateway implements ProcurementLotGateway
{
    /** @var array<int, DateTimeImmutable> */
    public array $protocols = [];

    /** @var array<int, DateTimeImmutable> */
    public array $revisionsCompleted = [];

    /** @var list<LotDecisionAction> */
    public array $cancelled = [];

    /** @var list<LotDecisionAction> */
    public array $revised = [];

    public function resultsProtocolPublishedAt(int $buyLotId): ?DateTimeImmutable
    {
        return $this->protocols[$buyLotId] ?? null;
    }

    public function procurementStatusName(int $buyId): string
    {
        return 'На исполнении';
    }

    public function cancelLot(LotDecisionAction $action): void
    {
        $this->cancelled[] = $action;
    }

    public function returnLotForRevision(LotDecisionAction $action): void
    {
        $this->revised[] = $action;
    }

    public function revisionCompletedAt(int $buyLotId, DateTimeImmutable $since): ?DateTimeImmutable
    {
        $at = $this->revisionsCompleted[$buyLotId] ?? null;

        return $at !== null && $at > $since ? $at : null;
    }
}
