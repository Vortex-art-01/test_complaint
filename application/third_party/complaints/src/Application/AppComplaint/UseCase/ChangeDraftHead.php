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
 * Смена руководителя-подписанта в черновике.
 */
final class ChangeDraftHead
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
    public function execute(int $complaintId, int $headUserId): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $complaintId, $headUserId, $now): void {
            $this->access->lockForAuthor($user, $complaintId, [AppComplaintStatus::Draft], $now);
            $this->rules->assertHead($user->organizationId, $headUserId);
            $this->complaints->changeHead($complaintId, $headUserId, $now);
        });
    }
}
