<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\System;

use Complaints\Application\Access\CurrentUser;
use Complaints\Application\Access\Permission;
use Complaints\Application\Port\AccessPolicy;
use Complaints\Application\Repository\ProcurementRepository;

/**
 * Права проекта по умолчанию: роль модуля уже проверена ядром;
 * исполнять решение может только сотрудник организатора этой закупки.
 *
 * Если в проекте есть дополнительные запреты (блокировка организации,
 * ограничения по закупке), их добавляют здесь: метод вызывается перед
 * каждым действием модуля над закупкой.
 */
final class OrganizerAccessPolicy implements AccessPolicy
{
    public function __construct(
        private readonly ProcurementRepository $procurement,
    ) {
    }

    public function isAllowed(CurrentUser $user, Permission $permission, int $buyId): bool
    {
        if ($permission === Permission::ExecuteDecision) {
            return $user->belongsTo($this->procurement->buy($buyId)->organizerOrgId);
        }

        return true;
    }
}
