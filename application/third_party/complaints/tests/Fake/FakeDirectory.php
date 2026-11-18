<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Exception\NotFound;
use Complaints\Application\Port\OrganizationCard;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Port\UserCard;

final class FakeDirectory implements OrganizationDirectory
{
    /** @var array<int, OrganizationCard> */
    public array $organizations = [];

    /** @var array<int, UserCard> */
    public array $users = [];

    /** @var array<int, list<int>> */
    public array $heads = [];

    public function organization(int $organizationId): OrganizationCard
    {
        return $this->organizations[$organizationId] ?? throw new NotFound();
    }

    public function user(int $userId): UserCard
    {
        return $this->users[$userId] ?? throw new NotFound();
    }

    public function headsOf(int $organizationId): array
    {
        return array_map(fn (int $id): UserCard => $this->users[$id], $this->heads[$organizationId] ?? []);
    }
}
