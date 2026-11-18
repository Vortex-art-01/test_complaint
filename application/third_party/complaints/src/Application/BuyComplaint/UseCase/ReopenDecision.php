<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\BuyComplaint\DecisionAccess;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\TransactionManager;
use Complaints\Application\Service\StatusTransitioner;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;

/**
 * «Назад» после отзыва: recalled -> in_review, чтобы изменить ответы.
 */
final class ReopenDecision
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DecisionAccess $access,
        private readonly StatusTransitioner $transitions,
        private readonly TransactionManager $transactions,
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
            $decision = $this->access->lockForReviewer($user, $decisionId, [BuyComplaintStatus::Recalled], $now);
            $this->transitions->moveDecision($decision, BuyComplaintStatus::InReview, $user->userId, $now);
        });
    }
}
