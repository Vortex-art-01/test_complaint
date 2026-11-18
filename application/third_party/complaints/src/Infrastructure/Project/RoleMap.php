<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Project;

use Complaints\Application\Access\ModuleRole;
use InvalidArgumentException;

/**
 * Соответствие ролей проекта ролям модуля (config/complaints.php → role_map):
 * код роли проекта => список ролей модуля.
 */
final readonly class RoleMap
{
    /**
     * @param array<string, list<ModuleRole>> $map
     */
    private function __construct(
        private array $map,
    ) {
    }

    /**
     * @param array<string|int, list<string>> $config
     *
     * @throws InvalidArgumentException неизвестная роль модуля
     */
    public static function fromConfig(array $config): self
    {
        $map = [];
        foreach ($config as $projectRole => $moduleRoles) {
            foreach ((array) $moduleRoles as $moduleRole) {
                $map[(string) $projectRole][] = ModuleRole::tryFrom((string) $moduleRole)
                    ?? throw new InvalidArgumentException(sprintf('complaints[role_map][%s]: неизвестная роль модуля %s.', $projectRole, var_export($moduleRole, true)));
            }
        }

        return new self($map);
    }

    /**
     * @param list<string|int> $projectRoles коды ролей пользователя в проекте
     * @return list<ModuleRole>
     */
    public function moduleRoles(array $projectRoles): array
    {
        $roles = [];
        foreach ($projectRoles as $projectRole) {
            foreach ($this->map[(string) $projectRole] ?? [] as $role) {
                if (!in_array($role, $roles, true)) {
                    $roles[] = $role;
                }
            }
        }

        return $roles;
    }
}
