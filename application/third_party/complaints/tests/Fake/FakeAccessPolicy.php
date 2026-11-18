<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Access\CurrentUser;
use Complaints\Application\Access\Permission;
use Complaints\Application\Port\AccessPolicy;

final class FakeAccessPolicy implements AccessPolicy
{
    /** @var list<Permission> */
    public array $denied = [];

    /** @var array<int, int> buyId => организация-организатор */
    public array $organizers = [];

    public function isAllowed(CurrentUser $user, Permission $permission, int $buyId): bool
    {
        if (in_array($permission, $this->denied, true)) {
            return false;
        }

        return $permission !== Permission::ExecuteDecision || ($this->organizers[$buyId] ?? null) === $user->organizationId;
    }
}
