<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\ModuleRole;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Port\UserCard;

/**
 * Шаг 3 мастера: руководители организации — кандидаты в подписанты.
 */
final class ListSupplierHeads
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly OrganizationDirectory $directory,
    ) {
    }

    /**
     * @return list<UserCard>
     *
     * @throws AccessDenied
     */
    public function execute(): array
    {
        $user = $this->users->currentUser();
        $this->guard->assertAllowed($user->hasRole(ModuleRole::Supplier));

        return $this->directory->headsOf($user->organizationId);
    }
}
