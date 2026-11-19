<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\ModuleRole;
use Complaints\Application\Access\Permission;
use Complaints\Application\AppComplaint\FilingRules;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\AppComplaintDraft;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\TransactionRunner;

/**
 * «Далее» на шаге 3: создаёт черновик жалобы и её лот.
 * Заявка, лот заявки и лот закупки берутся из заявки поставщика в БД.
 */
final class CreateComplaintDraft
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly FilingRules $rules,
        private readonly AppComplaintRepository $complaints,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return int id жалобы
     *
     * @throws ValidationFailed
     * @throws AccessDenied
     */
    public function execute(CreateComplaintDraftCommand $command): int
    {
        $user = $this->users->currentUser();
        $this->guard->assertAllowed($user->hasRole(ModuleRole::Supplier)
            && $this->guard->can($user, Permission::FileComplaint, $command->buyId));
        $now = $this->clock->now();

        return $this->transactions->transactional(function () use ($command, $user, $now): int {
            $lot = $this->rules->lot($command->buyId, $command->appLotId, $user->organizationId, $now);
            $this->rules->assertHead($user->organizationId, $command->headUserId);

            $this->complaints->lockApplicationLot($lot->appLot->id);
            if ($this->complaints->hasActiveComplaint($lot->appLot->id, null)) {
                throw ValidationFailed::field('app_lot_id', 'complaints_error_duplicate_complaint');
            }

            $complaintId = $this->complaints->create(new AppComplaintDraft(
                $command->buyId,
                $lot->appLot->appId,
                $user->organizationId,
                $user->userId,
                $command->headUserId,
            ), $now);
            $this->complaints->addLot($complaintId, $lot->appLot, $now);

            return $complaintId;
        });
    }
}
