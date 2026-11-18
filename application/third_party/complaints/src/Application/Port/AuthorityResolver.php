<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

use Complaints\Application\Access\ModuleRole;

/**
 * Уполномоченный орган, рассматривающий жалобы, и его сотрудники.
 */
interface AuthorityResolver
{
    /**
     * Организация уполномоченного органа для закупки.
     */
    public function authorityFor(int $buyId): int;

    /**
     * Сотрудники органа с ролью модуля: Reviewer, Coordinator или Approver.
     *
     * @return list<UserCard>
     */
    public function employees(int $authorityOrgId, ModuleRole $role): array;
}
