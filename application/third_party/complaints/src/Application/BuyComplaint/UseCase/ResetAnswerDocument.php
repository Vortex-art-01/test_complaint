<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\BuyComplaint\DecisionAccess;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\AnswerRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\TransactionManager;
use Complaints\Application\Service\DocumentIssuer;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\Document\DocumentType;

/**
 * «Сбросить»: PDF ответа перестаёт действовать, ответ снова можно править.
 */
final class ResetAnswerDocument
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DecisionAccess $access,
        private readonly BuyComplaintRepository $decisions,
        private readonly AnswerRepository $answers,
        private readonly DocumentIssuer $documents,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws NotFound
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(int $answerId): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $answerId, $now): void {
            $answer = $this->answers->get($answerId);
            $lot = $this->decisions->lot($answer->buyComplaintLotId);
            $decision = $this->access->lockForReviewer($user, $lot->buyComplaintId, [BuyComplaintStatus::InReview], $now);

            $this->documents->retire(DocumentType::Answer, $answerId, $user->userId, $now);
            $this->documents->retire(DocumentType::ConsolidatedAnswer, $decision->id, $user->userId, $now);
        });
    }
}
