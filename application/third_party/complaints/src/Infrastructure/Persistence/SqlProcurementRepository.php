<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Repository\ApplicationCard;
use Complaints\Application\Repository\ApplicationLotCard;
use Complaints\Application\Repository\BuyCard;
use Complaints\Application\Repository\BuyLotCard;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Application\Shared\LocalizedText;

/**
 * Закупки, лоты и заявки проекта — только чтение, имена из ProjectSchema.
 */
final class SqlProcurementRepository implements ProcurementRepository
{
    public function __construct(
        private readonly Database $db,
        private readonly ProjectSchema $schema,
        private readonly SqlValues $values,
    ) {
    }

    public function findBuyByNumber(string $number): ?BuyCard
    {
        $row = $this->db->row(
            $this->buySelect() . sprintf(' WHERE b.%s = :number ORDER BY b.id DESC LIMIT 1', $this->schema->buyNumber()),
            ['number' => $number],
        );

        return $row === null ? null : $this->buyCard($row);
    }

    public function buy(int $buyId): BuyCard
    {
        $row = $this->db->row($this->buySelect() . ' WHERE b.id = :id', ['id' => $buyId]);

        return $row === null ? throw new NotFound() : $this->buyCard($row);
    }

    public function buyLot(int $buyLotId): BuyLotCard
    {
        $row = $this->db->row($this->lotSelect() . ' WHERE l.id = :id', ['id' => $buyLotId]);

        return $row === null ? throw new NotFound() : $this->lotCard($row);
    }

    public function buyLots(array $buyLotIds): array
    {
        if ($buyLotIds === []) {
            return [];
        }

        $lots = [];
        $rows = $this->db->rows(
            $this->lotSelect() . ' WHERE l.id = ANY (CAST(:ids AS bigint[]))',
            ['ids' => Database::intArray($buyLotIds)],
        );
        foreach ($rows as $row) {
            $card = $this->lotCard($row);
            $lots[$card->id] = $card;
        }

        return $lots;
    }

    public function supplierApplication(int $buyId, int $supplierOrgId): ?ApplicationCard
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса, если у организации может быть
        // несколько заявок на закупку (отозванные, черновики): вернуть заявку, по которой
        // подведены итоги. Сейчас берётся последняя заявка организации на закупку.
        $row = $this->db->row(
            $this->appSelect() . sprintf(' WHERE a.buy_id = :buy AND a.%s = :supplier ORDER BY a.id DESC LIMIT 1', $this->schema->appSupplier()),
            ['buy' => $buyId, 'supplier' => $supplierOrgId],
        );

        return $row === null ? null : $this->applicationCard($row);
    }

    public function application(int $appId): ApplicationCard
    {
        $row = $this->db->row($this->appSelect() . ' WHERE a.id = :id', ['id' => $appId]);

        return $row === null ? throw new NotFound() : $this->applicationCard($row);
    }

    public function applicationLots(int $appId): array
    {
        return array_map(
            fn (array $row): ApplicationLotCard => $this->applicationLotCard($row),
            $this->db->rows($this->appLotSelect() . ' WHERE al.test_app_id = :app ORDER BY al.id', ['app' => $appId]),
        );
    }

    public function applicationLot(int $appLotId): ApplicationLotCard
    {
        $row = $this->db->row($this->appLotSelect() . ' WHERE al.id = :id', ['id' => $appLotId]);

        return $row === null ? throw new NotFound() : $this->applicationLotCard($row);
    }

    private function buySelect(): string
    {
        return sprintf(
            'SELECT b.id, b.%s AS number, b.%s AS name_ru, b.%s AS name_kz, b.%s AS method_id, b.%s AS organizer_org_id, b.%s AS published_at FROM %s b',
            $this->schema->buyNumber(),
            $this->schema->buyNameRu(),
            $this->schema->buyNameKz(),
            $this->schema->buyMethod(),
            $this->schema->buyOrganizer(),
            $this->schema->buyPublishedAt(),
            $this->schema->buyTable(),
        );
    }

    private function lotSelect(): string
    {
        return sprintf(
            'SELECT l.id, l.%s AS buy_id, l.%s AS number, l.%s AS name_ru, l.%s AS name_kz FROM %s l',
            $this->schema->lotBuy(),
            $this->schema->lotNumber(),
            $this->schema->lotNameRu(),
            $this->schema->lotNameKz(),
            $this->schema->lotTable(),
        );
    }

    private function appSelect(): string
    {
        return sprintf('SELECT a.id, a.buy_id, a.%s AS supplier_org_id FROM %s a', $this->schema->appSupplier(), $this->schema->appTable());
    }

    private function appLotSelect(): string
    {
        return sprintf('SELECT al.id, al.test_app_id, al.test_buy_lots_id FROM %s al', $this->schema->appLotTable());
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buyCard(array $row): BuyCard
    {
        return new BuyCard(
            $this->values->int($row['id']),
            (string) $row['number'],
            new LocalizedText((string) $row['name_ru'], (string) ($row['name_kz'] ?? $row['name_ru'])),
            $this->values->int($row['method_id']),
            $this->values->int($row['organizer_org_id']),
            $this->values->moment($row['published_at']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function lotCard(array $row): BuyLotCard
    {
        return new BuyLotCard(
            $this->values->int($row['id']),
            $this->values->int($row['buy_id']),
            (string) $row['number'],
            new LocalizedText((string) $row['name_ru'], (string) ($row['name_kz'] ?? $row['name_ru'])),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function applicationCard(array $row): ApplicationCard
    {
        return new ApplicationCard(
            $this->values->int($row['id']),
            $this->values->int($row['buy_id']),
            $this->values->int($row['supplier_org_id']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function applicationLotCard(array $row): ApplicationLotCard
    {
        return new ApplicationLotCard(
            $this->values->int($row['id']),
            $this->values->int($row['test_app_id']),
            $this->values->int($row['test_buy_lots_id']),
        );
    }
}
