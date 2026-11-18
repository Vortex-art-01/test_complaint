<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\BuyComplaint\DecisionAccess;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;

/**
 * Утверждающий органа снимает закрепление решения за рассматривающим —
 * например, на время отсутствия сотрудника.
 */
final class ReleaseDecision
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly DecisionAccess $access,
        private readonly BuyComplaintRepository $decisions,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(int $decisionId): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $decisionId, $now): void {
            $decision = $this->decisions->lock($decisionId);
            $this->guard->assertAllowed($this->guard->canRelease($user, $decision));
            $this->access->assertStatus($decision, [BuyComplaintStatus::InReview, BuyComplaintStatus::Recalled]);
            $this->decisions->release($decisionId, $now);
        });
    }
}
