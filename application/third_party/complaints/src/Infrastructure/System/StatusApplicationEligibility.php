<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\System;

use Complaints\Application\Port\ApplicationEligibility;
use Complaints\Infrastructure\Persistence\Database;
use InvalidArgumentException;

/**
 * Право на жалобу по статусам заявки и лота заявки (config/complaints.php →
 * eligibility). Пустой список статусов — подходит любой статус.
 */
final class StatusApplicationEligibility implements ApplicationEligibility
{
    private const IDENTIFIER = '/^[a-z_][a-z0-9_]{0,62}$/';

    /**
     * @param list<int> $appStatuses    статусы test_app, дающие право на жалобу
     * @param list<int> $appLotStatuses статусы test_app_lots, дающие право на жалобу
     */
    public function __construct(
        private readonly Database $db,
        private readonly string $appTable,
        private readonly string $appStatusColumn,
        private readonly array $appStatuses,
        private readonly string $appLotTable,
        private readonly string $appLotStatusColumn,
        private readonly array $appLotStatuses,
    ) {
        foreach ([$appStatusColumn, $appLotStatusColumn] as $column) {
            if (preg_match(self::IDENTIFIER, $column) !== 1) {
                throw new InvalidArgumentException('complaints[eligibility]: имя столбца ' . var_export($column, true));
            }
        }
    }

    public function isEligible(int $appId, int $appLotId): bool
    {
        return $this->hasStatus($this->appTable, $this->appStatusColumn, $appId, $this->appStatuses)
            && $this->hasStatus($this->appLotTable, $this->appLotStatusColumn, $appLotId, $this->appLotStatuses);
    }

    /**
     * @param list<int> $statuses
     */
    private function hasStatus(string $table, string $column, int $id, array $statuses): bool
    {
        if ($statuses === []) {
            return true;
        }

        $status = $this->db->value(sprintf('SELECT %s FROM %s WHERE id = :id', $column, $table), ['id' => $id]);

        return $status !== null && in_array((int) $status, $statuses, true);
    }
}
