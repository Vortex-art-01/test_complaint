<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\BuyComplaint\DecisionAccess;
use Complaints\Application\BuyComplaint\LotAnswers;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\AnswerRepository;
use Complaints\Application\Repository\AttachmentRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Application\Service\DocumentIssuer;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\Document\AttachmentOwner;
use Complaints\Domain\Document\DocumentType;

/**
 * Удаление файла из ответа (мягкое), пока PDF ответа не сформирован.
 */
final class RemoveAnswerAttachment
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DecisionAccess $access,
        private readonly BuyComplaintRepository $decisions,
        private readonly AnswerRepository $answers,
        private readonly AttachmentRepository $attachments,
        private readonly LotAnswers $lotAnswers,
        private readonly DocumentIssuer $documents,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws NotFound
     * @throws ValidationFailed PDF ответа уже сформирован
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(int $attachmentId): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $attachmentId, $now): void {
            $attachment = $this->attachments->get($attachmentId);
            if ($attachment->owner !== AttachmentOwner::Answer) {
                throw new NotFound();
            }

            $answer = $this->answers->get($attachment->ownerId);
            $lot = $this->decisions->lot($answer->buyComplaintLotId);
            $decision = $this->access->lockForReviewer($user, $lot->buyComplaintId, [BuyComplaintStatus::InReview], $now);
            $this->lotAnswers->assertNotFormed($answer->id, 'file');

            $this->attachments->remove($attachmentId, $now);
            $this->documents->retire(DocumentType::ConsolidatedAnswer, $decision->id, $user->userId, $now);
        });
    }
}
