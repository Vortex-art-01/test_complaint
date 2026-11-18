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
 * Шаг 1 мастера: закупка по номеру, которую организация может обжаловать.
 */
final class FindComplainableBuy
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly FilingRules $rules,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws ValidationFailed закупки нет, её нельзя обжаловать, нет заявки или срок закрыт
     * @throws AccessDenied
     */
    public function execute(string $buyNumber): ComplainableBuy
    {
        $user = $this->users->currentUser();
        $this->guard->assertAllowed($user->hasRole(ModuleRole::Supplier));

        $buy = $this->rules->buyByNumber($buyNumber, $user->organizationId, $this->clock->now());
        $this->guard->assertAllowed($this->guard->can($user, Permission::FileComplaint, $buy->buy->id));

        return $buy;
    }
}
