<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use InvalidArgumentException;

/**
 * Имена таблиц и столбцов проекта, которые читает модуль (закупка, лоты,
 * заявки). Задаются в config/complaints.php → complaints[project_schema];
 * в SQL подставляются только после проверки, что это идентификаторы.
 *
 * Таблицы должны совпадать с REFERENCES миграций 003–004 (trd_buy,
 * trd_buy_lot, trd_app, trd_app_lot); столбцы срока обжалования
 * добавляет в закупку миграция 002.
 */
final readonly class ProjectSchema
{
    private const IDENTIFIER = '/^[a-z_][a-z0-9_]{0,62}(\.[a-z_][a-z0-9_]{0,62})?$/';

    private const REQUIRED = [
        'buy' => ['table', 'number', 'name_ru', 'name_kz', 'method', 'organizer', 'published_at', 'appeal_start', 'appeal_end'],
        'buy_lot' => ['table', 'buy', 'number', 'name_ru', 'name_kz'],
        'app' => ['table', 'buy', 'supplier'],
        'app_lot' => ['table', 'app', 'buy_lot'],
    ];

    /**
     * @param array<string, array<string, string>> $names
     */
    private function __construct(
        private array $names,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws InvalidArgumentException не хватает имени или имя не идентификатор
     */
    public static function fromConfig(array $config): self
    {
        $names = [];
        foreach (self::REQUIRED as $entity => $keys) {
            foreach ($keys as $key) {
                $name = $config[$entity][$key] ?? null;
                if (!is_string($name) || preg_match(self::IDENTIFIER, $name) !== 1) {
                    throw new InvalidArgumentException(sprintf(
                        'complaints_project_schema[%s][%s]: ожидалось имя таблицы или столбца, получено %s.',
                        $entity,
                        $key,
                        var_export($name, true),
                    ));
                }
                $names[$entity][$key] = $name;
            }
        }

        return new self($names);
    }

    public function buyTable(): string
    {
        return $this->names['buy']['table'];
    }

    public function buyNumber(): string
    {
        return $this->names['buy']['number'];
    }

    public function buyNameRu(): string
    {
        return $this->names['buy']['name_ru'];
    }

    public function buyNameKz(): string
    {
        return $this->names['buy']['name_kz'];
    }

    public function buyMethod(): string
    {
        return $this->names['buy']['method'];
    }

    public function buyOrganizer(): string
    {
        return $this->names['buy']['organizer'];
    }

    public function buyPublishedAt(): string
    {
        return $this->names['buy']['published_at'];
    }

    /**
     * Первый день срока обжалования (date).
     */
    public function buyAppealStart(): string
    {
        return $this->names['buy']['appeal_start'];
    }

    /**
     * Последний день срока обжалования включительно (date).
     */
    public function buyAppealEnd(): string
    {
        return $this->names['buy']['appeal_end'];
    }

    public function lotTable(): string
    {
        return $this->names['buy_lot']['table'];
    }

    public function lotBuy(): string
    {
        return $this->names['buy_lot']['buy'];
    }

    public function lotNumber(): string
    {
        return $this->names['buy_lot']['number'];
    }

    public function lotNameRu(): string
    {
        return $this->names['buy_lot']['name_ru'];
    }

    public function lotNameKz(): string
    {
        return $this->names['buy_lot']['name_kz'];
    }

    public function appTable(): string
    {
        return $this->names['app']['table'];
    }

    public function appBuy(): string
    {
        return $this->names['app']['buy'];
    }

    public function appSupplier(): string
    {
        return $this->names['app']['supplier'];
    }

    public function appLotTable(): string
    {
        return $this->names['app_lot']['table'];
    }

    public function appLotApp(): string
    {
        return $this->names['app_lot']['app'];
    }

    public function appLotBuyLot(): string
    {
        return $this->names['app_lot']['buy_lot'];
    }
}
