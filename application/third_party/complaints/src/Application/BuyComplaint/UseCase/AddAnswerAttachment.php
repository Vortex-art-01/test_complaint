<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\BuyComplaint\DecisionAccess;
use Complaints\Application\BuyComplaint\LotAnswers;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\FileStorage;
use Complaints\Application\Repository\AttachmentRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Application\Service\AttachmentRules;
use Complaints\Application\Service\DocumentIssuer;
use Complaints\Application\Shared\UploadedFile;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\Document\AttachmentOwner;
use Complaints\Domain\Document\DocumentType;

/**
 * «Прикрепить» файл к ответу: per_complaint — к ответу лота жалобы,
 * per_lot — к ответу на весь лот. Ответ создаётся, если его ещё нет.
 */
final class AddAnswerAttachment
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DecisionAccess $access,
        private readonly BuyComplaintRepository $decisions,
        private readonly LotAnswers $lotAnswers,
        private readonly AttachmentRules $rules,
        private readonly AttachmentRepository $attachments,
        private readonly FileStorage $files,
        private readonly DocumentIssuer $documents,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param int|null $complaintLotId лот жалобы; для per_lot не нужен
     * @return int id вложения
     *
     * @throws ValidationFailed
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(int $decisionLotId, ?int $complaintLotId, UploadedFile $file): int
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        return $this->transactions->transactional(function () use ($user, $decisionLotId, $complaintLotId, $file, $now): int {
            $lot = $this->decisions->lot($decisionLotId);
            $decision = $this->access->lockForReviewer($user, $lot->buyComplaintId, [BuyComplaintStatus::InReview], $now);
            if ($decision->reviewMethod === null) {
                throw ValidationFailed::field('form', 'complaints_error_method_required');
            }

            $answerId = $this->lotAnswers->ensureAnswer($decision, $lot, $complaintLotId, $user->userId, $now);
            $this->lotAnswers->assertNotFormed($answerId, 'file');
            $this->rules->assertAcceptable($file, count($this->attachments->of(AttachmentOwner::Answer, $answerId)));

            $fileId = $this->files->store($file->bytes, $file->fileName, $file->mimeType, $user->userId);
            $attachmentId = $this->attachments->add(AttachmentOwner::Answer, $answerId, $fileId, $user->userId, $now);
            $this->documents->retire(DocumentType::ConsolidatedAnswer, $decision->id, $user->userId, $now);

            return $attachmentId;
        });
    }
}
