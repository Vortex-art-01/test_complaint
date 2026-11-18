<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

use Complaints\Application\AppComplaint\DraftAccess;
use Complaints\Application\AppComplaint\FilingRules;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Domain\AppComplaint\AppComplaintStatus;

/**
 * Смена лота в сохранённом черновике — в пределах той же заявки.
 * Прежний лот помечается удалённым, срок обжалования берётся у нового лота.
 */
final class ChangeDraftLot
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DraftAccess $access,
        private readonly FilingRules $rules,
        private readonly AppComplaintRepository $complaints,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws ValidationFailed
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(int $complaintId, int $appLotId): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $complaintId, $appLotId, $now): void {
            $complaint = $this->access->lockForAuthor($user, $complaintId, [AppComplaintStatus::Draft], $now);
            $current = $this->complaints->lotOf($complaintId);
            if ($current->appLotId === $appLotId) {
                return;
            }

            $lot = $this->rules->lot($complaint->buyId, $appLotId, $user->organizationId, $now);
            if ($lot->appLot->appId !== $complaint->appId) {
                throw ValidationFailed::field('app_lot_id', 'complaints_error_lot_not_complainable');
            }

            $this->complaints->lockApplicationLot($appLotId);
            if ($this->complaints->hasActiveComplaint($appLotId, $lot->appealPeriod->endsAt, $complaintId)) {
                throw ValidationFailed::field('app_lot_id', 'complaints_error_duplicate_complaint');
            }

            $this->complaints->removeLot($current->id, $now);
            $this->complaints->addLot($complaintId, $lot->appLot, $now);
            $this->complaints->changeAppealBasis($complaintId, $lot->protocolPublishedAt, $lot->appealPeriod, $now);
        });
    }
}
