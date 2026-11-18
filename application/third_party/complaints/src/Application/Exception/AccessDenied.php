<?php

declare(strict_types=1);

namespace Complaints\Application\Exception;

/**
 * Пользователь не вправе выполнить действие.
 */
final class AccessDenied extends ApplicationException
{
    /**
     * @param array<string, scalar> $params
     */
    public function __construct(string $messageKey = 'complaints_error_access_denied', array $params = [])
    {
        parent::__construct($messageKey, $params);
    }
}
