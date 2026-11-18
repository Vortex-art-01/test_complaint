<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\BuyComplaint\DecisionAccess;
use Complaints\Application\BuyComplaint\DecisionPdf;
use Complaints\Application\BuyComplaint\LotAnswers;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\AnswerRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\TransactionManager;
use Complaints\Application\Service\DocumentIssuer;
use Complaints\Domain\AppComplaint\AppComplaintLot;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\Document\DocumentType;

/**
 * «Сформировать»: PDF ответа. После этого текст, вердикты и файлы ответа
 * меняются только через «Сбросить».
 */
final class FormAnswerDocument
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DecisionAccess $access,
        private readonly BuyComplaintRepository $decisions,
        private readonly AnswerRepository $answers,
        private readonly LotAnswers $lotAnswers,
        private readonly DecisionPdf $pdf,
        private readonly DocumentIssuer $documents,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws NotFound
     * @throws ValidationFailed нет текста ответа или вердикта
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

            $linked = array_values(array_filter(
                $this->lotAnswers->complaintLots($lot),
                static fn (AppComplaintLot $complaintLot): bool => $complaintLot->answerId === $answerId,
            ));
            if ($linked === []) {
                throw new NotFound();
            }
            if (!$answer->hasText()) {
                throw ValidationFailed::field('answer', 'complaints_error_answer_empty');
            }
            foreach ($linked as $complaintLot) {
                if ($complaintLot->verdict === null) {
                    throw ValidationFailed::field('verdict', 'complaints_error_verdict_required');
                }
            }

            $this->pdf->answer($decision, $lot, $answer, $linked, $user->userId, $now);
            $this->documents->retire(DocumentType::ConsolidatedAnswer, $decision->id, $user->userId, $now);
        });
    }
}
