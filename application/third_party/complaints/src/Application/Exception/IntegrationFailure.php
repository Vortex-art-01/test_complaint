<?php

declare(strict_types=1);

namespace Complaints\Application\Exception;

use Throwable;

/**
 * Сервис проекта (закупки, файлы, уведомления) не выполнил запрос модуля.
 */
final class IntegrationFailure extends ApplicationException
{
    public function __construct(string $messageKey = 'complaints_error_integration', ?Throwable $previous = null)
    {
        parent::__construct($messageKey, [], $previous);
    }
}
