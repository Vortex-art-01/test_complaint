<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\Access\Actor;
use Complaints\Application\BuyComplaint\DecisionAccess;
use Complaints\Application\BuyComplaint\DecisionCompleteness;
use Complaints\Application\BuyComplaint\DecisionPdf;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;

/**
 * «Далее» на списке лотов: ФЛК и сводный документ ответов с реестром
 * PDF ответов и их контрольными суммами. После отзыва — новая версия.
 */
final class FormConsolidatedDocument
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DecisionAccess $access,
        private readonly DecisionCompleteness $completeness,
        private readonly DecisionPdf $pdf,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws ValidationFailed не заполнены решения или не сформированы ответы
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(int $decisionId): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $decisionId, $now): void {
            $decision = $this->access->lockForReviewer(
                $user,
                $decisionId,
                [BuyComplaintStatus::InReview, BuyComplaintStatus::Recalled],
                $now,
            );
            if ($decision->reviewMethod === null) {
                throw ValidationFailed::field('form', 'complaints_error_method_required');
            }

            $this->completeness->assertComplete($decisionId);
            $this->pdf->consolidated($decision, Actor::of($user), $now);
        });
    }
}
