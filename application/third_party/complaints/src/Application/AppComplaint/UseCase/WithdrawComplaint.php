<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

use Complaints\Application\AppComplaint\DraftAccess;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Application\Service\StatusTransitioner;
use Complaints\Domain\AppComplaint\AppComplaintStatus;

/**
 * «Отозвать»: submitted -> withdrawn, только до окончания срока обжалования.
 * Подпись руководителя сохраняется: жалобу можно подать повторно без изменений.
 */
final class WithdrawComplaint
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DraftAccess $access,
        private readonly StatusTransitioner $transitions,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws ValidationFailed срок обжалования истёк
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(int $complaintId): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $complaintId, $now): void {
            $complaint = $this->access->lockForAuthor($user, $complaintId, [AppComplaintStatus::Submitted], $now);
            $this->transitions->moveComplaint(
                $complaint,
                AppComplaintStatus::Withdrawn,
                $user->userId,
                $now,
                ['withdrawn_at' => $now],
            );
        });
    }
}
