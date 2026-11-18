<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\BuyComplaint\DecisionAccess;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\AnswerRepository;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Application\Service\DocumentIssuer;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\ReviewMethod;
use Complaints\Domain\Document\DocumentType;

/**
 * Выбор способа рассмотрения на всю закупку. Первый выбор закрепляет решение
 * за рассматривающим. Смена способа (не навигация «Назад») удаляет ответы,
 * снимает ссылки на них и снимает с действия PDF ответов; вердикты и типы
 * решений сохраняются — от способа они не зависят.
 */
final class ChooseReviewMethod
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DecisionAccess $access,
        private readonly BuyComplaintRepository $decisions,
        private readonly AnswerRepository $answers,
        private readonly AppComplaintRepository $complaints,
        private readonly DocumentIssuer $documents,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(int $decisionId, ReviewMethod $method): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $decisionId, $method, $now): void {
            $decision = $this->access->lockForReviewer($user, $decisionId, [BuyComplaintStatus::InReview], $now);
            if ($decision->reviewMethod === $method) {
                return;
            }

            if ($decision->reviewMethod !== null) {
                foreach ($this->answers->softDeleteOfBuyComplaint($decisionId, $now) as $answerId) {
                    $this->documents->retire(DocumentType::Answer, $answerId, $user->userId, $now);
                }
                $this->complaints->clearAnswers($decisionId, $now);
            }

            $this->decisions->setReviewMethod($decisionId, $method, $now);
            $this->documents->retire(DocumentType::ConsolidatedAnswer, $decisionId, $user->userId, $now);
        });
    }
}
