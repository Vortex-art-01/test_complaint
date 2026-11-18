<?php

declare(strict_types=1);

namespace Complaints\Application\Exception;

/**
 * Статус записи изменился с момента открытия страницы: условный UPDATE
 * не затронул ни одной строки или переход больше не разрешён.
 */
final class StatusConflict extends ApplicationException
{
    /**
     * @param array<string, scalar> $params
     */
    public function __construct(string $messageKey = 'complaints_error_status_conflict', array $params = [])
    {
        parent::__construct($messageKey, $params);
    }
}
