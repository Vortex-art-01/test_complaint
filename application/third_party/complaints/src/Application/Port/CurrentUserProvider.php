<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

use Complaints\Application\Access\CurrentUser;
use Complaints\Application\Exception\NotAuthenticated;

/**
 * Текущий пользователь портала.
 */
interface CurrentUserProvider
{
    /**
     * Пользователь, его организация и роли модуля (роли проекта,
     * сопоставленные ModuleRole).
     *
     * @throws NotAuthenticated пользователь не вошёл в систему
     */
    public function currentUser(): CurrentUser;
}
