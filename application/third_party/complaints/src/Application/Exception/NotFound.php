<?php

declare(strict_types=1);

namespace Complaints\Application\Exception;

/**
 * Запись не найдена или удалена.
 */
final class NotFound extends ApplicationException
{
    /**
     * @param array<string, scalar> $params
     */
    public function __construct(string $messageKey = 'complaints_error_not_found', array $params = [])
    {
        parent::__construct($messageKey, $params);
    }
}
