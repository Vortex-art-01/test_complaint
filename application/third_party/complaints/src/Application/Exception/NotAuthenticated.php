<?php

declare(strict_types=1);

namespace Complaints\Application\Exception;

/**
 * Пользователь не вошёл в систему.
 */
final class NotAuthenticated extends ApplicationException
{
    public function __construct()
    {
        parent::__construct('complaints_error_not_authenticated');
    }
}
