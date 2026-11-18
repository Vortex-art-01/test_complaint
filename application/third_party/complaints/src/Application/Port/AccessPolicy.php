<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

use Complaints\Application\Access\CurrentUser;
use Complaints\Application\Access\Permission;

/**
 * Права проекта на действия модуля над закупкой.
 *
 * Модуль сам проверяет свои правила (автор жалобы, выбранный руководитель,
 * закреплённый рассматривающий, маршрут согласования). Порт отвечает на то,
 * что знает только проект: например, относится ли пользователь
 * к организатору закупки или не заблокирована ли организация.
 */
interface AccessPolicy
{
    /**
     * Разрешает ли проект пользователю действие над закупкой.
     * Роль модуля (Permission::requiredRole()) уже проверена.
     */
    public function isAllowed(CurrentUser $user, Permission $permission, int $buyId): bool;
}
