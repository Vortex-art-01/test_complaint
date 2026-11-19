<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\ApplicationCard;
use Complaints\Application\Repository\ApplicationLotCard;
use Complaints\Application\Repository\BuyCard;
use Complaints\Application\Repository\BuyLotCard;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Domain\Shared\Period;

final class InMemoryProcurement implements ProcurementRepository
{
    /** @var array<int, BuyCard> */
    public array $buys = [];

    /** @var array<int, BuyLotCard> */
    public array $buyLots = [];

    /** @var array<int, ApplicationCard> */
    public array $applications = [];

    /** @var array<int, ApplicationLotCard> */
    public array $applicationLots = [];

    /**
     * Проект установил закупке новый срок обжалования (новый протокол итогов).
     */
    public function setAppealPeriod(int $buyId, ?Period $period): void
    {
        $buy = $this->buy($buyId);
        $this->buys[$buyId] = new BuyCard($buy->id, $buy->number, $buy->name, $buy->methodId, $buy->organizerOrgId, $buy->publishedAt, $period);
    }

    public function findBuyByNumber(string $number): ?BuyCard
    {
        foreach ($this->buys as $buy) {
            if ($buy->number === $number) {
                return $buy;
            }
        }

        return null;
    }

    public function buy(int $buyId): BuyCard
    {
        return $this->buys[$buyId] ?? throw new NotFound();
    }

    public function buyLot(int $buyLotId): BuyLotCard
    {
        return $this->buyLots[$buyLotId] ?? throw new NotFound();
    }

    public function buyLots(array $buyLotIds): array
    {
        $found = [];
        foreach ($buyLotIds as $id) {
            if (isset($this->buyLots[$id])) {
                $found[$id] = $this->buyLots[$id];
            }
        }

        return $found;
    }

    public function supplierApplication(int $buyId, int $supplierOrgId): ?ApplicationCard
    {
        foreach ($this->applications as $application) {
            if ($application->buyId === $buyId && $application->supplierOrgId === $supplierOrgId) {
                return $application;
            }
        }

        return null;
    }

    public function application(int $appId): ApplicationCard
    {
        return $this->applications[$appId] ?? throw new NotFound();
    }

    public function applicationLots(int $appId): array
    {
        return array_values(array_filter(
            $this->applicationLots,
            static fn (ApplicationLotCard $lot): bool => $lot->appId === $appId,
        ));
    }

    public function applicationLot(int $appLotId): ApplicationLotCard
    {
        return $this->applicationLots[$appLotId] ?? throw new NotFound();
    }
}
