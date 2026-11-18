<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Project;

use Complaints\Application\Access\ModuleRole;
use Complaints\Application\Port\AuthorityResolver;
use Complaints\Application\Port\UserCard;

/**
 * Один уполномоченный орган на все закупки модуля (config/complaints.php →
 * authority_org_id); сотрудники органа с ролями модуля берутся из проекта.
 */
final class ConfiguredAuthorityResolver implements AuthorityResolver
{
    public function __construct(
        private readonly int $authorityOrgId,
    ) {
    }

    public function authorityFor(int $buyId): int
    {
        return $this->authorityOrgId;
    }

    public function employees(int $authorityOrgId, ModuleRole $role): array
    {
        return $this->usersWithRole($authorityOrgId, $role);
    }

    /**
     * @return list<UserCard>
     */
    private function usersWithRole(int $organizationId, ModuleRole $role): array
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Вернуть активных сотрудников организации, у которых есть роль проекта,
        // сопоставленная роли модуля $role в role_map (Reviewer — «Рассматривающий
        // жалобы», Coordinator — «Согласующий жалобы», Approver — «Утверждающий жалобы»),
        // как list<UserCard>, по ФИО. Из этого списка выбирают маршрут согласования
        // и рассылают уведомления о новых жалобах.
        throw ProjectServiceMissing::in(__METHOD__);
    }
}
