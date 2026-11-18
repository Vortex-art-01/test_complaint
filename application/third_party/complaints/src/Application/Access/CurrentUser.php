<?php

declare(strict_types=1);

namespace Complaints\Application\Access;

/**
 * Текущий пользователь портала, его организация и роли модуля.
 */
final readonly class CurrentUser
{
    /**
     * @param list<ModuleRole> $roles
     */
    public function __construct(
        public int $userId,
        public int $organizationId,
        public array $roles,
    ) {
    }

    public function hasRole(ModuleRole $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function hasAnyRole(ModuleRole ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function belongsTo(int $organizationId): bool
    {
        return $this->organizationId === $organizationId;
    }
}
