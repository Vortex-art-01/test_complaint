<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Exception\NotFound;

/**
 * Чтение существующих таблиц проекта: закупки, лоты, заявки, лоты заявок.
 */
interface ProcurementRepository
{
    public function findBuyByNumber(string $number): ?BuyCard;

    /**
     * @throws NotFound
     */
    public function buy(int $buyId): BuyCard;

    /**
     * @throws NotFound
     */
    public function buyLot(int $buyLotId): BuyLotCard;

    /**
     * @param list<int> $buyLotIds
     * @return array<int, BuyLotCard> по id лота
     */
    public function buyLots(array $buyLotIds): array;

    /**
     * Заявка организации на закупку; null — заявки нет.
     */
    public function supplierApplication(int $buyId, int $supplierOrgId): ?ApplicationCard;

    /**
     * @throws NotFound
     */
    public function application(int $appId): ApplicationCard;

    /**
     * @return list<ApplicationLotCard>
     */
    public function applicationLots(int $appId): array;

    /**
     * @throws NotFound
     */
    public function applicationLot(int $appLotId): ApplicationLotCard;
}
