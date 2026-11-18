<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Access\ModuleRole;
use Complaints\Application\Port\AuthorityResolver;
use Complaints\Application\Port\UserCard;

final class FakeAuthorities implements AuthorityResolver
{
    public int $authorityOrgId = 0;

    /** @var array<string, list<int>> роль => пользователи */
    public array $employees = [];

    public function __construct(
        private readonly FakeDirectory $directory,
    ) {
    }

    public function authorityFor(int $buyId): int
    {
        return $this->authorityOrgId;
    }

    public function employees(int $authorityOrgId, ModuleRole $role): array
    {
        return array_map(fn (int $id): UserCard => $this->directory->user($id), $this->employees[$role->value] ?? []);
    }
}
