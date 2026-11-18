<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

use Complaints\Application\AppComplaint\ComplaintContentRules;
use Complaints\Application\AppComplaint\DraftAccess;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Domain\AppComplaint\AppComplaintStatus;

/**
 * «Сохранить» на форме черновика: телефон и описание. Пустые поля допустимы,
 * обязательность проверяется при отправке на подпись.
 */
final class UpdateComplaintDraft
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DraftAccess $access,
        private readonly ComplaintContentRules $content,
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
    public function execute(UpdateComplaintDraftCommand $command): void
    {
        $user = $this->users->currentUser();
        $this->content->check($command->contactPhone, $command->description, false);
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($command, $user, $now): void {
            $this->access->lockForAuthor($user, $command->complaintId, [AppComplaintStatus::Draft], $now);
            $this->complaints->updateContent(
                $command->complaintId,
                ComplaintContentRules::normalize($command->contactPhone),
                ComplaintContentRules::normalize($command->description),
                $now,
            );
        });
    }
}
