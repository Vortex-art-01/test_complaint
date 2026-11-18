<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Project;

use Complaints\Application\Access\CurrentUser;
use Complaints\Application\Exception\NotAuthenticated;
use Complaints\Application\Port\CurrentUserProvider;

/**
 * Текущий пользователь портала: id, организация и роли модуля по role_map.
 */
final class ProjectCurrentUserProvider implements CurrentUserProvider
{
    private ?CurrentUser $user = null;

    public function __construct(
        private readonly RoleMap $roles,
    ) {
    }

    public function currentUser(): CurrentUser
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $userId = $this->loggedInUserId() ?? throw new NotAuthenticated();

        return $this->user = new CurrentUser(
            $userId,
            $this->organizationOf($userId),
            $this->roles->moduleRoles($this->projectRolesOf($userId)),
        );
    }

    private function loggedInUserId(): ?int
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Вернуть id пользователя, вошедшего в портал (тот же id, что проект пишет
        // в created_by и т. п.), из сессии или библиотеки авторизации проекта;
        // null — пользователь не вошёл. Вызывается в начале каждого действия.
        throw ProjectServiceMissing::in(__METHOD__);
    }

    private function organizationOf(int $userId): int
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Вернуть id организации, от имени которой пользователь сейчас работает
        // в портале (поставщик, уполномоченный орган или организатор).
        throw ProjectServiceMissing::in(__METHOD__);
    }

    /**
     * @return list<string|int>
     */
    private function projectRolesOf(int $userId): array
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Вернуть коды ролей пользователя в проекте («Руководитель», «Рассматривающий
        // жалобы», «Согласующий жалобы», «Утверждающий жалобы», роль сотрудника
        // поставщика и организатора). Соответствие ролям модуля — в role_map конфига.
        throw ProjectServiceMissing::in(__METHOD__);
    }
}
