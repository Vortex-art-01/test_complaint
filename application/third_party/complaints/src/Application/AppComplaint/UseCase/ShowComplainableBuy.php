<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\ModuleRole;
use Complaints\Application\Access\Permission;
use Complaints\Application\AppComplaint\ComplainableBuy;
use Complaints\Application\AppComplaint\FilingRules;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;

/**
 * Шаг 2 мастера: лоты своей заявки, по которым срок обжалования открыт.
 */
final class ShowComplainableBuy
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly FilingRules $rules,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws ValidationFailed
     * @throws AccessDenied
     */
    public function execute(int $buyId): ComplainableBuy
    {
        $user = $this->users->currentUser();
        $this->guard->assertAllowed($this->guard->can($user, Permission::FileComplaint, $buyId)
            && $user->hasRole(ModuleRole::Supplier));

        return $this->rules->buyById($buyId, $user->organizationId, $this->clock->now());
    }
}
